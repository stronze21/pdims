<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueDisplaySetting;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueLog;
use App\Models\Record\Prescriptions\Prescription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

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

    // Add these properties to the class
    public $showTestApiModal = false;

    // Test API form data
    public $testPrescriptionId = '';
    public $testEnccode = '';
    public $testHpercode = '';
    public $testLocationCode = '';
    public $testPriority = 'normal';
    public $testQueuePrefix = '';
    public $testRemarks = '';
    public $testCreatedBy = '';
    public $testCreatedFrom = 'Manual Test';

    // Add this method to open the modal
    public function openTestApiModal()
    {
        // Pre-fill with user's location
        $this->testLocationCode = auth()->user()->pharm_location_id;
        $this->testCreatedBy = auth()->user()->employeeid;
        $this->testPriority = 'normal';
        $this->testCreatedFrom = 'Manual Test';

        // Reset other fields
        $this->testPrescriptionId = '';
        $this->testEnccode = '';
        $this->testHpercode = '';
        $this->testQueuePrefix = '';
        $this->testRemarks = '';

        $this->showTestApiModal = true;
    }

    public function testApiConnection()
    {
        try {
            $url = config('app.url') . '/api/prescription-queue/create';

            $response = Http::timeout(10)->get(config('app.url') . '/api/health');

            if ($response->successful()) {
                $this->success('API is reachable');
            } else {
                $this->warning('API returned status: ' . $response->status());
            }

            Log::info('API Connection Test', [
                'url' => $url,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            $this->error('Cannot reach API: ' . $e->getMessage());
            Log::error('API Connection Test Failed', ['error' => $e->getMessage()]);
        }
    }

    public function submitTestApi()
    {
        $this->validate([
            'testPrescriptionId' => 'required|integer',
            'testEnccode' => 'required|string|max:50',
            'testHpercode' => 'required|string|max:50',
            'testLocationCode' => 'required|string|max:20',
            'testPriority' => 'required|in:normal,urgent,stat',
            'testQueuePrefix' => 'nullable|string|max:10',
            'testRemarks' => 'nullable|string',
            'testCreatedBy' => 'nullable|string|max:20',
            'testCreatedFrom' => 'nullable|string|max:50',
        ]);

        try {
            $url = config('app.url') . '/api/prescription-queue/create';

            $payload = [
                'prescription_id' => $this->testPrescriptionId,
                'enccode' => $this->testEnccode,
                'hpercode' => $this->testHpercode,
                'location_code' => $this->testLocationCode,
                'priority' => $this->testPriority,
            ];

            if ($this->testQueuePrefix) {
                $payload['queue_prefix'] = $this->testQueuePrefix;
            }
            if ($this->testRemarks) {
                $payload['remarks'] = $this->testRemarks;
            }
            if ($this->testCreatedBy) {
                $payload['created_by'] = $this->testCreatedBy;
            }
            if ($this->testCreatedFrom) {
                $payload['created_from'] = $this->testCreatedFrom;
            }

            Log::info('Queue API Test Request', [
                'url' => $url,
                'payload' => $payload
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            Log::info('Queue API Test Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $data = $response->json();

            if ($response->successful()) {
                if (isset($data['success']) && $data['success']) {
                    $queueNumber = $data['data']['queue_number'] ?? 'N/A';
                    $position = $data['data']['position'] ?? 'N/A';
                    $waitTime = $data['data']['estimated_wait_minutes'] ?? 'N/A';

                    $this->success(
                        "Queue created! Number: {$queueNumber} | Position: {$position} | Wait: {$waitTime} min"
                    );
                    $this->showTestApiModal = false;
                    $this->loadStats();
                    $this->dispatch('refresh-queue');
                    return;
                }
            }

            $errorMsg = 'Unknown error';

            if (isset($data['message'])) {
                $errorMsg = $data['message'];
            } elseif (isset($data['error'])) {
                $errorMsg = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
            } elseif ($response->status() >= 400) {
                $errorMsg = "HTTP {$response->status()}: " . $response->body();
            }

            $this->error("API Error: {$errorMsg}");
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->error('Connection failed: Cannot reach API endpoint. Check if the server is running.');
            Log::error('Queue API Connection Error', ['error' => $e->getMessage()]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $this->error('Request failed: ' . $e->getMessage());
            Log::error('Queue API Request Error', ['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->error('Unexpected error: ' . $e->getMessage());
            Log::error('Queue API Unexpected Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function fillSampleData()
    {
        try {
            $sample = DB::connection('webapp')->select("
            SELECT TOP 1
                p.id as prescription_id,
                p.enccode,
                p.hpercode
            FROM webapp.dbo.prescriptions p WITH (NOLOCK)
            WHERE p.created_at >= DATEADD(day, -7, GETDATE())
            ORDER BY p.created_at DESC
        ");

            if (!empty($sample)) {
                $this->testPrescriptionId = $sample[0]->prescription_id;
                $this->testEnccode = $sample[0]->enccode;
                $this->testHpercode = $sample[0]->hpercode;
                $this->info('Sample data loaded from recent prescription');
            } else {
                $this->testPrescriptionId = '12345';
                $this->testEnccode = '000004012345TEST' . date('mdYHis');
                $this->testHpercode = 'TEST' . date('Y') . sprintf('%07d', rand(1, 9999999));
                $this->warning('Using dummy data - no recent prescriptions found');
            }
        } catch (\Exception $e) {
            $this->testPrescriptionId = '12345';
            $this->testEnccode = '000004012345TEST' . date('mdYHis');
            $this->testHpercode = 'TEST' . date('Y') . sprintf('%07d', rand(1, 9999999));
            $this->warning('Using dummy data');
        }
    }

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

            // $queue->prescription->update([
            //     'stat' => 'I'
            // ]);

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
