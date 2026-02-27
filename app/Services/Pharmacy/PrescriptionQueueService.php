<?php

namespace App\Services\Pharmacy;

use Illuminate\Support\Facades\DB;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueLog;
use App\Models\Record\Prescriptions\Prescription;
use Illuminate\Support\Facades\Log;

class PrescriptionQueueService
{
    /**
     * Log queue action without changing status
     */
    public function logQueueAction($queueId, $userId = null, $remarks = null)
    {
        try {
            $queue = PrescriptionQueue::findOrFail($queueId);

            // Create log entry
            PrescriptionQueueLog::create([
                'queue_id' => $queueId,
                'status_from' => $queue->queue_status,
                'status_to' => $queue->queue_status, // Same status
                'changed_by' => $userId,
                'remarks' => $remarks,
            ]);

            return [
                'success' => true,
                'message' => 'Action logged successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to log action',
            ];
        }
    }

    /**
     * Create a new queue entry for a prescription
     * This is called from EMR or PDIMS when a prescription is created
     */
    public function createQueue(array $data)
    {
        DB::connection('webapp')->beginTransaction();
        try {
            // Validate required fields
            $validated = $this->validateQueueData($data);

            // Generate queue number
            $queueData = PrescriptionQueue::generateQueueNumber(
                $validated['location_code'],
                $validated['queue_prefix'] ?? 'RX',
                $validated['priority'] ?? 'normal'
            );

            // Estimate wait time
            $estimatedWait = PrescriptionQueue::estimateWaitTime(
                $validated['location_code'],
                $validated['priority'] ?? 'normal'
            );

            // Create queue
            $queue = PrescriptionQueue::create([
                'prescription_id' => $validated['prescription_id'],
                'enccode' => $validated['enccode'],
                'hpercode' => $validated['hpercode'],
                'queue_number' => $queueData['queue_number'],
                'queue_prefix' => $validated['queue_prefix'] ?? 'RX',
                'sequence_number' => $queueData['sequence_number'],
                'location_code' => $validated['location_code'],
                'queue_status' => 'waiting',
                'priority' => $validated['priority'] ?? 'normal',
                'queued_at' => now(),
                'estimated_wait_minutes' => $estimatedWait,
                'remarks' => $validated['remarks'] ?? null,
                'created_from' => $validated['created_from'] ?? 'EMR',
            ]);

            // Log creation
            PrescriptionQueueLog::create([
                'queue_id' => $queue->id,
                'status_from' => null,
                'status_to' => 'waiting',
                'changed_by' => $validated['created_by'] ?? null,
                'remarks' => 'Queue created',
            ]);

            DB::connection('webapp')->commit();

            return [
                'success' => true,
                'queue' => $queue,
                'message' => 'Queue created successfully',
            ];
        } catch (\Exception $e) {
            DB::connection('webapp')->rollBack();
            Log::error($e);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to create queue',
            ];
        }
    }

    /**
     * Update queue status
     */
    public function updateQueueStatus($queueId, $newStatus, $userId = null, $remarks = null)
    {
        DB::connection('webapp')->beginTransaction();
        try {
            $queue = PrescriptionQueue::findOrFail($queueId);
            $oldStatus = $queue->queue_status;

            // Update based on status
            $updates = ['queue_status' => $newStatus];
            switch ($newStatus) {
                case 'preparing':
                    $updates['preparing_at'] = now();
                    $updates['prepared_by'] = $userId;
                    break;
                case 'charging':
                    $updates['charging_at'] = now();
                    $updates['charged_by'] = $userId;
                    break;
                case 'ready':
                    $updates['ready_at'] = now();
                    break;
                case 'dispensed':
                    $updates['dispensed_at'] = now();
                    $updates['dispensed_by'] = $userId;
                    break;
                case 'cancelled':
                    $updates['cancelled_at'] = now();
                    $updates['cancelled_by'] = $userId;
                    $updates['cancellation_reason'] = $remarks;
                    break;
            }

            $queue->update($updates);

            // Log status change
            PrescriptionQueueLog::create([
                'queue_id' => $queueId,
                'status_from' => $oldStatus,
                'status_to' => $newStatus,
                'changed_by' => $userId,
                'remarks' => $remarks,
            ]);

            DB::connection('webapp')->commit();

            // Broadcast event for real-time updates
            \App\Events\Pharmacy\QueueStatusChanged::dispatch($queue->fresh(), $oldStatus, $newStatus);

            return [
                'success' => true,
                'queue' => $queue->fresh(),
                'message' => "Queue status updated to {$newStatus}",
            ];
        } catch (\Exception $e) {
            DB::connection('webapp')->rollBack();
            Log::error($e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to update queue status',
            ];
        }
    }

    /**
     * Validate queue creation data
     */
    private function validateQueueData(array $data)
    {
        $required = ['prescription_id', 'enccode', 'hpercode', 'location_code'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        return $data;
    }

    /**
     * Get location statistics
     */
    public function getLocationStats($locationCode, $date = null)
    {
        $date = $date ?? today();

        $stats = [
            'total' => PrescriptionQueue::forLocation($locationCode)
                ->whereDate('queued_at', $date)
                ->count(),
            'waiting' => PrescriptionQueue::forLocation($locationCode)
                ->waiting()
                ->whereDate('queued_at', $date)
                ->count(),
            'preparing' => PrescriptionQueue::forLocation($locationCode)
                ->preparing()
                ->whereDate('queued_at', $date)
                ->count(),
            'charging' => PrescriptionQueue::forLocation($locationCode)
                ->charging()
                ->whereDate('queued_at', $date)
                ->count(),
            'ready' => PrescriptionQueue::forLocation($locationCode)
                ->ready()
                ->whereDate('queued_at', $date)
                ->count(),
            'dispensed' => PrescriptionQueue::forLocation($locationCode)
                ->where('queue_status', 'dispensed')
                ->whereDate('queued_at', $date)
                ->count(),
            'cancelled' => PrescriptionQueue::forLocation($locationCode)
                ->where('queue_status', 'cancelled')
                ->whereDate('queued_at', $date)
                ->count(),
        ];

        // Calculate average times
        $completed = PrescriptionQueue::forLocation($locationCode)
            ->where('queue_status', 'dispensed')
            ->whereDate('queued_at', $date)
            ->whereNotNull('dispensed_at')
            ->get();

        if ($completed->isNotEmpty()) {
            $stats['avg_wait_time'] = round($completed->avg(function ($q) {
                return $q->getTotalTimeMinutes();
            }));
            $stats['avg_processing_time'] = round($completed->avg(function ($q) {
                return $q->getProcessingTimeMinutes();
            }));
        } else {
            $stats['avg_wait_time'] = 0;
            $stats['avg_processing_time'] = 0;
        }

        return $stats;
    }

    /**
     * Count pending prescriptions for batch queue creation
     */
    public function countPendingPrescriptions($locationCode, $date, $encounterTypes = [])
    {
        $query = DB::connection('webapp')
            ->table('prescriptions as p')
            ->join('hospital2.dbo.henctr as e', 'p.enccode', '=', 'e.enccode')
            ->whereDate('p.created_at', $date)
            ->where('p.location_code', $locationCode)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('prescription_queues')
                    ->whereColumn('prescription_queues.prescription_id', 'p.id')
                    ->whereIn('prescription_queues.queue_status', ['waiting', 'preparing', 'charging', 'ready']);
            });

        if (!empty($encounterTypes)) {
            $query->whereIn('e.toecode', $encounterTypes);
        }

        return $query->count();
    }

    /**
     * Batch create queues for pending prescriptions
     */
    public function batchCreateQueues($locationCode, $date, $encounterTypes = [], $userId = null)
    {
        DB::connection('webapp')->beginTransaction();
        try {
            $prescriptions = DB::connection('webapp')
                ->table('prescriptions as p')
                ->join('hospital2.dbo.henctr as e', 'p.enccode', '=', 'e.enccode')
                ->select('p.id', 'p.enccode', 'e.hpercode')
                ->whereDate('p.created_at', $date)
                ->where('p.location_code', $locationCode)
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('prescription_queues')
                        ->whereColumn('prescription_queues.prescription_id', 'p.id')
                        ->whereIn('prescription_queues.queue_status', ['waiting', 'preparing', 'charging', 'ready']);
                });

            if (!empty($encounterTypes)) {
                $prescriptions->whereIn('e.toecode', $encounterTypes);
            }

            $prescriptions = $prescriptions->get();

            $created = 0;
            foreach ($prescriptions as $prescription) {
                $result = $this->createQueue([
                    'prescription_id' => $prescription->id,
                    'enccode' => $prescription->enccode,
                    'hpercode' => $prescription->hpercode,
                    'location_code' => $locationCode,
                    'priority' => 'normal',
                    'created_by' => $userId,
                    'created_from' => 'Batch',
                ]);

                if ($result['success']) {
                    $created++;
                }
            }

            DB::connection('webapp')->commit();

            return [
                'success' => true,
                'created_count' => $created,
                'message' => "Successfully created {$created} queue(s)",
            ];
        } catch (\Exception $e) {
            DB::connection('webapp')->rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to create batch queues',
            ];
        }
    }

    /**
     * Clean up old expired queues (run daily)
     */
    public function cleanupOldQueues($daysToKeep = 30)
    {
        $cutoffDate = now()->subDays($daysToKeep);

        $deleted = PrescriptionQueue::where('created_at', '<', $cutoffDate)
            ->whereIn('queue_status', ['dispensed', 'cancelled'])
            ->delete();

        return [
            'success' => true,
            'deleted_count' => $deleted,
            'message' => "Cleaned up $deleted old queue records",
        ];
    }

    /**
     * Reset daily sequences (run at midnight)
     */
    public function resetDailySequences()
    {
        // Sequences are automatically managed per day in the model
        // This method can be used for any additional cleanup

        $yesterday = today()->subDay();

        // Archive yesterday's sequences if needed
        DB::connection('webapp')
            ->table('prescription_queue_sequences')
            ->whereDate('created_at', '<', $yesterday)
            ->delete();

        return [
            'success' => true,
            'message' => 'Daily sequences reset',
        ];
    }
}
