<?php

namespace App\Livewire\Portal;

use App\Models\Portal\PortalPrescriptionRefill;
use App\Models\Portal\PortalPatient;
use App\Models\Record\Prescriptions\PrescriptionData;
use App\Services\Pharmacy\PrescriptionReactivationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ManageRefillRequests extends Component
{
    use Toast, WithPagination;

    protected PrescriptionReactivationService $reactivationService;

    public $search = '';
    public $statusFilter = 'all';

    // View modal
    public $viewModal = false;
    public $viewRefill = null;
    public $prescriptionContext = null;

    // Process modal
    public $processModal = false;
    public $selectedRefillId = null;
    public $processAction = '';
    public $adminRemarks = '';
    public bool $refillTableAvailable = true;

    // Manual refill modal
    public bool $manualRefillModal = false;
    public string $manualPatientSearch = '';
    public array $manualPatientResults = [];
    public ?int $manualPatientId = null;
    public ?PortalPatient $manualPatient = null;
    public array $manualPrescriptionItems = [];
    public ?int $manualPrescriptionDataId = null;
    public $manualQtyRequested = 1;
    public string $manualRemarks = '';

    public function mount()
    {
        $this->refillTableAvailable = Schema::connection('portal')->hasTable('prescription_refill_requests');
    }

    public function boot(PrescriptionReactivationService $reactivationService)
    {
        $this->reactivationService = $reactivationService;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function openViewModal($refillId)
    {
        if (! $this->refillTableAvailable) {
            $this->warning('Prescription refill requests table is missing. Run the Portal migration first.');
            return;
        }

        $this->viewRefill = PortalPrescriptionRefill::with('patient')->find($refillId);
        $this->prescriptionContext = null;

        if ($this->viewRefill && $this->viewRefill->prescription_data_id) {
            $context = DB::connection('webapp')->selectOne("
                SELECT
                    pd.id,
                    pd.qty,
                    pd.frequency,
                    pd.duration,
                    pd.remark,
                    pd.archive,
                    pd.stat,
                    pd.addtl_remarks,
                    dm.drug_concat,
                    COALESCE(pdi.total_issued, 0) as total_issued,
                    emp.lastname + ', ' + emp.firstname AS doctor_name,
                    rx.created_at as prescribed_at
                FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
                INNER JOIN webapp.dbo.prescription rx WITH (NOLOCK)
                    ON pd.presc_id = rx.id
                INNER JOIN hospital.dbo.hdmhdr dm WITH (NOLOCK)
                    ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
                LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                    ON rx.empid = emp.employeeid
                LEFT JOIN (
                    SELECT presc_data_id, SUM(qtyissued) as total_issued
                    FROM webapp.dbo.prescription_data_issued WITH (NOLOCK)
                    GROUP BY presc_data_id
                ) pdi ON pd.id = pdi.presc_data_id
                WHERE pd.id = ?
            ", [$this->viewRefill->prescription_data_id]);

            if ($context) {
                $this->prescriptionContext = (object) array_merge(
                    (array) $context,
                    $this->reactivationService->enrichItem($context)
                );
            }
        }

        $this->viewModal = true;
    }

    public function openManualRefillModal()
    {
        $this->reset(
            'manualPatientSearch',
            'manualPatientResults',
            'manualPatientId',
            'manualPatient',
            'manualPrescriptionItems',
            'manualPrescriptionDataId',
            'manualRemarks'
        );
        $this->manualQtyRequested = 1;
        $this->manualRefillModal = true;
    }

    public function updatedManualPatientSearch()
    {
        $search = trim($this->manualPatientSearch);

        if (mb_strlen($search) < 2) {
            $this->manualPatientResults = [];
            return;
        }

        $this->manualPatientResults = PortalPatient::query()
            ->where(function ($query) use ($search) {
                $query->where('hpercode', 'LIKE', "%{$search}%")
                    ->orWhere('patlast', 'LIKE', "%{$search}%")
                    ->orWhere('patfirst', 'LIKE', "%{$search}%");
            })
            ->orderBy('patlast')
            ->orderBy('patfirst')
            ->limit(10)
            ->get()
            ->map(fn (PortalPatient $patient) => [
                'id' => $patient->id,
                'fullname' => $patient->fullname,
                'hpercode' => $patient->hpercode,
            ])
            ->all();
    }

    public function selectManualPatient(int $patientId): void
    {
        $patient = PortalPatient::query()->find($patientId);

        if (!$patient) {
            $this->warning('Patient not found.');
            return;
        }

        $this->manualPatientId = $patient->id;
        $this->manualPatient = $patient;
        $this->manualPatientSearch = $patient->fullname . ' - ' . $patient->hpercode;
        $this->manualPatientResults = [];
        $this->manualPrescriptionItems = $this->loadManualPrescriptionItems($patient->hpercode);
        $this->manualPrescriptionDataId = null;
        $this->manualQtyRequested = 1;
    }

    public function updatedManualPrescriptionDataId($value): void
    {
        $selected = collect($this->manualPrescriptionItems)
            ->firstWhere('id', (int) $value);

        if ($selected) {
            $this->manualQtyRequested = max(1, (float) ($selected['allowable_request_qty'] ?? 1));
        }
    }

    public function openProcessModal($refillId, $action)
    {
        if (! $this->refillTableAvailable) {
            $this->warning('Prescription refill requests table is missing. Run the Portal migration first.');
            return;
        }

        $this->selectedRefillId = $refillId;
        $this->processAction = $action;
        $this->adminRemarks = '';
        $this->processModal = true;
    }

    public function processRefill()
    {
        if (! $this->refillTableAvailable) {
            $this->warning('Prescription refill requests table is missing. Run the Portal migration first.');
            $this->processModal = false;
            return;
        }

        $refill = PortalPrescriptionRefill::find($this->selectedRefillId);

        if (!$refill || $refill->status !== 'pending') {
            $this->error('This request has already been processed.');
            $this->processModal = false;
            return;
        }

        $refill->update([
            'status' => $this->processAction,
            'admin_remarks' => $this->adminRemarks,
            'processed_by' => auth()->user()->name ?? auth()->id(),
            'processed_at' => now(),
        ]);

        if ($this->processAction === 'approved' && $refill->prescription_data_id) {
            $prescriptionData = PrescriptionData::query()->find($refill->prescription_data_id);

            if ($prescriptionData && (int) ($prescriptionData->archive ?? 0) !== 1) {
                $reorderedAt = now('Asia/Manila');

                $prescriptionData->stat = 'A';
                $prescriptionData->active_date = $reorderedAt;
                $prescriptionData->updated_at = $reorderedAt;
                $prescriptionData->save();

                $this->reactivationService->logReorder(
                    (int) $prescriptionData->id,
                    'manual',
                    $reorderedAt,
                    'Manual reorder from approved refill request',
                    auth()->user()?->name ?? (string) auth()->id()
                );
            }
        }

        $statusLabel = $this->processAction === 'approved' ? 'approved' : 'denied';
        $this->success("Refill request has been {$statusLabel}.");
        $this->processModal = false;
        $this->selectedRefillId = null;
    }

    public function createManualRefill(): void
    {
        if (!$this->manualPatient || !$this->manualPatientId) {
            $this->warning('Select a patient first.');
            return;
        }

        $item = collect($this->manualPrescriptionItems)
            ->firstWhere('id', (int) $this->manualPrescriptionDataId);

        if (!$item) {
            $this->warning('Select a prescription item to refill.');
            return;
        }

        $allowableQty = (float) ($item['allowable_request_qty'] ?? 0);
        $qtyRequested = (float) $this->manualQtyRequested;

        if ($qtyRequested <= 0) {
            $this->warning('Quantity must be greater than zero.');
            return;
        }

        if ($allowableQty > 0 && $qtyRequested > $allowableQty) {
            $this->warning("Requested quantity exceeds the current allowable refill ({$allowableQty}).");
            return;
        }

        $now = now('Asia/Manila');

        $refill = PortalPrescriptionRefill::create([
            'patient_id' => $this->manualPatient->id,
            'hpercode' => $this->manualPatient->hpercode,
            'enccode' => $item['enccode'] ?? null,
            'prescription_id' => $item['prescription_id'] ?? null,
            'prescription_data_id' => $item['id'],
            'dmdcomb' => $item['dmdcomb'] ?? null,
            'dmdctr' => $item['dmdctr'] ?? null,
            'drug_name' => $item['drug_name'] ?? ($item['drug_concat'] ?? 'Medication'),
            'qty_requested' => $qtyRequested,
            'remarks' => $this->manualRemarks ?: 'Manual refill recorded by pharmacy/physician.',
            'request_source' => 'pharmacy_manual',
            'status' => 'completed',
            'admin_remarks' => 'Recorded manually by pharmacy/physician without patient app request.',
            'processed_by' => auth()->user()?->name ?? (string) auth()->id(),
            'processed_at' => $now,
        ]);

        $prescriptionData = PrescriptionData::query()->find($item['id']);
        if ($prescriptionData && (int) ($prescriptionData->archive ?? 0) !== 1) {
            $prescriptionData->stat = 'A';
            $prescriptionData->active_date = $now;
            $prescriptionData->updated_at = $now;
            $prescriptionData->save();

            $this->reactivationService->logReorder(
                (int) $prescriptionData->id,
                'manual',
                $now,
                'Manual refill recorded by pharmacy/physician without patient app request',
                auth()->user()?->name ?? (string) auth()->id()
            );
        }

        $this->manualRefillModal = false;
        $this->success("Manual refill recorded for {$refill->drug_name}.");
    }

    public function markCompleted($refillId)
    {
        if (! $this->refillTableAvailable) {
            $this->warning('Prescription refill requests table is missing. Run the Portal migration first.');
            return;
        }

        $refill = PortalPrescriptionRefill::find($refillId);

        if ($refill && $refill->status === 'approved') {
            $refill->update([
                'status' => 'completed',
                'processed_by' => auth()->user()->name ?? auth()->id(),
                'processed_at' => now(),
            ]);
            $this->success('Refill request marked as completed.');
        }
    }

    public function render()
    {
        if (! $this->refillTableAvailable) {
            return view('livewire.portal.manage-refill-requests', [
                'refills' => new LengthAwarePaginator(
                    items: collect(),
                    total: 0,
                    perPage: 15,
                    currentPage: 1,
                    options: ['path' => request()->url(), 'pageName' => 'page']
                ),
                'pendingCount' => 0,
            ])->layout('layouts.portal');
        }

        $refills = PortalPrescriptionRefill::with('patient')
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('drug_name', 'LIKE', "%{$this->search}%")
                        ->orWhere('hpercode', 'LIKE', "%{$this->search}%")
                        ->orWhereHas('patient', function ($pq) {
                            $pq->where('patlast', 'LIKE', "%{$this->search}%")
                                ->orWhere('patfirst', 'LIKE', "%{$this->search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15);

        $pendingCount = PortalPrescriptionRefill::where('status', 'pending')->count();

        return view('livewire.portal.manage-refill-requests', [
            'refills' => $refills,
            'pendingCount' => $pendingCount,
        ])->layout('layouts.portal');
    }

    private function loadManualPrescriptionItems(?string $hpercode): array
    {
        if (!$hpercode) {
            return [];
        }

        $items = DB::connection('hospital')->select("
            SELECT
                pd.id,
                pd.presc_id AS prescription_id,
                rx.enccode,
                pd.dmdcomb,
                pd.dmdctr,
                pd.qty,
                pd.frequency,
                pd.duration,
                pd.remark,
                pd.addtl_remarks,
                pd.archive,
                pd.stat,
                dm.drug_concat,
                COALESCE(pdi.total_issued, 0) AS total_issued
            FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
            INNER JOIN webapp.dbo.prescription rx WITH (NOLOCK)
                ON rx.id = pd.presc_id
            INNER JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON enctr.enccode = rx.enccode
            INNER JOIN hospital.dbo.hdmhdr dm WITH (NOLOCK)
                ON pd.dmdcomb = dm.dmdcomb
                AND pd.dmdctr = dm.dmdctr
            LEFT JOIN (
                SELECT presc_data_id, SUM(qtyissued) AS total_issued
                FROM webapp.dbo.prescription_data_issued WITH (NOLOCK)
                GROUP BY presc_data_id
            ) pdi ON pdi.presc_data_id = pd.id
            WHERE enctr.hpercode = ?
                AND (pd.archive IS NULL OR pd.archive = 0)
            ORDER BY rx.created_at DESC, pd.created_at DESC
        ", [$hpercode]);

        return $this->reactivationService->enrichItems($items)
            ->filter(fn (array $item) => !($item['needs_manual_review'] ?? false) && (float) ($item['computed_remaining_qty'] ?? 0) > 0)
            ->map(function (array $item) {
                $parts = explode('_,', $item['drug_concat'] ?? '');

                return [
                    'id' => (int) $item['id'],
                    'prescription_id' => (int) ($item['presc_id'] ?? 0),
                    'enccode' => $item['enccode'] ?? null,
                    'dmdcomb' => $item['dmdcomb'] ?? null,
                    'dmdctr' => $item['dmdctr'] ?? null,
                    'drug_name' => trim(($parts[0] ?? '') . ' ' . ($parts[1] ?? '')),
                    'drug_concat' => $item['drug_concat'] ?? null,
                    'schedule_text' => $item['schedule_text'] ?? null,
                    'days_to_cover' => $item['days_to_cover'] ?? null,
                    'qty_issued' => (float) ($item['qty_issued'] ?? 0),
                    'computed_remaining_qty' => (float) ($item['computed_remaining_qty'] ?? 0),
                    'allowable_request_qty' => (float) ($item['allowable_request_qty'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }
}
