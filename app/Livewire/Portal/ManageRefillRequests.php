<?php

namespace App\Livewire\Portal;

use App\Models\Portal\PortalPrescriptionRefill;
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
