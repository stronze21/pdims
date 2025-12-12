<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $query = PrescriptionQueue::with(['patient', 'prescription', 'preparer', 'dispenser'])
            ->whereDate('queued_at', today());

        if ($request->has('location')) {
            $query->where('location_code', $request->location);
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->whereIn('queue_status', ['waiting', 'preparing', 'ready']);
            } else {
                $query->where('queue_status', $request->status);
            }
        }

        $queues = $query->orderByRaw("
            CASE priority
                WHEN 'stat' THEN 1
                WHEN 'urgent' THEN 2
                ELSE 3
            END
        ")->orderBy('queued_at', 'asc')->get();

        return response()->json($queues);
    }

    public function stats(Request $request)
    {
        $locationCode = $request->input('location');
        $date = today();

        $query = PrescriptionQueue::whereDate('queued_at', $date);

        if ($locationCode) {
            $query->where('location_code', $locationCode);
        }

        $stats = [
            'total_today' => (clone $query)->count(),
            'waiting' => (clone $query)->where('queue_status', 'waiting')->count(),
            'preparing' => (clone $query)->where('queue_status', 'preparing')->count(),
            'ready' => (clone $query)->where('queue_status', 'ready')->count(),
            'dispensed' => (clone $query)->where('queue_status', 'dispensed')->count(),
            'cancelled' => (clone $query)->where('queue_status', 'cancelled')->count(),
            'avg_wait_time' => $this->getAverageWaitTime($locationCode),
        ];

        return response()->json($stats);
    }

    private function getAverageWaitTime($locationCode = null)
    {
        $query = PrescriptionQueue::where('queue_status', 'dispensed')
            ->whereDate('queued_at', today())
            ->whereNotNull('dispensed_at');

        if ($locationCode) {
            $query->where('location_code', $locationCode);
        }

        $dispensed = $query->get();

        if ($dispensed->isEmpty()) return 0;

        $totalMinutes = $dispensed->sum(function ($queue) {
            return $queue->getTotalTimeMinutes();
        });

        return round($totalMinutes / $dispensed->count());
    }

    public function updateStatus(Request $request, $id)
    {
        $queue = PrescriptionQueue::findOrFail($id);

        DB::connection('webapp')->beginTransaction();

        try {
            $oldStatus = $queue->queue_status;
            $action = $request->input('action');

            switch ($action) {
                case 'start_preparing':
                    $queue->update([
                        'queue_status' => 'preparing',
                        'preparing_at' => now(),
                        'prepared_by' => auth()->user()->employeeid,
                    ]);
                    break;

                case 'mark_ready':
                    $queue->update([
                        'queue_status' => 'ready',
                        'ready_at' => now(),
                    ]);
                    break;

                case 'mark_dispensed':
                    $queue->update([
                        'queue_status' => 'dispensed',
                        'dispensed_at' => now(),
                        'dispensed_by' => auth()->user()->employeeid,
                    ]);
                    break;

                case 'cancel':
                    $queue->update([
                        'queue_status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancelled_by' => auth()->user()->employeeid,
                        'cancellation_reason' => $request->input('remarks'),
                    ]);
                    break;
            }

            PrescriptionQueueLog::create([
                'queue_id' => $queue->id,
                'status_from' => $oldStatus,
                'status_to' => $queue->queue_status,
                'changed_by' => auth()->user()->employeeid,
                'remarks' => $request->input('remarks'),
            ]);

            DB::connection('webapp')->commit();

            return response()->json($queue->fresh(['patient', 'prescription']));
        } catch (\Exception $e) {
            DB::connection('webapp')->rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
