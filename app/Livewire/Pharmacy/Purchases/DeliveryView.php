<?php

namespace App\Livewire\Pharmacy\Purchases;

use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\DeliveryDetail;
use App\Models\Pharmacy\DeliveryItems;
use App\Models\Pharmacy\Drugs\DrugPrice;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\ZeroBillingCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class DeliveryView extends Component
{
    use Toast;

    public $delivery_id;
    public $details;

    // Item form fields
    public $dmdcomb;
    public $expiry_date;
    public $qty = 1;
    public $unit_price = 0;
    public $lot_no;
    public $has_compounding = false;
    public $compounding_fee = 0;

    // Edit item fields
    public $editItemId;
    public $edit_lot_no;
    public $edit_expiry_date;
    public $edit_has_compounding = false;
    public $edit_compounding_fee = 0;
    public $editItemName = '';

    // Modals
    public $addItemModal = false;
    public $editItemModal = false;
    public $lockModal = false;

    public function mount($delivery_id)
    {
        $this->delivery_id = $delivery_id;
        $this->expiry_date = date('Y-m-d', strtotime('+1 year'));
        $this->loadDetails();
    }

    private function loadDetails()
    {
        $this->details = DeliveryDetail::where('id', $this->delivery_id)
            ->with(['items.drug', 'items.current_price', 'supplier', 'charge'])
            ->first();
    }

    public function openAddItemModal()
    {
        $this->reset(['dmdcomb', 'qty', 'unit_price', 'lot_no', 'has_compounding', 'compounding_fee']);
        $this->expiry_date = date('Y-m-d', strtotime('+1 year'));
        $this->qty = 1;
        $this->addItemModal = true;
    }

    public function openEditItemModal($itemId)
    {
        $item = DeliveryItems::find($itemId);
        if (!$item) return;

        $this->editItemId = $itemId;
        $this->editItemName = $item->drug ? str_replace('_', ' ', $item->drug->drug_concat) : 'N/A';
        $this->edit_lot_no = $item->lot_no;
        $this->edit_expiry_date = $item->expiry_date;
        $this->edit_has_compounding = false;
        $this->edit_compounding_fee = 0;

        // Detect compounding
        if ($item->current_price && $item->current_price->has_compounding) {
            $this->edit_has_compounding = true;
            $this->edit_compounding_fee = $item->current_price->compounding_fee ?? 0;
        }

        $this->editItemModal = true;
    }

    public function add_item()
    {
        $this->validate([
            'dmdcomb' => 'required',
            'unit_price' => ['required', 'numeric', 'min:0'],
            'qty' => ['required', 'numeric', 'min:1'],
            'expiry_date' => 'required|date',
        ]);

        $unit_cost = $this->unit_price;

        if (ZeroBillingCharge::isZeroBilling($this->details->charge_code)) {
            $markup_price = 0;
            $retail_price = 0;
        } else {
            $markup_price = $this->calculateMarkup($unit_cost);
            $retail_price = $unit_cost + $markup_price;
        }

        if ($this->has_compounding) {
            $this->validate([
                'compounding_fee' => ['required', 'numeric', 'min:0'],
            ]);
            $retail_price += $this->compounding_fee;
        }

        $total_amount = $unit_cost * $this->qty;
        $dm = explode(',', $this->dmdcomb);

        $new_item = new DeliveryItems;
        $new_item->delivery_id = $this->details->id;
        $new_item->dmdcomb = $dm[0];
        $new_item->dmdctr = $dm[1];
        $new_item->qty = $this->qty;
        $new_item->unit_price = $unit_cost;
        $new_item->total_amount = $total_amount;
        $new_item->retail_price = $retail_price;
        $new_item->lot_no = $this->lot_no;
        $new_item->expiry_date = $this->expiry_date;
        $new_item->pharm_location_id = $this->details->pharm_location_id;
        $new_item->charge_code = $this->details->charge_code;
        $new_item->save();

        $attributes = [
            'dmdcomb' => $new_item->dmdcomb,
            'dmdctr' => $new_item->dmdctr,
            'dmhdrsub' => $this->details->charge_code,
            'dmduprice' => $unit_cost,
            'dmselprice' => $new_item->retail_price,
            'expdate' => $new_item->expiry_date,
            'stock_id' => $new_item->id,
            'mark_up' => $markup_price,
            'acquisition_cost' => $unit_cost,
            'has_compounding' => $this->has_compounding,
            'retail_price' => $retail_price,
        ];

        if ($this->has_compounding) {
            $attributes['compounding_fee'] = $this->compounding_fee;
        }

        $new_price = DrugPrice::firstOrCreate($attributes, [
            'dmdprdte' => now(),
        ]);

        $new_item->dmdprdte = $new_price->dmdprdte;
        $new_item->save();

        $this->addItemModal = false;
        $this->loadDetails();
        $this->success('Item added to delivery!');
    }

    public function edit_item()
    {
        $this->validate([
            'edit_lot_no' => 'required|string',
            'edit_expiry_date' => 'required|date',
            'edit_compounding_fee' => ['required_if:edit_has_compounding,true', 'numeric', 'min:0'],
        ]);

        $update_item = DeliveryItems::find($this->editItemId);
        if (!$update_item) return;

        $unit_cost = $update_item->unit_price;

        if (ZeroBillingCharge::isZeroBilling($this->details->charge_code)) {
            $markup_price = 0;
            $retail_price = 0;
        } else {
            $markup_price = $this->calculateMarkup($unit_cost);
            $retail_price = $unit_cost + $markup_price;
        }

        if ($this->edit_has_compounding) {
            $retail_price += $this->edit_compounding_fee;
        }

        $update_item->lot_no = $this->edit_lot_no;
        $update_item->expiry_date = $this->edit_expiry_date;
        $update_item->retail_price = $retail_price;
        $update_item->save();

        $attributes = [
            'dmdcomb' => $update_item->dmdcomb,
            'dmdctr' => $update_item->dmdctr,
            'dmhdrsub' => $this->details->charge_code,
            'dmduprice' => $unit_cost,
            'dmselprice' => $retail_price,
            'expdate' => $update_item->expiry_date,
            'stock_id' => $update_item->id,
            'mark_up' => $markup_price,
            'acquisition_cost' => $unit_cost,
            'has_compounding' => $this->edit_has_compounding,
            'retail_price' => $retail_price,
        ];

        if ($this->edit_has_compounding) {
            $attributes['compounding_fee'] = $this->edit_compounding_fee;
        }

        $price_record = DrugPrice::where('stock_id', $update_item->id)->first();
        if ($price_record) {
            $price_record->fill($attributes);
            $price_record->save();
            $dmdprdte = $price_record->dmdprdte;
        } else {
            $new_price = DrugPrice::create(array_merge($attributes, ['dmdprdte' => now()]));
            $dmdprdte = $new_price->dmdprdte;
        }

        $update_item->dmdprdte = $dmdprdte;
        $update_item->save();

        $this->editItemModal = false;
        $this->loadDetails();
        $this->success('Item updated!');
    }

    public function delete_item($itemId)
    {
        $item = DeliveryItems::find($itemId);
        if ($item) {
            $item->delete();
            $this->loadDetails();
            $this->success('Item deleted.');
        }
    }

    public function save_lock()
    {
        $updated = false;

        foreach ($this->details->items as $item) {
            $drug = Drug::where('dmdcomb', $item->dmdcomb)
                ->where('dmdctr', $item->dmdctr)
                ->first();

            $add_to = DrugStock::firstOrCreate([
                'dmdcomb' => $item->dmdcomb,
                'dmdctr' => $item->dmdctr,
                'loc_code' => $item->pharm_location_id,
                'chrgcode' => $item->charge_code,
                'exp_date' => $item->expiry_date,
                'retail_price' => $item->retail_price,
                'drug_concat' => $drug ? $drug->drug_concat : $item->dmdcomb,
                'dmdnost' => $drug->dmdnost ?? null,
                'strecode' => $drug->strecode ?? null,
                'formcode' => $drug->formcode ?? null,
                'rtecode' => $drug->rtecode ?? null,
                'brandname' => $drug->brandname ?? null,
                'dmdrem' => $drug->dmdrem ?? null,
                'dmdrxot' => $drug->dmdrxot ?? null,
                'gencode' => $drug->generic->gencode ?? null,
                'lot_no' => $item->lot_no,
            ]);
            $add_to->stock_bal += $item->qty;
            $add_to->beg_bal += $item->qty;

            $log = DrugStockLog::firstOrNew([
                'loc_code' => $item->pharm_location_id,
                'dmdcomb' => $add_to->dmdcomb,
                'dmdctr' => $add_to->dmdctr,
                'chrgcode' => $add_to->chrgcode,
                'unit_cost' => $item->unit_price,
                'unit_price' => $item->retail_price,
                'consumption_id' => null,
            ]);
            $log->purchased += $item->qty;
            $add_to->dmdprdte = $item->dmdprdte;

            $log->save();
            $add_to->save();

            // Stock card entry
            $date = Carbon::now()->format('Y-m-d');
            $card = DrugStockCard::firstOrNew([
                'io_trans_ref_no' => $this->details->si_no,
                'chrgcode' => $add_to->chrgcode,
                'loc_code' => $item->pharm_location_id,
                'dmdcomb' => $add_to->dmdcomb,
                'dmdctr' => $add_to->dmdctr,
                'exp_date' => $add_to->exp_date,
                'stock_date' => $date,
                'drug_concat' => $drug ? $drug->drug_concat : $add_to->drug_concat,
                'dmdprdte' => $add_to->dmdprdte,
            ]);
            $card->rec += $item->qty;
            $card->bal += $item->qty;
            $card->save();

            $item->status = 'delivered';
            $item->save();
            $updated = true;
        }

        if ($updated) {
            $this->details->status = 'locked';
            $this->details->save();
            $this->lockModal = false;
            $this->loadDetails();
            $this->success('Successfully updated stocks inventory!');
        } else {
            $this->lockModal = false;
            $this->error('No items to add to stock inventory.');
        }
    }

    private function calculateMarkup($unit_cost)
    {
        if ($unit_cost >= 10000.01) {
            return 1115 + (($unit_cost - 10000) * 0.05);
        } elseif ($unit_cost >= 1000.01) {
            return 215 + (($unit_cost - 1000) * 0.10);
        } elseif ($unit_cost >= 100.01) {
            return 35 + (($unit_cost - 100) * 0.20);
        } elseif ($unit_cost >= 50.01) {
            return 20 + (($unit_cost - 50) * 0.30);
        } else {
            return $unit_cost * 0.40;
        }
    }

    public function render()
    {
        $drugs = DB::table('hdmhdr')
            ->where('dmdstat', 'A')
            ->whereNotNull('drug_concat')
            ->orderBy('drug_concat')
            ->select(['dmdcomb', 'dmdctr', 'drug_concat as drug_name'])
            ->distinct()
            ->get();

        return view('livewire.pharmacy.purchases.delivery-view', [
            'drugs' => $drugs,
        ])->layout('layouts.app', ['title' => 'Delivery View - ' . ($this->details->si_no ?? $this->delivery_id)]);
    }
}
