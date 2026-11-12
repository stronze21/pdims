<?php

namespace App\Livewire\Pharmacy\Training;

use App\Livewire\Pharmacy\Dispensing\DispensingEncounter;
use App\Livewire\Pharmacy\Prescriptions\Queueing\PrescriptionQueueController;
use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Component;

class OpdWalkthrough extends Component
{
    private const ORDER = 'DEMO-RXO-001';

    private const PRESCRIBING_CLINIC = 'FAMED';

    private const OPD_CLINICS = [
        'FAMED' => 'Family Medicine',
        'MED' => 'Internal Medicine',
        'PEDIA' => 'Pediatrics',
    ];

    public const MEDICATION = 'Sample Medication A';

    public const REMARKS = 'Test Insctructions';

    #[Locked]
    public string $screen = 'queue';

    #[Locked]
    public string $stage = 'waiting';

    #[Locked]
    public ?string $prescriptionSource = null;

    #[Locked]
    public bool $showTrainingDisplay = false;

    #[Locked]
    public bool $patientCalled = false;

    public int $selectedWindow = 1;

    public string $dateFilter = '';

    public array $selected_items = [];

    public int $stocksDisplayCount = 50;

    public array $charge_code_filter = [];

    public bool $showIssueModal = false;

    public bool $showPrintModal = false;

    public bool $printIncludeInactive = false;

    public bool $printDeductIssued = true;

    public array $printSelectedItems = [];

    public string $deptcode = '';

    public bool $pay = false;

    public bool $ems = false;

    public bool $konsulta = false;

    public bool $wholesale = false;

    public bool $caf = false;

    public bool $maip = false;

    public bool $is_ris = false;

    public bool $pcso = false;

    public bool $phic = false;

    public function mount(string $screen = 'queue'): void
    {
        $this->screen = $screen;
        $this->stage = $screen === 'dispensing' ? 'preparing' : 'waiting';
        $this->showTrainingDisplay = $screen === 'queue';
        $this->prescriptionSource = self::resolvePrescriptionSource(request());
        $this->dateFilter = now()->toDateString();
    }

    public function nextQueue(): void
    {
        if ($this->screen === 'queue' && $this->stage === 'waiting') {
            $this->stage = 'preparing';
            $this->showTrainingDisplay = true;
        }
    }

    public function selectQueue(int $id): void
    {
        if ($id === 1) {
            $this->nextQueue();
        }
    }

    public function openDispensing(): void
    {
        if ($this->screen === 'queue' && $this->stage === 'preparing') {
            $this->redirectRoute('prescriptions.opd-walkthrough', ['screen' => 'dispensing'] + $this->trackParameters());
        }
    }

    public function closeTrainingDisplay(): void
    {
        $this->showTrainingDisplay = false;
    }

    public function updateSelectedItems(array $items): void
    {
        $this->selected_items = in_array(self::ORDER, $items, true) ? [self::ORDER] : [];
    }

    public function charge_items(): array
    {
        if (! $this->hasSelectedOrder('preparing')) {
            return ['status' => 'error', 'message' => 'Select the pending training item first.'];
        }

        $this->stage = 'ready';
        $this->showTrainingDisplay = true;
        $this->selected_items = [];

        return ['status' => 'success', 'message' => 'The sample item is charged and the queue is Ready.'];
    }

    public function openIssueModal(): void
    {
        $this->showIssueModal = $this->patientCalled && $this->hasSelectedOrder('ready');
        if ($this->showIssueModal) {
            $this->deptcode = self::PRESCRIBING_CLINIC;
        }
    }

    public function callForPatient(): void
    {
        if ($this->screen === 'dispensing' && $this->stage === 'ready') {
            $this->patientCalled = true;
            $this->showTrainingDisplay = true;
        }
    }

    public function selectIssueTag(string $selected): void
    {
        foreach (['pay', 'ems', 'konsulta', 'wholesale', 'caf', 'maip', 'is_ris', 'pcso', 'phic'] as $tag) {
            $this->$tag = $tag === $selected;
        }
    }

