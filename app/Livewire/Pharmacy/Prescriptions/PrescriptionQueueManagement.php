<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use Carbon\Carbon;
use Mary\Traits\Toast;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueLog;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueDisplaySetting;

class PrescriptionQueueManagement extends Component
{
    use Toast;

    // Filters
    public $locationFilter;
    public $statusFilter = 'active';
    public $priorityFilter = 'all';
    public $searchQueue = '';
    public $dateFilter;

    // Selected queue for actions
    public $selectedQueueId = null;
    public $selectedQueue = null;

    // Modals
    public $showDetailsModal = false;
    public $showCancelModal = false;
    public $showNotesModal = false;
    public $showDispensingModal = false;

    // Cancel form
    public $cancellationReason = '';

    // Notes form
    public $queueNotes = '';

    // Dispensing data
    public $prescribedItems = [];
    public $dispensingEnccode = null;

    // Stats
    public $stats = [];

    // Auto-refresh
    public $autoRefresh = true;

    public function mount()
    {
        $this->locationFilter = auth()->user()->pharm_location_id;
        $this->dateFilter = today()->format('Y-m-d');
        $this->loadStats();
    }

    #[On('refresh-queue')]
    public function refreshQueue()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        $date = Carbon::parse($this->dateFilter);

        $this->stats = [
            'total_today' => PrescriptionQueue::forLocation($this->locationFilter)
                ->whereDate('queued_at', $date)
                ->count(),
            'waiting' => PrescriptionQueue::forLocation($this->locationFilter)
                ->waiting()
                ->whereDate('queued_at', $date)
                ->count(),
            'preparing' => PrescriptionQueue::forLocation($this->locationFilter)
                ->preparing()
                ->whereDate('queued_at', $date)
                ->count(),
            'ready' => PrescriptionQueue::forLocation($this->locationFilter)
                ->ready()
                ->whereDate('queued_at', $date)
                ->count(),
            'dispensed' => PrescriptionQueue::forLocation($this->locationFilter)
                ->where('queue_status', 'dispensed')
                ->whereDate('queued_at', $date)
                ->count(),
            'cancelled' => PrescriptionQueue::forLocation($this->locationFilter)
                ->where('queue_status', 'cancelled')
                ->whereDate('queued_at', $date)
                ->count(),
            'avg_wait_time' => $this->getAverageWaitTime(),
        ];
    }

    private function getAverageWaitTime()
    {
        $date = Carbon::parse($this->dateFilter);

        $dispensed = PrescriptionQueue::forLocation($this->locationFilter)
            ->where('queue_status', 'dispensed')
            ->whereDate('queued_at', $date)
            ->whereNotNull('dispensed_at')
            ->get();

        if ($dispensed->isEmpty()) return 0;

        $totalMinutes = $dispensed->sum(function ($queue) {
            return $queue->getTotalTimeMinutes();
        });

        return round($totalMinutes / $dispensed->count());
    }

    public function getQueuesProperty()
    {
        $date = Carbon::parse($this->dateFilter);

        $query = PrescriptionQueue::query()
            ->with(['patient', 'prescription', 'preparer', 'dispenser'])
            ->forLocation($this->locationFilter)
            ->whereDate('queued_at', $date);

        if ($this->statusFilter === 'active') {
            $query->active();
        } elseif ($this->statusFilter !== 'all') {
            $query->where('queue_status', $this->statusFilter);
        }

        if ($this->priorityFilter !== 'all') {
            $query->priority($this->priorityFilter);
        }

        if ($this->searchQueue) {
            $query->where(function ($q) {
                $q->where('queue_number', 'like', '%' . $this->searchQueue . '%')
                    ->orWhere('hpercode', 'like', '%' . $this->searchQueue . '%')
                    ->orWhereHas('patient', function ($pq) {
                        $pq->where('patlast', 'like', '%' . $this->searchQueue . '%')
                            ->orWhere('patfirst', 'like', '%' . $this->searchQueue . '%');
                    });
            });
        }

        return $query->orderByPriority()->get();
    }

    public function viewDetails($queueId)
    {
        $this->selectedQueueId = $queueId;
        $this->selectedQueue = PrescriptionQueue::with([
            'patient',
            'prescription',
            'preparer',
            'dispenser',
            'logs.changer'
        ])->find($queueId);

        // Load prescribed items using raw query for performance
        if ($this->selectedQueue && $this->selectedQueue->prescription_id) {
            $this->loadPrescribedItems($this->selectedQueue->prescription_id);
        }

        $this->showDetailsModal = true;
    }

    private function loadPrescribedItems($prescriptionId)
    {
        try {
            $items = collect(DB::connection('webapp')->select("
                SELECT
                    pd.id,
                    pd.dmdcomb,
                    pd.dmdctr,
                    pd.qty,
                    pd.order_type,
                    pd.stat,
                    pd.remark,
                    dm.drug_concat,
                    COALESCE(pdi.total_issued, 0) as total_issued,
                    (pd.qty - COALESCE(pdi.total_issued, 0)) as remaining_qty
                FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
                INNER JOIN hospital.dbo.hdmhdr dm WITH (NOLOCK)
                    ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
                LEFT JOIN (
                    SELECT presc_data_id, SUM(qtyissued) as total_issued
                    FROM webapp.dbo.prescription_data_issued WITH (NOLOCK)
                    GROUP BY presc_data_id
                ) pdi ON pd.id = pdi.presc_data_id
                WHERE pd.prescription_id = ?
                    AND pd.stat = 'A'
                ORDER BY pd.created_at ASC
            ", [$prescriptionId]));

            $this->prescribedItems = $items->map(function ($item) {
                $parts = explode('_,', $item->drug_concat ?? '');
                return [
                    'id' => $item->id,
                    'dmdcomb' => $item->dmdcomb,
                    'dmdctr' => $item->dmdctr,
                    'generic' => $parts[0] ?? 'N/A',
                    'brand' => $parts[1] ?? '',
                    'qty_ordered' => $item->qty,
                    'qty_issued' => $item->total_issued,
                    'qty_remaining' => $item->remaining_qty,
                    'order_type' => $item->order_type,
                    'remark' => $item->remark,
                    'status' => $item->stat,
                    'is_fully_issued' => $item->remaining_qty <= 0,
                ];
            })->toArray();
        } catch (\Exception $e) {
            $this->prescribedItems = [];
            \Log::error('Error loading prescribed items: ' . $e->getMessage());
        }
    }

    public function openDispensingWindow($queueId)
    {
        $this->selectedQueueId = $queueId;
        $this->selectedQueue = PrescriptionQueue::with(['patient', 'prescription'])->find($queueId);

        if ($this->selectedQueue && $this->selectedQueue->enccode) {
            $this->dispensingEnccode = $this->selectedQueue->enccode;

            // Load prescribed items
            if ($this->selectedQueue->prescription_id) {
                $this->loadPrescribedItems($this->selectedQueue->prescription_id);
            }

            $this->showDispensingModal = true;
        } else {
            $this->error('Encounter code not found');
        }
    }

    public function navigateToDispensing()
    {
        if ($this->dispensingEnccode) {
            $encrypted = \Crypt::encrypt(str_replace(' ', '--', $this->dispensingEnccode));
            return redirect()->route('dispensing.view.enctr', ['enccode' => $encrypted]);
        }
    }

    public function callNext()
    {
        $nextQueue = PrescriptionQueue::forLocation($this->locationFilter)
            ->waiting()
            ->whereDate('queued_at', today())
            ->orderByPriority()
            ->first();

        if (!$nextQueue) {
            $this->warning('No waiting prescriptions in queue');
            return;
        }

        $this->startPreparing($nextQueue->id);
    }

    public function startPreparing($queueId)
    {
        DB::connection('webapp')->beginTransaction();
        try {
            $queue = PrescriptionQueue::findOrFail($queueId);

            if (!$queue->isWaiting()) {
                $this->warning('Queue is not in waiting status');
                return;
            }

            $oldStatus = $queue->queue_status;
            $queue->update([
                'queue_status' => 'preparing',
                'preparing_at' => now(),
                'prepared_by' => auth()->user()->employeeid,
            ]);

            PrescriptionQueueLog::create([
                'queue_id' => $queue->id,
                'status_from' => $oldStatus,
                'status_to' => 'preparing',
                'changed_by' => auth()->user()->employeeid,
            ]);

            DB::connection('webapp')->commit();
            $this->success('Started preparing: ' . $queue->queue_number);
            $this->loadStats();
            $this->dispatch('queue-status-changed', queueId: $queueId);
        } catch (\Exception $e) {
            DB::connection('webapp')->rollBack();
            $this->error('Error: ' . $e->getMessage());
        }
    }

    public function markReady($queueId)
    {
        DB::connection('webapp')->beginTransaction();
        try {
            $queue = PrescriptionQueue::findOrFail($queueId);

            if (!$queue->isPreparing()) {
                $this->warning('Queue is not in preparing status');
                return;
            }

            $oldStatus = $queue->queue_status;
            $queue->update([
                'queue_status' => 'ready',
                'ready_at' => now(),
            ]);

            PrescriptionQueueLog::create([
                'queue_id' => $queue->id,
                'status_from' => $oldStatus,
                'status_to' => 'ready',
                'changed_by' => auth()->user()->employeeid,
            ]);

            DB::connection('webapp')->commit();
            $this->success('Prescription ready for pickup: ' . $queue->queue_number);
            $this->loadStats();
            $this->dispatch('queue-status-changed', queueId: $queueId);
            $this->dispatch('play-notification-sound');
        } catch (\Exception $e) {
            DB::connection('webapp')->rollBack();
            $this->error('Error: ' . $e->getMessage());
        }
    }

    public function markDispensed($queueId)
    {
        DB::connection('webapp')->beginTransaction();
        try {
            $queue = PrescriptionQueue::findOrFail($queueId);

            if (!$queue->isReady()) {
                $this->warning('Queue is not ready for dispensing');
                return;
            }

            $oldStatus = $queue->queue_status;
            $queue->update([
                'queue_status' => 'dispensed',
                'dispensed_at' => now(),
                'dispensed_by' => auth()->user()->employeeid,
            ]);

            PrescriptionQueueLog::create([
                'queue_id' => $queue->id,
                'status_from' => $oldStatus,
                'status_to' => 'dispensed',
                'changed_by' => auth()->user()->employeeid,
            ]);

            DB::connection('webapp')->commit();
            $this->success('Prescription dispensed: ' . $queue->queue_number);
            $this->loadStats();
            $this->dispatch('queue-status-changed', queueId: $queueId);
        } catch (\Exception $e) {
            DB::connection('webapp')->rollBack();
            $this->error('Error: ' . $e->getMessage());
        }
    }

    public function openCancelModal($queueId)
    {
        $this->selectedQueueId = $queueId;
        $this->selectedQueue = PrescriptionQueue::find($queueId);
        $this->cancellationReason = '';
        $this->showCancelModal = true;
    }

    public function cancelQueue()
    {
        $this->validate([
            'cancellationReason' => 'required|string|min:10',
        ]);

        DB::connection('webapp')->beginTransaction();
        try {
            $queue = PrescriptionQueue::findOrFail($this->selectedQueueId);

            if ($queue->isDispensed() || $queue->isCancelled()) {
                $this->warning('Cannot cancel this queue');
                return;
            }

            $oldStatus = $queue->queue_status;
            $queue->update([
                'queue_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => auth()->user()->employeeid,
                'cancellation_reason' => $this->cancellationReason,
            ]);

            PrescriptionQueueLog::create([
                'queue_id' => $queue->id,
                'status_from' => $oldStatus,
                'status_to' => 'cancelled',
                'changed_by' => auth()->user()->employeeid,
                'remarks' => $this->cancellationReason,
            ]);

            DB::connection('webapp')->commit();
            $this->success('Queue cancelled: ' . $queue->queue_number);
            $this->showCancelModal = false;
            $this->loadStats();
            $this->dispatch('queue-status-changed', queueId: $this->selectedQueueId);
        } catch (\Exception $e) {
            DB::connection('webapp')->rollBack();
            $this->error('Error: ' . $e->getMessage());
        }
    }

    public function openNotesModal($queueId)
    {
        $this->selectedQueueId = $queueId;
        $queue = PrescriptionQueue::find($queueId);
        $this->queueNotes = $queue->remarks ?? '';
        $this->showNotesModal = true;
    }

    public function saveNotes()
    {
        try {
            $queue = PrescriptionQueue::findOrFail($this->selectedQueueId);
            $queue->update(['remarks' => $this->queueNotes]);

            $this->success('Notes saved');
            $this->showNotesModal = false;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.pharmacy.prescriptions.prescription-queue-management', [
            'queues' => $this->queues,
        ]);
    }
}
