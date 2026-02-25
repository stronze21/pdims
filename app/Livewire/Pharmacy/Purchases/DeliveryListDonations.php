<?php

namespace App\Livewire\Pharmacy\Purchases;

use App\Models\Pharmacy\DeliveryDetail;
use App\Models\References\ChargeCode;
use App\Models\References\Supplier;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class DeliveryListDonations extends Component
{
    use WithPagination;
    use Toast;

    public $search = '';
    public $supplier_id = '00688';

    // Form fields
    public $po_no;
    public $delivery_date;
    public $delivery_type = 'donation';

    public $addModal = false;

    public function mount()
    {
        $this->delivery_date = date('Y-m-d');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openAddModal()
    {
        $this->reset(['po_no', 'delivery_type']);
        $this->delivery_date = date('Y-m-d');
        $this->delivery_type = 'donation';
        $this->addModal = true;
    }

    public function add_delivery()
    {
        $this->validate([
            'delivery_date' => 'required|date',
        ]);

        $delivery = DeliveryDetail::create([
            'pharm_location_id' => auth()->user()->pharm_location_id,
            'user_id' => auth()->id(),
            'delivery_date' => $this->delivery_date,
            'suppcode' => $this->supplier_id,
            'delivery_type' => $this->delivery_type,
            'charge_code' => 'DRUMAK',
            'po_no' => $this->po_no,
        ]);

        $this->addModal = false;
        return $this->redirect(route('purchases.delivery-view', $delivery->id), navigate: true);
    }

    public function render()
    {
        $deliveries = DeliveryDetail::with(['supplier', 'items', 'charge'])
            ->where('pharm_location_id', auth()->user()->pharm_location_id)
            ->when($this->supplier_id, function ($query, $supplier_id) {
                $query->where('suppcode', $supplier_id);
            })
            ->latest()
            ->paginate(15);

        $suppliers = Supplier::where('suppcode', $this->supplier_id)->get();

        return view('livewire.pharmacy.purchases.delivery-list-donations', [
            'deliveries' => $deliveries,
            'suppliers' => $suppliers,
        ])->layout('layouts.app', ['title' => 'Donations']);
    }
}