    public function issue_order(): array
    {
        if (! $this->patientCalled || ! $this->showIssueModal || ! $this->hasSelectedOrder('ready') || ! isset(self::OPD_CLINICS[$this->deptcode])) {
            return ['status' => 'error', 'message' => 'Select the prescribing OPD clinic before confirming the issue.'];
        }

        $this->stage = 'dispensed';
        $this->showTrainingDisplay = true;
        $this->showIssueModal = false;
        $this->selected_items = [];

        return ['status' => 'success', 'message' => 'The sample item is issued and the queue is Dispensed.'];
    }

    public function openPrintPrescriptionsModal(): void
    {
        if ($this->screen === 'dispensing' && $this->stage === 'dispensed') {
            $this->printIncludeInactive = false;
            $this->printDeductIssued = true;
            $this->printSelectedItems = [];
            $this->showPrintModal = true;
        }
    }

    public function togglePrintItemSelection(int $id): void
    {
        if ($id === 1) {
            $this->selectAllPrintItems();
        }
    }

    public function selectAllPrintItems(): void
    {
        $this->printSelectedItems = $this->printSelectedItems === [1] ? [] : [1];
    }

    public function printPrescriptions(): void
    {
        if ($this->screen === 'dispensing' && $this->stage === 'dispensed' && $this->printSelectedItems === [1]) {
            $this->redirectRoute('prescriptions.opd-walkthrough.print', [
                'remaining' => (int) $this->printDeductIssued,
            ] + $this->trackParameters());
        }
    }

    private function trackParameters(): array
    {
        return $this->prescriptionSource ? ['source' => $this->prescriptionSource] : [];
    }

    public static function resolvePrescriptionSource(\Illuminate\Http\Request $request): ?string
    {
        return match ($request->query('source')) {
            'emr2' => 'emr2',
            'cdoe' => 'cdoe',
            default => $request->boolean('with_cdoe') ? 'cdoe' : null,
        };
    }

    public function getMssClassification(): string
    {
        return 'Service';
    }

    public function isMghTransaction(): bool
    {
        return false;
    }

    private function hasSelectedOrder(string $stage): bool
    {
        return $this->screen === 'dispensing' && $this->stage === $stage && $this->selected_items === [self::ORDER];
    }

    public function render()
    {
        $view = $this->screen === 'queue' ? $this->queueView() : $this->dispensingView();

        return $view->with('trainingDisplay', $this->showTrainingDisplay ? $this->trainingDisplayData() : null);
    }

    private function sampleQueue(): PrescriptionQueue
    {
        $now = now();
        $queue = new PrescriptionQueue;
        $queue->setDateFormat('Y-m-d H:i:s');
        $queue->setRawAttributes([
            'id' => 1,
            'queue_number' => 'RX-001',
            'queue_status' => $this->stage,
            'priority' => 'normal',
            'queued_at' => $now->copy()->subMinutes(4),
            'preparing_at' => $now->copy()->subMinutes(2),
            'ready_at' => $this->stage === 'ready' ? $now : null,
            'called_at' => $this->patientCalled ? $now : null,
            'assigned_window' => $this->stage === 'waiting' ? null : 1,
            'enccode' => 'TRAINING-OPD-001',
        ]);
        $queue->setRelation('patient', (object) ['patlast' => 'TRAINING', 'patfirst' => 'Alex', 'hpercode' => 'DEMO-001']);

        return $queue;
    }

    private function trainingDisplayData(): array
    {
        $queue = $this->sampleQueue();
        return [
            'displaySettings' => (object) ['require_cashier' => false, 'play_sound_alert' => false, 'show_waiting_column' => true],
            'currentlyServing' => collect($this->stage === 'preparing' ? [$queue] : []),
            'showingQueues' => [
                'ready' => collect($this->stage === 'ready' ? [$queue] : []),
                'waiting' => [
                    ...($this->stage === 'waiting' ? [$queue] : []),
                    (object) ['queue_number' => 'RX-002'],
                    (object) ['queue_number' => 'RX-003'],
                    (object) ['queue_number' => 'RX-004'],
                ],
            ],
            'lastCalledQueue' => null,
        ];
    }

