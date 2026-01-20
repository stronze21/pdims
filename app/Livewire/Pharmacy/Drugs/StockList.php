<?php

namespace App\Livewire\Pharmacy\Drugs;

use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\DrugPrice;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\Drugs\PullOut;
use App\Models\Pharmacy\Drugs\PullOutItem;
use App\Models\Pharmacy\PharmLocation;
use App\Models\Pharmacy\ZeroBillingCharge;
use App\Models\References\ChargeCode;
use App\Models\StockAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class StockList extends Component
{
    use Toast;

    public $location_id;
    public $dmdcomb, $chrgcode, $expiry_date, $qty, $unit_cost, $lot_no;
    public $has_compounding = false, $compounding_fee = 0;

    public $drugs, $locations, $charge_codes;

    // Modals
    public $addModal = false;
    public $updateModal = false;
    public $adjustModal = false;
    public $pulloutModal = false;

    public $selectedStockId;
    public $selectedStockName;
    public $selectedStockExpiry;
    public $selectedStockBalance;
    public $selectedStockChrgcode;

    public function mount()
    {
        $this->location_id = request('location_id', auth()->user()->pharm_location_id);
        $this->locations = PharmLocation::all();
        $this->charge_codes = ChargeCode::where('bentypcod', 'DRUME')
            ->where('chrgstat', 'A')
            ->whereIn('chrgcode', app('chargetable'))
            ->get();
        $this->drugs = DB::table('hdmhdr')
            ->join('hdruggrp', 'hdruggrp.grpcode', '=', 'hdmhdr.grpcode')
            ->join('hgen', 'hgen.gencode', '=', 'hdruggrp.gencode')
            ->where('hdmhdr.dmdstat', 'A')
            ->whereNotNull('hdmhdr.drug_concat')
            ->orderBy('hdmhdr.drug_concat')
            ->select([
                'hdmhdr.dmdcomb',
                'hdmhdr.dmdctr',
                'hdmhdr.drug_concat as drug_name',
            ])
            ->distinct()
            ->get();
    }

    public function updatedLocationId($value)
    {
        $this->location_id = $value;
        $this->dispatch('location-changed', locationId: $value);
    }

    public function openAddModal()
    {
        $this->resetForm();
        $this->addModal = true;
    }

    public function openUpdateModal($id, $name, $chrgcode, $expiry, $balance, $cost, $hasCompounding, $compoundingFee, $lotNo)
    {
        $this->selectedStockId = $id;
        $this->selectedStockName = $name;
        $this->chrgcode = $chrgcode;
        $this->expiry_date = $expiry;
        $this->qty = $balance;
        $this->unit_cost = $cost;
        $this->has_compounding = $hasCompounding == 1;
        $this->compounding_fee = $compoundingFee;
        $this->lot_no = $lotNo;
        $this->updateModal = true;
    }

    public function openAdjustModal($id, $name, $chrgcode, $expiry, $balance)
    {
        $this->selectedStockId = $id;
        $this->selectedStockName = $name;
        $this->selectedStockChrgcode = $chrgcode;
        $this->selectedStockExpiry = $expiry;
        $this->qty = $balance;
        $this->adjustModal = true;
    }

    public function openPulloutModal($id, $name, $chrgcode, $expiry, $balance)
    {
        $this->selectedStockId = $id;
        $this->selectedStockName = $name;
        $this->selectedStockChrgcode = $chrgcode;
        $this->selectedStockExpiry = $expiry;
        $this->qty = $balance;
        $this->pulloutModal = true;
    }

    public function add_item()
    {
        $this->validate([
            'dmdcomb' => 'required',
            'unit_cost' => 'required',
            'qty' => ['required', 'numeric', 'min:0'],
            'expiry_date' => 'required',
            'chrgcode' => 'required',
        ]);

        if (ZeroBillingCharge::isZeroBilling($this->chrgcode)) {
            $retail_price = 0;
            $markup_price = 0;
        } else {
            $retail_price = $this->calculateRetailPrice($this->unit_cost);
            $markup_price = $this->calculateMarkup($this->unit_cost);
        }

        if ($this->has_compounding) {
            $this->validate(['compounding_fee' => ['required', 'numeric', 'min:0']]);
            $retail_price += $this->compounding_fee;
        }

        $dm = explode(',', $this->dmdcomb);
        $drug = Drug::where('dmdcomb', $dm[0])->where('dmdctr', $dm[1])->first();

        $stock = DrugStock::firstOrCreate([
            'dmdcomb' => $dm[0],
            'dmdctr' => $dm[1],
            'loc_code' => $this->location_id,
            'chrgcode' => $this->chrgcode,
            'exp_date' => $this->expiry_date,
            'retail_price' => $retail_price,
            'drug_concat' => $drug->drug_name,
            'dmdnost' => $drug->dmdnost,
            'strecode' => $drug->strecode,
            'formcode' => $drug->formcode,
            'rtecode' => $drug->rtecode,
            'brandname' => $drug->brandname,
            'dmdrem' => $drug->dmdrem,
            'dmdrxot' => $drug->dmdrxot,
            'gencode' => $drug->generic->gencode,
            'lot_no' => $this->lot_no,
        ]);

        $stock->stock_bal += $this->qty;
        $stock->beg_bal += $this->qty;

        $attributes = $this->resolvePriceAttributes(
            $stock,
            null,
            $retail_price,
            $markup_price
        );

        $new_price = DrugPrice::firstOrCreate($attributes, ['dmdprdte' => now()]);
        $stock->dmdprdte = $new_price->dmdprdte;
        $stock->save();

        $this->handleLog($this->location_id, $stock->dmdcomb, $stock->dmdctr, $stock->chrgcode, date('Y-m-d'), $new_price->dmdprdte, $this->unit_cost, $retail_price, $this->qty, $stock->id, $stock->exp_date, $drug->drug_concat(), date('Y-m-d'), null);

        $this->addModal = false;
        $this->dispatch('refresh-stocks');
        $this->success('Item beginning balance has been saved!');
    }

    public function update_item()
    {
        $stock = DrugStock::find($this->selectedStockId);

        $this->validate([
            'unit_cost' => 'required',
            'qty' => ['required', 'numeric', 'min:0'],
            'expiry_date' => 'required',
            'chrgcode' => 'required',
        ]);

        $old_chrgcode = $stock->chrgcode;
        $old_stock_bal = $stock->stock_bal;
        $previous_qty = $stock->stock_bal;

        if (ZeroBillingCharge::isZeroBilling($this->chrgcode)) {
            $retail_price = 0;
            $markup_price = 0;
        } else {
            $retail_price = $this->calculateRetailPrice($this->unit_cost);
            $markup_price = $this->calculateMarkup($this->unit_cost);
        }

        if ($this->has_compounding) {
            $this->validate(['compounding_fee' => ['required', 'numeric', 'min:0']]);
            $retail_price += $this->compounding_fee;
        }

        $stock->beg_bal = 0;
        $stock->stock_bal = $this->qty;
        $stock->beg_bal = $this->qty;
        $stock->lot_no = $this->lot_no;
        $stock->chrgcode = $this->chrgcode;
        $stock->exp_date = $this->expiry_date;
        $stock->retail_price = $retail_price;

        $attributes = $this->resolvePriceAttributes(
            $stock,
            null,
            $retail_price,
            $markup_price
        );

        $new_price = DrugPrice::firstOrCreate(
            $attributes,
            ['dmdprdte' => now()]
        );

        $old_log = DrugStockLog::where('loc_code', $this->location_id)
            ->where('dmdcomb', $stock->dmdcomb)
            ->where('dmdctr', $stock->dmdctr)
            ->where('chrgcode', $old_chrgcode)
            ->first();

        if ($old_log) {
            $old_log->beg_bal -= $old_stock_bal;
            $old_log->save();
        }

        $log = DrugStockLog::firstOrNew([
            'loc_code' => $this->location_id,
            'dmdcomb' => $stock->dmdcomb,
            'dmdctr' => $stock->dmdctr,
            'chrgcode' => $stock->chrgcode,
            'unit_cost' => $this->unit_cost,
            'unit_price' => $retail_price,
            'consumption_id' => null,
        ]);
        $log->beg_bal += $this->qty;
        $stock->dmdprdte = $new_price->dmdprdte;

        StockAdjustment::create([
            'stock_id' => $stock->id,
            'user_id' => auth()->id(),
            'from_qty' => $previous_qty,
            'to_qty' => $this->qty,
        ]);

        $log->save();
        $stock->save();

        $this->updateModal = false;
        $this->dispatch('refresh-stocks');
        $this->success('Item beginning balance has been updated!');
    }

    private function resolvePriceAttributes($stock, $drug, $retail_price, $markup_price)
    {
        $attributes = [
            'dmdcomb' => $stock->dmdcomb,
            'dmdctr' => $stock->dmdctr,
            'dmhdrsub' => $this->chrgcode,
            'dmduprice' => $this->unit_cost,
            'dmselprice' => $retail_price,
            'expdate' => $stock->exp_date,
            'mark_up' => $markup_price,
            'acquisition_cost' => $this->unit_cost,
            'has_compounding' => $this->has_compounding,
            'retail_price' => $retail_price,
        ];

        if ($this->has_compounding) {
            $attributes['compounding_fee'] = $this->compounding_fee;
        }

        return $attributes;
    }

    public function adjust_qty()
    {
        $stock = DrugStock::find($this->selectedStockId);
        $current_bal = $stock->stock_bal;

        $stock->stock_bal = $this->qty;
        $stock->save();

        StockAdjustment::create([
            'stock_id' => $this->selectedStockId,
            'user_id' => auth()->id(),
            'from_qty' => $current_bal,
            'to_qty' => $this->qty,
        ]);

        $this->adjustModal = false;
        $this->dispatch('refresh-stocks');
        $this->success('Stock adjusted successfully!');
    }

    public function pull_out()
    {
        $pullout_date = Carbon::now()->format('Y-m-d');
        $detail = PullOut::firstOrCreate([
            'pullout_date' => $pullout_date,
            'pharm_location_id' => $this->location_id,
        ]);

        $stock = DrugStock::find($this->selectedStockId);
        PullOutItem::create([
            'detail_id' => $detail->id,
            'stock_id' => $stock->id,
            'pullout_qty' => $this->qty,
        ]);

        $card = DrugStockCard::firstOrNew([
            'loc_code' => $this->location_id,
            'dmdcomb' => $stock->dmdcomb,
            'dmdctr' => $stock->dmdctr,
            'chrgcode' => $stock->chrgcode,
            'exp_date' => $stock->exp_date,
            'stock_date' => $pullout_date,
            'drug_concat' => $stock->drug_concat(),
            'dmdprdte' => $stock->dmdprdte,
        ]);
        $card->pullout_qty += $this->qty;
        $card->save();

        $log = DrugStockLog::firstOrNew([
            'loc_code' => $this->location_id,
            'dmdcomb' => $stock->dmdcomb,
            'dmdctr' => $stock->dmdctr,
            'chrgcode' => $stock->chrgcode,
            'unit_cost' => $stock->current_price->acquisition_cost,
            'unit_price' => $stock->retail_price,
            'consumption_id' => null,
        ]);
        $log->pullout_qty += $this->qty;
        $log->save();

        $stock->stock_bal -= $this->qty;
        $stock->save();

        $this->pulloutModal = false;
        $this->dispatch('refresh-stocks');
        $this->success('Item pulled out successfully!');
    }

    public function sync_items()
    {
        Artisan::call('init:drugconcat');
        $this->dispatch('refresh-stocks');
        $this->success('Items in sync');
    }

    private function resetForm()
    {
        $this->reset(['dmdcomb', 'unit_cost', 'qty', 'expiry_date', 'chrgcode', 'has_compounding', 'compounding_fee', 'lot_no', 'selectedStockId', 'selectedStockName', 'selectedStockExpiry', 'selectedStockBalance', 'selectedStockChrgcode']);
    }

    private function calculateRetailPrice($unit_cost)
    {
        $markup_price = $this->calculateMarkup($unit_cost);
        return $unit_cost + $markup_price;
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

    private function handleLog($pharm_location_id, $dmdcomb, $dmdctr, $chrgcode, $trans_date, $dmdprdte, $unit_cost, $retail_price, $qty, $stock_id, $exp_date, $drug_concat, $date, $active_consumption = null)
    {
        $date = Carbon::parse($trans_date)->startOfMonth()->format('Y-m-d');

        $log = DrugStockLog::firstOrNew([
            'loc_code' => $pharm_location_id,
            'dmdcomb' => $dmdcomb,
            'dmdctr' => $dmdctr,
            'chrgcode' => $chrgcode,
            'unit_cost' => $unit_cost,
            'unit_price' => $retail_price,
            'consumption_id' => $active_consumption,
        ]);
        $log->beg_bal += $qty;
        $log->save();

        $card = DrugStockCard::firstOrNew([
            'chrgcode' => $chrgcode,
            'loc_code' => $pharm_location_id,
            'dmdcomb' => $dmdcomb,
            'dmdctr' => $dmdctr,
            'exp_date' => $exp_date,
            'stock_date' => $date,
            'drug_concat' => $drug_concat,
            'dmdprdte' => $dmdprdte,
        ]);
        $card->reference += $qty;
        $card->bal += $qty;
        $card->save();
    }

    public function render()
    {
        return view('livewire.pharmacy.drugs.stock-list');
    }
}
