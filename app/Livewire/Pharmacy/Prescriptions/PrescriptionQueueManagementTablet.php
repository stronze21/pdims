<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Locked;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use App\Services\Pharmacy\PrescriptionQueueService;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\PharmLocation;
use Livewire\Attributes\Layout;

class PrescriptionQueueManagementTablet extends Component
{
    use WithPagination, Toast;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $priorityFilter = '';
    public $dateFilter;
    public $perPage = 25;

    // Batch creation
    public $showBatchCreateModal = false;
    public $batchDate;
    public $batchLocation;
    public $batchTypes = ['OPD'];
    public $availableTypes = [
        'OPD' => 'Out-Patient',
        'ER' => 'Emergency Room',
        'ADM' => 'Admission',
        'ERADM' => 'ER to Admission',
        'OPDAD' => 'OPD to Admission',
    ];

    // Selected queue
    public $selectedQueueId;
    public $showDetailsModal = false;
    public $selectedQueue;

    // Status update
    public $showStatusModal = false;
    public $newStatus;
    public $statusRemarks;

    protected $queueService;

    public function boot(PrescriptionQueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    public function mount()
    {
        $this->dateFilter = today()->format('Y-m-d');
        $this->batchDate = today()->format('Y-m-d');
        $this->batchLocation = auth()->user()->pharm_location_id;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingPriorityFilter()
    {
        $this->resetPage();
    }

    public function updatingDateFilter()
    {
        $this->resetPage();
    }

    /**
     * Get queues for display
     */
    public function getQueuesProperty()
    {
        return PrescriptionQueue::query()
            ->with(['patient', 'prescription', 'preparer', 'dispenser'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('queue_number', 'like', '%' . $this->search . '%')
                        ->orWhere('hpercode', 'like', '%' . $this->search . '%')
                        ->orWhere('enccode', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('queue_status', $this->statusFilter);
            })
            ->when($this->priorityFilter, function ($query) {
                $query->where('priority', $this->priorityFilter);
            })
            ->when($this->dateFilter, function ($query) {
                $query->whereDate('queued_at', $this->dateFilter);
            })
            ->forLocation(auth()->user()->pharm_location_id)
            ->orderByPriority()
            ->paginate($this->perPage);
    }

    /**
     * Get statistics for the current filters
     */
    public function getStatsProperty()
    {
        return $this->queueService->getLocationStats(
            auth()->user()->pharm_location_id,
            $this->dateFilter ? \Carbon\Carbon::parse($this->dateFilter) : today()
        );
    }

    /**
     * Get available locations
     */
    public function getLocationsProperty()
    {
        return PharmLocation::orderBy('description')->get();
    }

    /**
     * Open batch create modal
     */
    public function openBatchCreateModal()
    {
        $this->batchDate = today()->format('Y-m-d');
        $this->batchLocation = auth()->user()->pharm_location_id;
        $this->batchTypes = ['OPD'];
        $this->showBatchCreateModal = true;
    }

    /**
     * Preview prescriptions that will be queued
     */
    #[Locked]
    public function previewBatchCreate()
    {
        try {
            $prescriptions = $this->getPrescriptionsForQueue(
                $this->batchDate,
                $this->batchLocation,
                $this->batchTypes
            );

            $this->dispatch('show-preview', [
                'count' => $prescriptions->count(),
                'prescriptions' => $prescriptions->take(10)->toArray()
            ]);
        } catch (\Exception $e) {
            $this->error('Error previewing prescriptions: ' . $e->getMessage());
        }
    }

    /**
     * Execute batch queue creation
     */
    #[Locked]
    public function executeBatchCreate()
    {
        try {
            DB::beginTransaction();

            $prescriptions = $this->getPrescriptionsForQueue(
                $this->batchDate,
                $this->batchLocation,
                $this->batchTypes
            );

            if ($prescriptions->isEmpty()) {
                $this->warning('No prescriptions found to queue.');
                return;
            }

            $stats = [
                'created' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];

            foreach ($prescriptions as $prescription) {
                // Check if queue already exists
                $existingQueue = PrescriptionQueue::where('prescription_id', $prescription->prescription_id)
                    ->whereIn('queue_status', ['waiting', 'preparing', 'ready'])
                    ->first();

                if ($existingQueue) {
                    $stats['skipped']++;
                    continue;
                }

                // Create queue
                $result = $this->queueService->createQueue([
                    'prescription_id' => $prescription->prescription_id,
                    'enccode' => $prescription->enccode,
                    'hpercode' => $prescription->hpercode,
                    'location_code' => $prescription->location_code,
                    'priority' => $prescription->priority,
                    'queue_prefix' => $prescription->queue_prefix,
                    'created_by' => $prescription->created_by,
                    'created_from' => $prescription->created_from,
                ]);

                if ($result['success']) {
                    $stats['created']++;
                } else {
                    $stats['errors']++;
                }
            }

            DB::commit();

            $this->showBatchCreateModal = false;

            $message = "Batch creation completed: {$stats['created']} created, {$stats['skipped']} skipped";
            if ($stats['errors'] > 0) {
                $message .= ", {$stats['errors']} errors";
            }

            $this->success($message);
            $this->dispatch('refresh-queues');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error creating queues: ' . $e->getMessage());
        }
    }

    /**
     * Get prescriptions for queue creation
     */
    private function getPrescriptionsForQueue($date, $locationCode, $encounterTypes)
    {
        $encounterTypesStr = "'" . implode("','", $encounterTypes) . "'";

        // Use BETWEEN for precise date filtering (single day)
        $dateStart = $date . ' 00:00:00';
        $dateEnd = $date . ' 23:59:59';

        $query = "
            SELECT
                presc.id AS prescription_id,
                enc.enccode,
                enc.hpercode,
                '{$locationCode}' AS location_code,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM webapp.dbo.prescription_data pd
                        WHERE pd.presc_id = presc.id
                          AND pd.priority = 'U'
                          AND pd.stat = 'A'
                    )
                    THEN 'stat'
                    ELSE 'normal'
                END AS priority,
                enc.toecode AS queue_prefix,
                presc.empid AS created_by,
                opd.tscode AS created_from,
                pat.patlast + ', ' + pat.patfirst AS patient_name,
                enc.encdate,
                presc.created_at AS prescription_time
            FROM hospital.dbo.henctr enc
                INNER JOIN hospital.dbo.hopdlog opd ON enc.enccode = opd.enccode
                INNER JOIN webapp.dbo.prescription presc ON enc.enccode = presc.enccode
                INNER JOIN hospital.dbo.hperson pat ON enc.hpercode = pat.hpercode
            WHERE
                enc.encdate BETWEEN '{$dateStart}' AND '{$dateEnd}'
                AND enc.toecode IN ({$encounterTypesStr})
                AND EXISTS (
                    SELECT 1
                    FROM webapp.dbo.prescription_data pd
                    WHERE pd.presc_id = presc.id
                      AND pd.stat = 'A'
                )
            ORDER BY
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM webapp.dbo.prescription_data pd
                        WHERE pd.presc_id = presc.id
                          AND pd.priority = 'U'
                          AND pd.stat = 'A'
                    )
                    THEN 1
                    ELSE 2
                END,
                enc.encdate DESC,
                presc.created_at DESC
        ";

        return collect(DB::select($query));
    }

    /**
     * View queue details
     */
    #[Locked]
    public function viewQueue($queueId)
    {
        $this->selectedQueue = PrescriptionQueue::with([
            'patient',
            'prescription',
            'preparer',
            'dispenser',
            'logs.changer'
        ])->findOrFail($queueId);

        // Get prescription items using raw query to avoid Compoships composite key issue
        if ($this->selectedQueue->prescription) {
            $prescriptionItems = DB::connection('webapp')->select("
                SELECT
                    pd.id,
                    pd.qty,
                    pd.stat,
                    pd.order_type,
                    pd.frequency,
                    pd.duration,
                    pd.remark,
                    pd.addtl_remarks,
                    pd.tkehome,
                    dm.drug_concat
                FROM webapp.dbo.prescription_data pd
                INNER JOIN hospital.dbo.hdmhdr dm
                    ON pd.dmdcomb = dm.dmdcomb
                    AND pd.dmdctr = dm.dmdctr
                WHERE pd.presc_id = ?
                  AND pd.stat = 'A'
                ORDER BY pd.created_at DESC
            ", [$this->selectedQueue->prescription_id]);

            $this->selectedQueue->prescription_items = collect($prescriptionItems);
        }

        $this->showDetailsModal = true;
    }

    /**
     * Open status update modal
     */
    public function openStatusModal($queueId, $status)
    {
        $this->selectedQueueId = $queueId;
        $this->newStatus = $status;
        $this->statusRemarks = '';
        $this->showStatusModal = true;
    }

    /**
     * Update queue status
     */
    #[Locked]
    public function updateStatus()
    {
        $this->validate([
            'newStatus' => 'required|in:waiting,preparing,ready,dispensed,cancelled',
        ]);

        $result = $this->queueService->updateQueueStatus(
            $this->selectedQueueId,
            $this->newStatus,
            auth()->user()->employeeid,
            $this->statusRemarks
        );

        if ($result['success']) {
            $this->success($result['message']);
            $this->showStatusModal = false;
            $this->dispatch('refresh-queues');
            $this->dispatch('refresh-display'); // Update display screen
        } else {
            $this->error($result['message']);
        }
    }

    /**
     * Call queue (mark as ready)
     */
    #[Locked]
    public function callQueue($queueId)
    {
        $result = $this->queueService->updateQueueStatus(
            $queueId,
            'ready',
            auth()->user()->employeeid,
            'Called for pickup'
        );

        if ($result['success']) {
            $this->success('Queue called successfully');
            $this->dispatch('refresh-queues');
            $this->dispatch('refresh-display');
        } else {
            $this->error($result['message']);
        }
    }

    /**
     * Cancel queue
     */
    #[On('cancel-queue')]
    #[Locked]
    public function cancelQueue($queueId, $reason = null)
    {
        $result = $this->queueService->updateQueueStatus(
            $queueId,
            'cancelled',
            auth()->user()->employeeid,
            $reason ?? 'Cancelled by user'
        );

        if ($result['success']) {
            $this->success('Queue cancelled');
            $this->dispatch('refresh-queues');
        } else {
            $this->error($result['message']);
        }
    }

    /**
     * Refresh queues
     */
    #[On('refresh-queues')]
    public function refresh()
    {
        // Component will auto-refresh
    }

    #[Layout('layouts.queue-controller')]
    public function render()
    {
        return view('livewire.pharmacy.prescriptions.prescription-queue-management-tablet', [
            'queues' => $this->queues,
            'stats' => $this->stats,
            'locations' => $this->locations,
        ]);
    }
}
