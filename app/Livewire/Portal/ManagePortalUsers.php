<?php

namespace App\Livewire\Portal;

use App\Models\Portal\PortalUser;
use App\Models\Record\Patients\Patient;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ManagePortalUsers extends Component
{
    use Toast, WithPagination;

    public $search = '';
    public $statusFilter = '';

    // Verification modal
    public $verifyModal = false;
    public $selectedUser = null;
    public $hospital_no = '';

    // Reject modal
    public $rejectModal = false;
    public $reject_reason = '';

    // View modal
    public $viewModal = false;
    public $viewUser = null;
    public $matchedPatient = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function openViewModal($userId)
    {
        $this->viewUser = PortalUser::find($userId);
        $this->matchedPatient = null;

        if ($this->viewUser && $this->viewUser->hpercode) {
            $this->matchedPatient = Patient::where('hpercode', $this->viewUser->hpercode)->first();
        }

        $this->viewModal = true;
    }

    public function openVerifyModal($userId)
    {
        $this->selectedUser = PortalUser::find($userId);
        $this->hospital_no = $this->selectedUser->hospital_no ?? '';

        // Try to find matching patient
        if ($this->selectedUser && $this->selectedUser->hpercode) {
            $patient = Patient::where('hpercode', $this->selectedUser->hpercode)->first();
            if ($patient) {
                $this->hospital_no = $patient->hpatcode ?? $this->hospital_no;
            }
        }

        $this->verifyModal = true;
    }

    public function verify()
    {
        $this->validate([
            'hospital_no' => 'required|string|max:50',
        ]);

        if (!$this->selectedUser) {
            $this->error('User not found.');
            return;
        }

        // If no hpercode linked yet, try to find patient by hospital number
        if (!$this->selectedUser->hpercode) {
            $patient = Patient::where('hpatcode', $this->hospital_no)->first();

            if ($patient) {
                $this->selectedUser->hpercode = $patient->hpercode;
            }
        }

        $this->selectedUser->update([
            'hospital_no' => $this->hospital_no,
            'hpercode' => $this->selectedUser->hpercode,
            'status' => 'verified',
            'verified_by' => auth()->user()->name,
            'verified_at' => now(),
        ]);

        $this->verifyModal = false;
        $this->reset(['hospital_no', 'selectedUser']);
        $this->success('Portal user has been verified successfully.');
    }

    public function openRejectModal($userId)
    {
        $this->selectedUser = PortalUser::find($userId);
        $this->reject_reason = '';
        $this->rejectModal = true;
    }

    public function reject()
    {
        $this->validate([
            'reject_reason' => 'required|string|max:500',
        ]);

        if (!$this->selectedUser) {
            $this->error('User not found.');
            return;
        }

        $this->selectedUser->update([
            'status' => 'rejected',
            'reject_reason' => $this->reject_reason,
            'verified_by' => auth()->user()->name,
            'verified_at' => now(),
        ]);

        $this->rejectModal = false;
        $this->reset(['reject_reason', 'selectedUser']);
        $this->success('Portal user has been rejected.');
    }

    public function render()
    {
        $users = PortalUser::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('patlast', 'LIKE', "%{$this->search}%")
                        ->orWhere('patfirst', 'LIKE', "%{$this->search}%")
                        ->orWhere('email', 'LIKE', "%{$this->search}%")
                        ->orWhere('hospital_no', 'LIKE', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(15);

        return view('livewire.portal.manage-portal-users', [
            'users' => $users,
        ]);
    }
}
