<?php

namespace App\Livewire\Pharmacy\Prescriptions\Queueing;

use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueDisplaySetting;
use App\Models\PharmLocation;
use App\Services\Pharmacy\PrescriptionQueueService;
use Illuminate\Support\Facades\Crypt;
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

    // Store charging queues
    public $nextChargingQueue = null;
    public $otherChargingQueues = [];

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


    // Filters
    public $dateFilter;
    public $perPage = 50;

    // Modal
    public $showDetailsModal = false;
    public $selectedQueueId = null;
    public $selectedQueue = null;

    // Print Modal
    public $showPrintModal = false;
    public $printQueueId = null;
    public $printQueue = null;
    public $printItems = [];
    public $selectedItems = [];


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

    public function updatedDateFilter()
    {
        $this->loadCurrentQueue();
        $this->resetPage();
    }

    public function handleQueueStatusChanged($event)
    {
        if ($event['assigned_window'] == $this->selectedWindow) {
            $this->loadCurrentQueue();
        }
    }

    public function handleQueueCalled($event)
    {
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

            Log::error('Batch queue creation failed', [
                'batch_date'     => $this->batchDate,
                'batch_location' => $this->batchLocation,
                'batch_types'    => $this->batchTypes,
                'exception'      => $e,
            ]);

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
            FROM hospital2.dbo.henctr enc
                INNER JOIN hospital2.dbo.hopdlog opd ON enc.enccode = opd.enccode
                INNER JOIN webapp.dbo.prescription presc ON enc.enccode = presc.enccode
                INNER JOIN hospital2.dbo.hperson pat ON enc.hpercode = pat.hpercode
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
     * Get available locations
     */
    public function getLocationsProperty()
    {
        return PharmLocation::orderBy('description')->get();
    }

    protected function loadCurrentQueue()
    {
        // Get active queue (preparing/ready, not charging)
        $this->currentQueue = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->where(function ($q) {
                if ($this->selectedQueueId) {
                    $q->where('id', $this->selectedQueueId);
                }
            })
            ->where('assigned_window', $this->selectedWindow)
            ->whereDate('queued_at', $this->dateFilter)
            ->whereIn('queue_status', ['preparing', 'ready'])
            ->with(['patient'])
            ->orderByRaw("
                CASE
                    WHEN queue_status = 'ready' THEN 1
                    ELSE 2
                END
            ")
            ->orderBy('queued_at', 'asc')
            ->first();

        // Get all charging queues for this window
        $chargingQueues = PrescriptionQueue::where('location_code', auth()->user()->pharm_location_id)
            ->where('assigned_window', $this->selectedWindow)
            ->where('queue_status', 'charging')
            ->whereDate('queued_at', $this->dateFilter)
            ->with(['patient'])
            ->orderBy('charging_at', 'asc')
            ->get();

        // Split into next and others
        $this->nextChargingQueue = $chargingQueues->first();
        $this->otherChargingQueues = $chargingQueues->skip(1)->values()->all();

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

        DB::connection('webapp')->table('prescription_queues')
            ->where('id', $this->currentQueue->id)
            ->update(['called_at' => now()]);

        $result = $this->queueService->logQueueAction(
            $this->currentQueue->id,
            auth()->user()->employeeid,
            'Patient called - waiting for arrival'
        );

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

        $settings = PrescriptionQueueDisplaySetting::getForLocation(
            auth()->user()->pharm_location_id
        );

        if ($settings->require_cashier) {
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
                $this->loadCurrentQueue();
            } else {
                $this->error($result['message']);
            }
        } else {
            $result = $this->queueService->updateQueueStatus(
                $this->currentQueue->id,
                'ready',
                auth()->user()->employeeid,
                'Ready for dispensing (no cashier required)'
            );

            if ($result['success']) {
                DB::connection('webapp')->table('prescription_queues')
                    ->where('id', $this->currentQueue->id)
                    ->update(['ready_at' => now()]);

                $this->success("Queue {$this->currentQueue->queue_number} is ready for dispensing!");
                $this->loadCurrentQueue();
            } else {
                $this->error($result['message']);
            }
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
            ->whereDate('queued_at', $this->dateFilter)
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
            $this->selectedQueueId = $nextQueue->id;
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

    #[Locked]
    public function openDispensing()
    {
        if (!$this->currentQueue) {
            $this->warning('No queue selected');
            return;
        }

        if (!$this->currentQueue->enccode) {
            $this->error('No encounter linked to this queue');
            return;
        }

        $encrypted = Crypt::encrypt(str_replace(' ', '--', $this->currentQueue->enccode));

        return redirect()->to(
            route('dispensing.view.enctr', ['enccode' => $encrypted]) . '?queue_id=' . $this->currentQueue->id
        );
    }

    public function selectQueue($queueId)
    {
        $queue = PrescriptionQueue::find($queueId);
        $this->selectedQueueId = $queueId;
        if (!$queue || (!$queue->isWaiting() && !$queue->isPreparing())) {
            $this->error('Queue is not available for selection');
            return;
        }

        if ($queue->isWaiting()) {
            $result = $this->queueService->updateQueueStatus(
                $queue->id,
                'preparing',
                auth()->user()->employeeid,
                "Selected to Window {$this->selectedWindow}"
            );

            if ($result['success']) {
                DB::connection('webapp')->table('prescription_queues')
                    ->where('id', $queue->id)
                    ->update([
                        'assigned_window' => $this->selectedWindow,
                        'prepared_by' => auth()->user()->employeeid,
                        'preparing_at' => now(),
                    ]);

                $this->success("Now serving: {$queue->queue_number}");
            } else {
                $this->error($result['message']);
                return;
            }
        } elseif ($queue->isPreparing()) {
            // Reassign a preparing queue to this window
            DB::connection('webapp')->table('prescription_queues')
                ->where('id', $queue->id)
                ->update([
                    'assigned_window' => $this->selectedWindow,
                ]);

            $this->queueService->logQueueAction(
                $queue->id,
                auth()->user()->employeeid,
                "Reassigned to Window {$this->selectedWindow}"
            );

            $this->success("Queue {$queue->queue_number} moved to Window {$this->selectedWindow}");
        }

        $this->loadCurrentQueue();
    }

    public function dispenseAndNext()
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

            // Auto-call the next waiting queue
            $this->loadCurrentQueue();
            if (!$this->currentQueue) {
                $this->nextQueue();
            }
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
                INNER JOIN hospital2.dbo.hdmhdr dm ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
                WHERE pd.presc_id = ? AND pd.stat = 'A'
                ORDER BY pd.created_at ASC
            ", [$this->selectedQueue->prescription_id]));

            $this->selectedQueue->prescription_items = $prescriptionItems;
        }

        $this->showDetailsModal = true;
    }

    public function openPrintModal($queueId)
    {
        $this->printQueueId = $queueId;
        $this->printQueue = PrescriptionQueue::with(['patient', 'prescription'])
            ->find($queueId);

        if ($this->printQueue && $this->printQueue->prescription_id) {
            $prescriptionItems = DB::connection('webapp')->select("
                SELECT
                    pd.id, pd.dmdcomb, pd.dmdctr, pd.qty, pd.order_type,
                    pd.remark, pd.addtl_remarks, pd.tkehome,
                    pd.frequency, pd.duration, dm.drug_concat
                FROM prescription_data pd
                INNER JOIN hospital2.dbo.hdmhdr dm ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
                WHERE pd.presc_id = ? AND pd.stat = 'A'
                ORDER BY pd.created_at ASC
            ", [$this->printQueue->prescription_id]);

            // Store items as array to persist through Livewire updates
            $this->printItems = array_map(function ($item) {
                return (array) $item;
            }, $prescriptionItems);

            // Select all items by default
            $this->selectedItems = array_column($this->printItems, 'id');
        }

        $this->showPrintModal = true;
    }

    public function toggleItemSelection($itemId)
    {
        if (in_array($itemId, $this->selectedItems)) {
            $this->selectedItems = array_values(array_diff($this->selectedItems, [$itemId]));
        } else {
            $this->selectedItems[] = $itemId;
        }
    }

    public function selectAllItems()
    {
        // If all are selected, deselect all; otherwise select all
        if (count($this->selectedItems) === count($this->printItems)) {
            $this->selectedItems = [];
        } else {
            $this->selectedItems = array_column($this->printItems, 'id');
        }
    }

    public function deselectAllItems()
    {
        $this->selectedItems = [];
    }

    public function printPrescription()
    {
        if (empty($this->selectedItems)) {
            $this->warning('Please select at least one item to print');
            return;
        }

        // Store selected items in session for print page
        session([
            'print_queue_id' => $this->printQueueId,
            'print_items' => $this->selectedItems
        ]);

        // Dispatch event to open print window - route should be defined in web.php
        $this->dispatch('open-print-window', url: url("/prescriptions/queue/print/{$this->printQueueId}"));
    }

    public function toggleAvailability()
    {
        $this->isAvailable = !$this->isAvailable;
        $status = $this->isAvailable ? 'available' : 'unavailable';
        $this->info("Window {$this->selectedWindow} is now {$status}");
    }

    public function render()
    {
        return view('livewire.pharmacy.prescriptions.queueing.prescription-queue-controller', [
            'queues' => $this->queues,
            'stats' => $this->stats,
            'locations' => $this->locations,
        ]);
    }
}
