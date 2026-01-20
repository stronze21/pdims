<?php

namespace App\Livewire\Pharmacy\Settings;

use App\Models\Pharmacy\ZeroBillingCharge;
use App\Models\References\ChargeCode;
use Livewire\Component;
use Mary\Traits\Toast;

class ManageZeroBillingCharges extends Component
{
    use Toast;

    public $charges;
    public $available_charge_codes;

    // Modal properties
    public $addModal = false;
    public $editModal = false;
    public $deleteModal = false;

    public $selectedId;
    public $chrgcode;
    public $description;
    public $is_active = true;

    public function mount()
    {
        $this->loadCharges();
        $this->loadAvailableChargeCodes();
    }

    public function loadCharges()
    {
        $this->charges = ZeroBillingCharge::with(['createdBy', 'updatedBy'])
            ->orderBy('chrgcode')
            ->get();
    }

    public function loadAvailableChargeCodes()
    {
        $existingCodes = ZeroBillingCharge::pluck('chrgcode')->toArray();

        $this->available_charge_codes = ChargeCode::where('bentypcod', 'DRUME')
            ->where('chrgstat', 'A')
            ->whereNotIn('chrgcode', $existingCodes)
            ->get();
    }

    public function openAddModal()
    {
        $this->resetForm();
        $this->addModal = true;
    }

    public function openEditModal($id)
    {
        $charge = ZeroBillingCharge::find($id);

        $this->selectedId = $charge->id;
        $this->chrgcode = $charge->chrgcode;
        $this->description = $charge->description;
        $this->is_active = $charge->is_active;

        $this->editModal = true;
    }

    public function openDeleteModal($id)
    {
        $this->selectedId = $id;
        $this->deleteModal = true;
    }

    public function save()
    {
        $this->validate([
            'chrgcode' => 'required|unique:pharm_zero_billing_charges,chrgcode',
            'description' => 'nullable|string|max:255',
        ]);

        ZeroBillingCharge::create([
            'chrgcode' => $this->chrgcode,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_by' => auth()->id(),
        ]);

        $this->addModal = false;
        $this->loadCharges();
        $this->loadAvailableChargeCodes();
        $this->success('Zero billing charge added successfully!');
    }

    public function update()
    {
        $this->validate([
            'description' => 'nullable|string|max:255',
        ]);

        $charge = ZeroBillingCharge::find($this->selectedId);
        $charge->update([
            'description' => $this->description,
            'is_active' => $this->is_active,
            'updated_by' => auth()->id(),
        ]);

        $this->editModal = false;
        $this->loadCharges();
        $this->success('Zero billing charge updated successfully!');
    }

    public function delete()
    {
        ZeroBillingCharge::destroy($this->selectedId);

        $this->deleteModal = false;
        $this->loadCharges();
        $this->loadAvailableChargeCodes();
        $this->success('Zero billing charge deleted successfully!');
    }

    public function toggleActive($id)
    {
        $charge = ZeroBillingCharge::find($id);
        $charge->update([
            'is_active' => !$charge->is_active,
            'updated_by' => auth()->id(),
        ]);

        $this->loadCharges();
        $this->success('Status updated successfully!');
    }

    private function resetForm()
    {
        $this->reset(['selectedId', 'chrgcode', 'description', 'is_active']);
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.pharmacy.settings.manage-zero-billing-charges');
    }
}
