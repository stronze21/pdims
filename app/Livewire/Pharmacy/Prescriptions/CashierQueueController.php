<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use App\Services\Pharmacy\PrescriptionQueueService;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;

#[Layout('layouts.queue-controller')]
class CashierQueueController extends Component
{
    use WithPagination, Toast;

    protected $queueService;

    // Current Queue at Cashier
    public $currentQueueId = null;
    public $currentQueue = null;

    // Filters
    public $dateFilter;
    public $perPage = 20;

    // Modal
    public $showDetailsModal = false;
    public $selectedQueueId = null;
    public $selectedQueue = null;

    public function boot(PrescriptionQueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    public function mount()
    {
        $this->dateFilter = today()->format('Y-m-d');
        $this->loadCurrentQueue();

        // Auto-call first queue if available
        if ($this->currentQueue && !$this->currentQueue->cashier_called_at) {
            $this->callQueue();
        }
    }

    public function getListeners()
    {
        $locationCode = auth()->user()->pharm_location_id;

        return [
            "echo:pharmacy.location.{$locationCode},.queue.status.changed" => 'handleQueueStatusChanged',
            'refresh-cashier-queue' => 'refresh',
        ];
    }

    public function handleQueueStatusChanged($event)
    {
        // Reload if queue moved to charging status
        if ($event['new_status'] === 'charging') {
            $this->loadCurrentQueue();
        }
    }

    #[On('refresh-cashier-queue')]
    public function refresh()
    {
        $this->loadCurrentQueue();
    }

    protected function loadCurrentQueue()
    {
        // Get the oldest charging queue (first in line at cashier)
        $this->currentQueue = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->where('queue_status', 'charging')
            ->whereDate('queued_at', today())
            ->orderBy('charging_at', 'asc')
            ->first();

        if ($this->currentQueue) {
            $this->currentQueueId = $this->currentQueue->id;
        }
    }

    public function getQueuesProperty()
    {
        return PrescriptionQueue::query()
            ->with(['patient'])
            ->where('location_code', auth()->user()->pharm_location_id)
            ->whereDate('queued_at', $this->dateFilter)
            ->where('queue_status', 'charging')
            ->orderByRaw("
                CASE
                    WHEN priority = 'stat' THEN 1
                    WHEN priority = 'urgent' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('charging_at', 'asc')
            ->paginate($this->perPage);
    }

    public function getStatsProperty()
    {
        $baseQuery = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->whereDate('queued_at', $this->dateFilter);

        return [
            'charging' => (clone $baseQuery)->where('queue_status', 'charging')->count(),
            'ready' => (clone $baseQuery)->where('queue_status', 'ready')->count(),
            'dispensed' => (clone $baseQuery)->where('queue_status', 'dispensed')->count(),
        ];
    }

    #[Locked]
    public function callQueue()
    {
        if (!$this->currentQueue) {
            // Get next queue from charging
            $this->loadCurrentQueue();

            if (!$this->currentQueue) {
                $this->warning('No queues waiting for payment');
                return;
            }
        }

        // Mark as being served at cashier
        DB::connection('webapp')->table('prescription_queues')
            ->where('id', $this->currentQueue->id)
            ->update(['cashier_called_at' => now()]);

        // Broadcast event for real-time display
        $queue = PrescriptionQueue::find($this->currentQueue->id);
        \App\Events\Pharmacy\QueueCalled::dispatch($queue, 'cashier');

        $this->success("Queue {$this->currentQueue->queue_number} - Please proceed to payment");
        $this->dispatch('cashier-queue-called', queueNumber: $this->currentQueue->queue_number);
    }

    #[Locked]
    public function nextQueue()
    {
        // Manually move to next queue
        $this->loadCurrentQueue();

        if ($this->currentQueue) {
            $this->callQueue();
        } else {
            $this->warning('No more queues waiting for payment');
        }
    }

    #[Locked]
    public function confirmPayment()
    {
        if (!$this->currentQueue) {
            $this->warning('No queue selected');
            return;
        }

        // Mark as ready for dispensing - queue returns to original window
        $result = $this->queueService->updateQueueStatus(
            $this->currentQueue->id,
            'ready',
            auth()->user()->employeeid,
            "Payment confirmed - return to Window {$this->currentQueue->assigned_window}"
        );

        if ($result['success']) {
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $this->currentQueue->id)
                ->update([
                    'ready_at' => now(),
                    // assigned_window is kept - queue returns to same window
                ]);

            $this->success("Queue {$this->currentQueue->queue_number} - Payment confirmed! Return to Window {$this->currentQueue->assigned_window}.");
            $this->dispatch(
                'payment-confirmed',
                queueNumber: $this->currentQueue->queue_number,
                windowNumber: $this->currentQueue->assigned_window
            );

            // Auto-advance to next queue
            $this->loadCurrentQueue();

            if ($this->currentQueue) {
                $this->callQueue();
            }
        } else {
            $this->error($result['message']);
        }
    }

    #[Locked]
    public function skipQueue()
    {
        if (!$this->currentQueue) {
            $this->warning('No queue to skip');
            return;
        }

        $this->info("Queue {$this->currentQueue->queue_number} skipped");
        $this->loadCurrentQueue();
    }

    #[Locked]
    public function forceConfirmPayment($queueId)
    {
        $queue = PrescriptionQueue::find($queueId);

        if (!$queue) {
            $this->error('Queue not found');
            return;
        }

        $result = $this->queueService->updateQueueStatus(
            $queueId,
            'ready',
            auth()->user()->employeeid,
            "Payment confirmed (force) - return to Window {$queue->assigned_window}"
        );

        if ($result['success']) {
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $queueId)
                ->update([
                    'ready_at' => now(),
                    // assigned_window is kept
                ]);

            $this->success("Payment confirmed! Queue returns to Window {$queue->assigned_window}");
            $this->loadCurrentQueue();
        } else {
            $this->error($result['message']);
        }
    }

    public function viewQueue($queueId)
    {
        $this->selectedQueueId = $queueId;
        $this->selectedQueue = PrescriptionQueue::with(['patient', 'prescription'])
            ->find($queueId);

        if ($this->selectedQueue && $this->selectedQueue->prescription_id) {
            $prescriptionItems = collect(DB::connection('webapp')->select("
                SELECT
                    pd.id, pd.dmdcomb, pd.dmdctr, pd.qty, pd.order_type,
                    pd.remark, pd.addtl_remarks, pd.tkehome,
                    pd.frequency, pd.duration, dm.drug_concat
                FROM prescription_data pd
                INNER JOIN hospital.dbo.hdmhdr dm ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
                WHERE pd.presc_id = ? AND pd.stat = 'A'
                ORDER BY pd.created_at ASC
            ", [$this->selectedQueue->prescription_id]));

            $this->selectedQueue->prescription_items = $prescriptionItems;
        }

        $this->showDetailsModal = true;
    }

    public function render()
    {
        return view('livewire.pharmacy.prescriptions.cashier-queue-controller', [
            'queues' => $this->queues,
            'stats' => $this->stats,
        ]);
    }
}
