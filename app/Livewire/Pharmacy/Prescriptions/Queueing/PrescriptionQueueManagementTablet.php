<?php

namespace App\Livewire\Pharmacy\Prescriptions\Queueing;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use App\Services\Pharmacy\PrescriptionQueueService;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\PharmLocation;

#[Layout('layouts.queue-controller')]
class PrescriptionQueueManagementTablet extends Component
{
    use WithPagination, Toast;

    protected $queueService;

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $priorityFilter = '';
    public $dateFilter;
    public $perPage = 12;

    // Window System
    public $selectedWindow = null;
    public $maxWindows = 4; // Configurable: 1-8 windows

    // Modals
    public $showBatchCreateModal = false;
    public $showDetailsModal = false;
    public $showStatusModal = false;

    // Batch Create
    public $batchDate;
    public $batchTypes = [];
    public $availableTypes = [
        'OPD' => 'Out-Patient',
        'ER' => 'Emergency Room',
        'ERADM' => 'ER to Admission',
        'ADM' => 'Admission',
        'OPDAD' => 'OPD to Admission',
    ];

    // Selected Queue
    public $selectedQueueId = null;
    public $selectedQueue = null;

    // Status Update
    public $newStatus = null;
    public $statusRemarks = '';

    public function boot(PrescriptionQueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    public function mount()
    {
        $this->dateFilter = today()->format('Y-m-d');
        $this->batchDate = today()->format('Y-m-d');

        // Auto-assign window if not set
        if (!session('queue_window')) {
            $this->autoAssignWindow();
        } else {
            $this->selectedWindow = session('queue_window');
        }
    }

    protected function autoAssignWindow()
    {
        // Find the window with the least active queues
        $windowLoads = [];
        for ($i = 1; $i <= $this->maxWindows; $i++) {
            $count = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
                ->whereIn('queue_status', ['preparing', 'ready'])
                ->where('assigned_window', $i)
                ->count();
            $windowLoads[$i] = $count;
        }

        asort($windowLoads);
        $this->selectedWindow = array_key_first($windowLoads);
        session(['queue_window' => $this->selectedWindow]);
    }

    public function updatedSelectedWindow($value)
    {
        session(['queue_window' => $value]);
        $this->resetPage();
    }

    public function getQueuesProperty()
    {
        $query = PrescriptionQueue::query()
            ->with(['patient', 'prescription', 'logs'])
            ->where('location_code', auth()->user()->pharm_location_id)
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('queue_number', 'like', '%' . $this->search . '%')
                        ->orWhere('hpercode', 'like', '%' . $this->search . '%')
                        ->orWhere('enccode', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('queue_status', $this->statusFilter))
            ->when(!$this->statusFilter, fn($q) => $q->whereNot('queue_status', 'dispensed'))
            ->when($this->priorityFilter, fn($q) => $q->where('priority', $this->priorityFilter))
            ->when($this->dateFilter, function ($q) {
                $q->whereDate('queued_at', $this->dateFilter);
            })
            ->when($this->selectedWindow, function ($q) {
                // Only show unassigned or this window's queues
                $q->where(function ($query) {
                    $query->where('assigned_window', $this->selectedWindow)
                        ->orWhereNull('assigned_window');
                });
            })
            ->orderByRaw("
                CASE
                    WHEN priority = 'stat' THEN 1
                    WHEN priority = 'urgent' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('queued_at', 'asc')
            ->paginate($this->perPage);

        return $query;
    }

    public function getStatsProperty()
    {
        $baseQuery = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->whereDate('queued_at', $this->dateFilter ?: today());

        if ($this->selectedWindow) {
            $baseQuery->where(function ($q) {
                $q->where('assigned_window', $this->selectedWindow)
                    ->orWhereNull('assigned_window');
            });
        }

        return [
            'total' => $baseQuery->count(),
            'waiting' => (clone $baseQuery)->where('queue_status', 'waiting')->count(),
            'preparing' => (clone $baseQuery)->where('queue_status', 'preparing')->count(),
            'ready' => (clone $baseQuery)->where('queue_status', 'ready')->count(),
            'dispensed' => (clone $baseQuery)->where('queue_status', 'dispensed')->count(),
            'cancelled' => (clone $baseQuery)->where('queue_status', 'cancelled')->count(),
            'avg_waiting_time' => (clone $baseQuery)
                ->where('queue_status', '!=', 'waiting')
                ->whereNotNull('preparing_at')
                ->selectRaw('AVG(DATEDIFF(MINUTE, queued_at, preparing_at)) as avg_time')
                ->value('avg_time') ?? 0,
            'avg_preparing_time' => (clone $baseQuery)
                ->where('queue_status', '!=', 'preparing')
                ->whereNotNull('ready_at')
                ->selectRaw('AVG(DATEDIFF(MINUTE, preparing_at, ready_at)) as avg_time')
                ->value('avg_time') ?? 0,
            'avg_ready_time' => (clone $baseQuery)
                ->where('queue_status', 'dispensed')
                ->whereNotNull('dispensed_at')
                ->selectRaw('AVG(DATEDIFF(MINUTE, ready_at, dispensed_at)) as avg_time')
                ->value('avg_time') ?? 0,
            'avg_total_time' => (clone $baseQuery)
                ->where('queue_status', 'dispensed')
                ->whereNotNull('dispensed_at')
                ->selectRaw('AVG(DATEDIFF(MINUTE, queued_at, dispensed_at)) as avg_time')
                ->value('avg_time') ?? 0,
        ];
    }

    public function getLocationsProperty()
    {
        return PharmLocation::orderBy('description')->get();
    }

    #[Locked]
    public function callNextQueue()
    {
        if (!$this->selectedWindow) {
            $this->error('Please select a window first');
            return;
        }

        // Get the next waiting queue (prioritized)
        $nextQueue = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->where('queue_status', 'waiting')
            ->whereNull('assigned_window')
            ->orderByRaw("
                CASE
                    WHEN priority = 'stat' THEN 1
                    WHEN priority = 'urgent' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('queued_at', 'asc')
            ->first();

        if (!$nextQueue) {
            $this->warning('No waiting queues available');
            return;
        }

        // Assign to window and start preparing
        $result = $this->queueService->updateQueueStatus(
            $nextQueue->id,
            'preparing',
            auth()->user()->employeeid,
            "Called to Window {$this->selectedWindow}"
        );

        if ($result['success']) {
            // Update window assignment
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $nextQueue->id)
                ->update([
                    'assigned_window' => $this->selectedWindow,
                    'prepared_by' => auth()->user()->employeeid,
                ]);

            $this->success("Queue {$nextQueue->queue_number} called to Window {$this->selectedWindow}");
            $this->dispatch('queue-called', queueNumber: $nextQueue->queue_number);
        } else {
            $this->error($result['message']);
        }
    }

    #[Locked]
    public function viewQueue($queueId)
    {
        $this->selectedQueueId = $queueId;
        $this->selectedQueue = PrescriptionQueue::with([
            'patient',
            'prescription',
            'logs.changer'
        ])->find($queueId);

        if ($this->selectedQueue && $this->selectedQueue->prescription_id) {
            $prescriptionItems = collect(DB::connection('webapp')->select("
                SELECT
                    pd.id,
                    pd.dmdcomb,
                    pd.dmdctr,
                    pd.qty,
                    pd.order_type,
                    pd.remark,
                    pd.addtl_remarks,
                    pd.tkehome,
                    pd.frequency,
                    pd.duration,
                    dm.drug_concat
                FROM prescription_data pd
                INNER JOIN hospital2.dbo.hdmhdr dm ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
                WHERE pd.presc_id = ?
                    AND pd.stat = 'A'
                ORDER BY pd.created_at ASC
            ", [$this->selectedQueue->prescription_id]));

            $this->selectedQueue->prescription_items = $prescriptionItems;
        }

        $this->showDetailsModal = true;
    }

    #[Locked]
    public function openStatusModal($queueId, $status)
    {
        $this->selectedQueueId = $queueId;
        $this->newStatus = $status;
        $this->statusRemarks = '';
        $this->showStatusModal = true;
    }

    #[Locked]
    public function updateStatus()
    {
        if (!$this->selectedQueueId || !$this->newStatus) {
            $this->error('Invalid status update');
            return;
        }

        $queue = PrescriptionQueue::find($this->selectedQueueId);

        // Auto-assign window if starting to prepare
        if ($this->newStatus === 'preparing' && !$queue->assigned_window && $this->selectedWindow) {
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $this->selectedQueueId)
                ->update(['assigned_window' => $this->selectedWindow]);
        }

        $result = $this->queueService->updateQueueStatus(
            $this->selectedQueueId,
            $this->newStatus,
            auth()->user()->employeeid,
            $this->statusRemarks
        );

        if ($result['success']) {
            $this->success($result['message']);
            $this->showStatusModal = false;
            $this->resetStatusForm();
        } else {
            $this->error($result['message']);
        }
    }

    #[Locked]
    public function callQueue($queueId)
    {
        $queue = PrescriptionQueue::find($queueId);

        if (!$queue || !$queue->isPreparing()) {
            $this->error('Queue must be in preparing status');
            return;
        }

        $result = $this->queueService->updateQueueStatus(
            $queueId,
            'ready',
            auth()->user()->employeeid,
            'Queue called for pickup'
        );

        if ($result['success']) {
            $this->success("Queue {$queue->queue_number} is ready for pickup!");
            $this->dispatch('queue-ready', queueNumber: $queue->queue_number);
        } else {
            $this->error($result['message']);
        }
    }

    #[On('cancel-queue')]
    #[Locked]
    public function cancelQueue($queueId)
    {
        $result = $this->queueService->updateQueueStatus(
            $queueId,
            'cancelled',
            auth()->user()->employeeid,
            'Cancelled by pharmacist'
        );

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }
    }

    public function openBatchCreateModal()
    {
        $this->showBatchCreateModal = true;
    }

    public function previewBatchCreate()
    {
        if (empty($this->batchTypes)) {
            $this->warning('Please select at least one encounter type');
            return;
        }

        $count = $this->queueService->countPendingPrescriptions(
            auth()->user()->pharm_location_id,
            $this->batchDate,
            $this->batchTypes
        );

        $this->info("Found {$count} prescription(s) ready to be queued");
    }

    #[Locked]
    public function executeBatchCreate()
    {
        if (empty($this->batchTypes)) {
            $this->warning('Please select at least one encounter type');
            return;
        }

        $result = $this->queueService->batchCreateQueues(
            auth()->user()->pharm_location_id,
            $this->batchDate,
            $this->batchTypes,
            auth()->user()->employeeid
        );

        if ($result['success']) {
            $this->success($result['message']);
            $this->showBatchCreateModal = false;
            $this->resetBatchForm();
        } else {
            $this->error($result['message']);
        }
    }

    private function resetStatusForm()
    {
        $this->selectedQueueId = null;
        $this->newStatus = null;
        $this->statusRemarks = '';
    }

    private function resetBatchForm()
    {
        $this->batchDate = today()->format('Y-m-d');
        $this->batchTypes = [];
    }

    public function render()
    {
        return view('livewire.pharmacy.prescriptions.queueing.prescription-queue-management-tablet', [
            'queues' => $this->queues,
            'stats' => $this->stats,
            'locations' => $this->locations,
        ]);
    }
}
