<?php

namespace App\Livewire\Portal;

use App\Models\Portal\PortalPrescriptionRefill;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ManageRefillRequests extends Component
{
    use Toast, WithPagination;

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
        $this->viewRefill = PortalPrescriptionRefill::with('patient')->find($refillId);
        $this->prescriptionContext = null;

        if ($this->viewRefill && $this->viewRefill->prescription_data_id) {
            $this->prescriptionContext = DB::connection('webapp')->selectOne("
                SELECT
                    pd.qty as qty_ordered,
                    pd.frequency,
                    pd.duration,
                    pd.remark,
                    pd.addtl_remarks,
                    dm.drug_concat,
                    COALESCE(pdi.total_issued, 0) as total_issued,
                    (pd.qty - COALESCE(pdi.total_issued, 0)) as remaining_qty,
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
        }

        $this->viewModal = true;
    }

    public function openProcessModal($refillId, $action)
    {
        $this->selectedRefillId = $refillId;
        $this->processAction = $action;
        $this->adminRemarks = '';
        $this->processModal = true;
    }

    public function processRefill()
    {
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

        $statusLabel = $this->processAction === 'approved' ? 'approved' : 'denied';
        $this->success("Refill request has been {$statusLabel}.");
        $this->processModal = false;
        $this->selectedRefillId = null;
    }

    public function markCompleted($refillId)
    {
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
        ]);
    }
}
