<?php

namespace App\Livewire\Portal;

use App\Models\Portal\PortalPatient;
use App\Models\Portal\PortalUserAccount;
use App\Models\Record\Patients\Patient;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ManagePortalUsers extends Component
{
    use Toast, WithPagination;

    public $search = '';

    // View modal
    public $viewModal = false;
    public $viewUser = null;
    public $viewPatient = null;
    public $matchedHospitalPatient = null;

    // Delete modal
    public $deleteModal = false;
    public $selectedAccountId = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function openViewModal($accountId)
    {
        $this->viewUser = PortalUserAccount::with('patient')->find($accountId);
        $this->viewPatient = $this->viewUser?->patient;
        $this->matchedHospitalPatient = null;

        if ($this->viewPatient && $this->viewPatient->hpercode) {
            $this->matchedHospitalPatient = Patient::where('hpercode', $this->viewPatient->hpercode)->first();
        }

        $this->viewModal = true;
    }

    public function openDeleteModal($accountId)
    {
        $this->selectedAccountId = $accountId;
        $this->deleteModal = true;
    }

    public function deleteAccount()
    {
        $account = PortalUserAccount::find($this->selectedAccountId);

        if ($account) {
            $account->delete();
            $this->success('Portal user account has been deleted.');
        }

        $this->deleteModal = false;
        $this->selectedAccountId = null;
    }

    public function render()
    {
        $accounts = PortalUserAccount::with('patient')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('username', 'LIKE', "%{$this->search}%")
                        ->orWhere('email', 'LIKE', "%{$this->search}%")
                        ->orWhereHas('patient', function ($pq) {
                            $pq->where('patlast', 'LIKE', "%{$this->search}%")
                                ->orWhere('patfirst', 'LIKE', "%{$this->search}%")
                                ->orWhere('hpercode', 'LIKE', "%{$this->search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15);

        return view('livewire.portal.manage-portal-users', [
            'accounts' => $accounts,
        ]);
    }
}
