<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueDisplaySetting;
use App\Models\PharmLocation;
use App\Services\Pharmacy\PrescriptionQueueService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Layout('layouts.queue-controller')]
class PrescriptionQueueController extends Component
{
    use WithPagination, Toast;

    protected $queueService;

    // Window System
    public $selectedWindow = 1;
    public $maxWindows = 4;
    public $isAvailable = true;
    public $requireCashier = false;

    // Current Queue
    public $currentQueueId = null;
    public $currentQueue = null;

    // Filters
    public $dateFilter;
    public $perPage = 50;

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
        $settings = PrescriptionQueueDisplaySetting::getForLocation(
            auth()->user()->pharm_location_id
        );

        if ($settings) {
            $this->maxWindows = $settings->pharmacy_windows;
            $this->requireCashier = $settings->require_cashier;
        }
        $settings->require_cashier;

        if (session('queue_window')) {
            $this->selectedWindow = session('queue_window');
        } else {
            $this->autoAssignWindow();
        }

        $this->loadCurrentQueue();
    }

    public function getListeners()
    {
        $locationCode = auth()->user()->pharm_location_id;

        return [
            "echo:pharmacy.location.{$locationCode},.queue.status.changed" => 'handleQueueStatusChanged',
            "echo:pharmacy.location.{$locationCode},.queue.called" => 'handleQueueCalled',
        ];
    }

    public function handleQueueStatusChanged($event)
    {
        // Reload if this window is affected
        if ($event['assigned_window'] == $this->selectedWindow) {
            $this->loadCurrentQueue();
        }
    }

    public function handleQueueCalled($event)
    {
        // Reload queue list
        $this->loadCurrentQueue();
    }

    protected function autoAssignWindow()
    {
        $windowLoads = [];
        for ($i = 1; $i <= $this->maxWindows; $i++) {
            $count = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
                ->whereIn('queue_status', ['preparing', 'charging', 'ready'])
                ->where('assigned_window', $i)
                ->count();
            $windowLoads[$i] = $count;
        }

        asort($windowLoads);
        $this->selectedWindow = array_key_first($windowLoads);
        session(['queue_window' => $this->selectedWindow]);
    }

    protected function loadCurrentQueue()
    {
        $this->currentQueue = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->where('assigned_window', $this->selectedWindow)
            ->whereIn('queue_status', ['preparing', 'charging', 'ready'])
            ->with(['patient'])
            ->orderByRaw("
                CASE
                    WHEN queue_status = 'ready' THEN 1
                    WHEN queue_status = 'charging' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('queued_at', 'asc')
            ->first();

        if ($this->currentQueue) {
            $this->currentQueueId = $this->currentQueue->id;
        }
    }

    public function updatedSelectedWindow($value)
    {
        session(['queue_window' => $value]);
        $this->loadCurrentQueue();
        $this->resetPage();
    }

    public function getQueuesProperty()
    {
        return PrescriptionQueue::query()
            ->with(['patient'])
            ->where('location_code', auth()->user()->pharm_location_id)
            ->whereDate('queued_at', $this->dateFilter)
            ->whereIn('queue_status', ['waiting', 'preparing', 'charging', 'ready'])
            ->where(function ($q) {
                $q->where('assigned_window', $this->selectedWindow)
                    ->orWhereNull('assigned_window');
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
    }

    public function getStatsProperty()
    {
        $baseQuery = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->whereDate('queued_at', $this->dateFilter);

        return [
            'waiting' => (clone $baseQuery)->where('queue_status', 'waiting')->count(),
            'preparing' => (clone $baseQuery)->where('queue_status', 'preparing')->count(),
            'charging' => (clone $baseQuery)->where('queue_status', 'charging')->count(),
            'ready' => (clone $baseQuery)->where('queue_status', 'ready')->count(),
            'dispensed' => (clone $baseQuery)->where('queue_status', 'dispensed')->count(),
            'cancelled' => (clone $baseQuery)->where('queue_status', 'cancelled')->count(),
        ];
    }

    #[Locked]
    public function callQueue()
    {
        if (!$this->currentQueue) {
            $this->warning('No queue is currently being served');
            return;
        }

        if ($this->currentQueue->queue_status !== 'preparing') {
            $this->warning('Can only call queues in preparing status');
            return;
        }

        // Just mark as called, keep in preparing status
        DB::connection('webapp')->table('prescription_queues')
            ->where('id', $this->currentQueue->id)
            ->update(['called_at' => now()]);

        // Log the call
        $result = $this->queueService->logQueueAction(
            $this->currentQueue->id,
            auth()->user()->employeeid,
            'Patient called - waiting for arrival'
        );

        // Broadcast event for real-time display updates
        $queue = PrescriptionQueue::find($this->currentQueue->id);
        \App\Events\Pharmacy\QueueCalled::dispatch($queue, 'pharmacy');

        $this->success("Queue {$this->currentQueue->queue_number} called! Waiting for patient...");
        $this->dispatch('queue-called', queueNumber: $this->currentQueue->queue_number);
        $this->loadCurrentQueue();
    }

    #[Locked]
    public function moveToCharging()
    {
        if (!$this->currentQueue) {
            $this->warning('No queue selected');
            return;
        }

        if (!$this->currentQueue->isPreparing() || !$this->currentQueue->called_at) {
            $this->warning('Must call patient first');
            return;
        }

        // Check if cashier is required for this location
        $settings = PrescriptionQueueDisplaySetting::getForLocation(
            auth()->user()->pharm_location_id
        );

        if ($settings->require_cashier) {
            // Patient arrived, move to charging (cashier workflow)
            $result = $this->queueService->updateQueueStatus(
                $this->currentQueue->id,
                'charging',
                auth()->user()->employeeid,
                'Sent to cashier for payment'
            );

            if ($result['success']) {
                DB::connection('webapp')->table('prescription_queues')
                    ->where('id', $this->currentQueue->id)
                    ->update([
                        'charging_at' => now(),
                        'charged_by' => auth()->user()->employeeid,
                    ]);

                $message = "Patient sent to cashier for payment.";
                if ($settings->cashier_location) {
                    $message .= " Location: {$settings->cashier_location}";
                }

                $this->success($message);

                // Notify cashier to refresh
                $this->dispatch('refresh-cashier-queue');

                $this->loadCurrentQueue();
            } else {
                $this->error($result['message']);
            }
        } else {
            // Bypass cashier - go directly to ready
            $result = $this->queueService->updateQueueStatus(
                $this->currentQueue->id,
                'ready',
                auth()->user()->employeeid,
                'Ready for dispensing (cashier bypassed)'
            );

            if ($result['success']) {
                DB::connection('webapp')->table('prescription_queues')
                    ->where('id', $this->currentQueue->id)
                    ->update(['ready_at' => now()]);

                $this->success("Queue {$this->currentQueue->queue_number} is ready for dispensing!");
                $this->dispatch('ready-for-dispensing', queueNumber: $this->currentQueue->queue_number);
                $this->loadCurrentQueue();
            } else {
                $this->error($result['message']);
            }
        }
    }

    #[Locked]
    public function readyForDispensing()
    {
        if (!$this->currentQueue) {
            $this->warning('No queue selected');
            return;
        }

        if (!$this->currentQueue->isCharging()) {
            $this->warning('Queue must be in charging status');
            return;
        }

        // Patient paid, now ready for dispensing
        $result = $this->queueService->updateQueueStatus(
            $this->currentQueue->id,
            'ready',
            auth()->user()->employeeid,
            'Payment confirmed - ready for dispensing'
        );

        if ($result['success']) {
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $this->currentQueue->id)
                ->update(['ready_at' => now()]);

            $this->success("Queue {$this->currentQueue->queue_number} is ready for dispensing!");
            $this->dispatch('ready-for-dispensing', queueNumber: $this->currentQueue->queue_number);
            $this->loadCurrentQueue();
        } else {
            $this->error($result['message']);
        }
    }

    #[Locked]
    public function dispenseQueue()
    {
        if (!$this->currentQueue) {
            $this->warning('No queue to dispense');
            return;
        }

        if (!$this->currentQueue->isReady()) {
            $this->warning('Queue must be ready for dispensing');
            return;
        }

        $result = $this->queueService->updateQueueStatus(
            $this->currentQueue->id,
            'dispensed',
            auth()->user()->employeeid,
            'Items dispensed to patient'
        );

        if ($result['success']) {
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $this->currentQueue->id)
                ->update([
                    'dispensed_by' => auth()->user()->employeeid,
                    'dispensed_at' => now(),
                ]);

            $this->success("Queue {$this->currentQueue->queue_number} completed!");
            $this->loadCurrentQueue();
        } else {
            $this->error($result['message']);
        }
    }

    #[Locked]
    public function nextQueue()
    {
        $nextQueue = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->where('queue_status', 'waiting')
            ->whereNull('assigned_window')
            ->whereNull('called_at')
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

        $result = $this->queueService->updateQueueStatus(
            $nextQueue->id,
            'preparing',
            auth()->user()->employeeid,
            "Started on Window {$this->selectedWindow}"
        );

        if ($result['success']) {
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $nextQueue->id)
                ->update([
                    'assigned_window' => $this->selectedWindow,
                    'prepared_by' => auth()->user()->employeeid,
                    'preparing_at' => now(),
                ]);

            $this->success("Now serving: {$nextQueue->queue_number}");
            $this->loadCurrentQueue();
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

        DB::connection('webapp')->table('prescription_queues')
            ->where('id', $this->currentQueue->id)
            ->increment('skip_count');

        $result = $this->queueService->updateQueueStatus(
            $this->currentQueue->id,
            'waiting',
            auth()->user()->employeeid,
            'Skipped by pharmacist'
        );

        if ($result['success']) {
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $this->currentQueue->id)
                ->update([
                    'assigned_window' => null,
                    'called_at' => null,
                ]);

            $this->info("Queue {$this->currentQueue->queue_number} skipped");
            $this->loadCurrentQueue();
        } else {
            $this->error($result['message']);
        }
    }

    #[Locked]
    public function cancelCurrentQueue()
    {
        if (!$this->currentQueue) {
            $this->warning('No queue to cancel');
            return;
        }

        $result = $this->queueService->updateQueueStatus(
            $this->currentQueue->id,
            'cancelled',
            auth()->user()->employeeid,
            'Cancelled by pharmacist'
        );

        if ($result['success']) {
            $this->success("Queue {$this->currentQueue->queue_number} cancelled");
            $this->loadCurrentQueue();
        } else {
            $this->error($result['message']);
        }
    }

    #[Locked]
    public function forceCall($queueId)
    {
        $queue = PrescriptionQueue::find($queueId);

        if (!$queue || !$queue->isWaiting()) {
            $this->error('Invalid queue');
            return;
        }

        $result = $this->queueService->updateQueueStatus(
            $queueId,
            'preparing',
            auth()->user()->employeeid,
            "Force called to Window {$this->selectedWindow}"
        );

        if ($result['success']) {
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $queueId)
                ->update([
                    'assigned_window' => $this->selectedWindow,
                    'prepared_by' => auth()->user()->employeeid,
                    'preparing_at' => now(),
                ]);

            $this->success("Queue {$queue->queue_number} force called!");
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

    public function toggleAvailability()
    {
        $this->isAvailable = !$this->isAvailable;
        $status = $this->isAvailable ? 'available' : 'unavailable';
        $this->info("Window {$this->selectedWindow} is now {$status}");
    }

    public function render()
    {
        return view('livewire.pharmacy.prescriptions.prescription-queue-controller', [
            'queues' => $this->queues,
            'stats' => $this->stats,
        ]);
    }
}
