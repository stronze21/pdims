<?php

namespace App\Livewire\Pharmacy\Purchases;

use App\Models\Pharmacy\DeliveryDetail;
use App\Models\References\ChargeCode;
use App\Models\References\Supplier;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class DeliveryList extends Component
{
    use WithPagination;
    use Toast;

    public $search = '';
    public $supplier_id = '';

    // Form fields
    public $po_no;
    public $si_no;
    public $delivery_date;
    public $suppcode;
    public $delivery_type = 'procured';
    public $charge_code = 'DRUMA';

    public $addModal = false;

    public function mount()
    {
        $this->delivery_date = date('Y-m-d');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSupplier_id()
    {
        $this->resetPage();
    }

    public function openAddModal()
    {
        $this->reset(['po_no', 'si_no', 'suppcode', 'delivery_type']);
        $this->delivery_date = date('Y-m-d');
        $this->delivery_type = 'procured';
        $this->charge_code = 'DRUMA';
        $this->addModal = true;
    }

    public function add_delivery()
    {
        $this->validate([
            'suppcode' => 'required',
            'charge_code' => 'required',
            'delivery_date' => 'required|date',
        ]);

        $delivery = DeliveryDetail::create([
            'po_no' => $this->po_no,
            'si_no' => $this->si_no,
            'pharm_location_id' => auth()->user()->pharm_location_id,
            'user_id' => auth()->id(),
            'delivery_date' => $this->delivery_date,
            'suppcode' => $this->suppcode,
            'delivery_type' => $this->delivery_type,
            'charge_code' => $this->charge_code,
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
            ->when($this->search, function ($query, $search) {
                $query->where('po_no', 'LIKE', '%' . $search . '%')
                    ->orWhere('si_no', 'LIKE', '%' . $search . '%');
            })
            ->latest()
            ->paginate(15);

        $suppliers = Supplier::orderBy('suppname')->get();
        $charges = ChargeCode::where('bentypcod', 'DRUME')
            ->where('chrgstat', 'A')
            ->whereIn('chrgcode', app('chargetable'))
            ->get();

        return view('livewire.pharmacy.purchases.delivery-list', [
            'deliveries' => $deliveries,
            'suppliers' => $suppliers,
            'charges' => $charges,
        ])->layout('layouts.app', ['title' => 'Deliveries']);
    }
}