    private function queueView()
    {
        $queue = $this->sampleQueue();

        return view('livewire.pharmacy.prescriptions.queueing.prescription-queue-controller', array_replace(
            get_class_vars(PrescriptionQueueController::class),
            [
                'opdTraining' => true,
                'trainingHint' => $this->stage === 'waiting'
                    ? ($this->prescriptionSource
                        ? 'The sample prescription from '.strtoupper($this->prescriptionSource).' is now waiting. Choose Call Next Queue for Window 1.'
                        : 'RX-001 is waiting in the pharmacy queue. Choose Call Next Queue for Window 1.')
                    : 'Verify Alex Training and queue RX-001, then choose Open Dispensing.',
                'maxWindows' => 1,
                'queues' => new LengthAwarePaginator([$queue], 1, 50),
                'currentQueue' => $this->stage === 'preparing' ? $queue : null,
                'stats' => ['waiting' => (int) ($this->stage === 'waiting'), 'charging' => 0, 'ready' => 0],
                'locations' => collect(),
            ],
        ))->layout('layouts.queue-controller');
    }

    private function dispensingView()
    {
        $status = match ($this->stage) {
            'preparing' => 'U',
            'ready' => 'P',
            'dispensed' => 'S',
        };
        $chargeSlip = $status === 'U' ? null : 'DEMO-CHARGE-001';
        $drug = new Drug;
        $drug->setRawAttributes(['drug_concat' => self::MEDICATION]);
        $prescription = (object) [
            'id' => 1,
            'dm' => $drug,
            'qty' => 1,
            'order_type' => 'Basic',
            'remark' => self::REMARKS,
        ];
        $order = (object) [
            'docointkey' => self::ORDER,
            'estatus' => $status,
            'pcchrgcod' => $chargeSlip,
            'dodate' => now(),
            'drug_concat' => self::MEDICATION,
            'prescription_data_id' => 1,
            'chrgdesc' => 'Service',
            'pchrgqty' => 1,
            'qtyissued' => (int) ($status === 'S'),
            'qtybal' => (int) ($status !== 'S'),
            'pchrgup' => 0,
            'pcchrgamt' => 0,
            'remarks' => self::REMARKS,
            'rx_remark' => null,
            'rx_addtl_remarks' => null,
            'tx_type' => null,
        ];

        return view('livewire.pharmacy.dispensing.dispensing-encounter', array_replace(
            get_class_vars(DispensingEncounter::class),
            [
                'opdTraining' => true,
                'trainingHint' => match ($this->stage) {
                    'preparing' => 'Verify the sample encounter, select the pending medication, then choose Charge.',
                    'ready' => $this->patientCalled
                        ? 'Select the charged medication and choose Issue. Review the prefilled prescribing clinic, then confirm.'
                        : 'Choose Call for Patient to see the claiming alert on the pharmacy TV display.',
                    'dispensed' => 'Open Tools → Print Rx. Select the medication and clear the deduction option to preview the full prescribed quantity.',
                },
                'hasEncounter' => true,
                'can_add_items' => true,
                'patlast' => 'TRAINING',
                'patfirst' => 'Alex',
                'hpercode' => 'DEMO-001',
                'toecode' => 'OPD',
                'queueId' => 1,
                'currentQueueNumber' => 'RX-001',
                'currentQueueStatus' => $this->stage,
                'queueChargeSlipNo' => $chargeSlip,
                'charges' => [],
                'active_prescription' => [(object) ['data_active' => [$prescription]]],
                'orders' => [$order],
                'stocks' => [],
                'prescriptionClinicCode' => self::PRESCRIBING_CLINIC,
                'departments' => collect(self::OPD_CLINICS)->map(fn ($name, $code) => (object) [
                    'deptcode' => $code,
                    'deptname' => $name,
                ])->all(),
                'summaries' => [],
                'printItems' => [[
                    'id' => 1,
                    'drug_concat' => self::MEDICATION,
                    'qty_per_administration' => 1,
                    'schedule_text' => 'Once daily',
                    'days_to_cover' => 1,
                    'order_type' => 'Basic',
                ]],
            ],
        ))->layout('layouts.dispensing');
    }
}
