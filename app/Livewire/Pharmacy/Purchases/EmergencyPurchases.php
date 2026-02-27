<?php

namespace App\Livewire\Pharmacy\Purchases;

use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\Drugs\DrugEmergencyPurchase;
use App\Models\Pharmacy\Drugs\DrugPrice;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\ZeroBillingCharge;
use App\Models\References\ChargeCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class EmergencyPurchases extends Component
{
    use WithPagination;
    use Toast;

    public $search = '';

    // Form fields
    public $purchase_date;
    public $or_no;
    public $pharmacy_name;
    public $charge_code = 'DRUMC';
    public $dmdcomb;
    public $expiry_date;
    public $qty;
    public $unit_price;
    public $lot_no;
    public $has_compounding = false;
    public $compounding_fee = 0;
    public $remarks;

    // Modals
    public $addModal = false;
    public $pushModal = false;
    public $pushPurchaseId = null;

    // Data
    public $drugs;
    public $charges;

    public function mount()
    {
        $this->purchase_date = date('Y-m-d');
        $this->expiry_date = date('Y-m-d', strtotime('+1 year'));
        $this->loadFormData();
    }

    private function loadFormData()
    {
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

        $this->charges = ChargeCode::where('bentypcod', 'DRUME')
            ->where('chrgstat', 'A')
            ->whereIn('chrgcode', app('chargetable'))
            ->get();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function openAddModal()
    {
        $this->resetForm();
        $this->addModal = true;
    }

    public function openPushModal($purchaseId)
    {
        $this->pushPurchaseId = $purchaseId;
        $this->pushModal = true;
    }

    public function new_ep()
    {
        $this->validate([
            'purchase_date' => ['required', 'date', 'before_or_equal:' . now()->format('Y-m-d')],
            'or_no' => ['required', 'string', 'max:10', 'min:1'],
            'pharmacy_name' => ['required', 'string', 'max:100'],
            'charge_code' => ['required', 'string', 'max:6'],
            'dmdcomb' => ['required'],
            'expiry_date' => ['required', 'date', 'after:' . now()->format('Y-m-d')],
            'qty' => ['required', 'numeric', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:1'],
            'lot_no' => ['nullable', 'string', 'max:10'],
        ]);

        $unit_cost = $this->unit_price;

        if (ZeroBillingCharge::isZeroBilling($this->charge_code)) {
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

        $dm = explode(',', $this->dmdcomb);
        $total_amount = $unit_cost * $this->qty;

        $new_ep = DrugEmergencyPurchase::create([
            'or_no' => $this->or_no,
            'pharmacy_name' => $this->pharmacy_name,
            'user_id' => auth()->id(),
            'purchase_date' => $this->purchase_date,
            'dmdcomb' => $dm[0],
            'dmdctr' => $dm[1],
            'qty' => $this->qty,
            'unit_price' => $unit_cost,
            'total_amount' => $total_amount,
            'markup_price' => $markup_price,
            'retail_price' => $retail_price,
            'lot_no' => $this->lot_no,
            'expiry_date' => $this->expiry_date,
            'charge_code' => $this->charge_code,
            'pharm_location_id' => auth()->user()->pharm_location_id,
            'remarks' => $this->remarks,
        ]);

        $new_price = new DrugPrice;
        $new_price->dmdcomb = $new_ep->dmdcomb;
        $new_price->dmdctr = $new_ep->dmdctr;
        $new_price->dmhdrsub = $new_ep->charge_code;
        $new_price->dmduprice = $unit_cost;
        $new_price->dmselprice = $retail_price;
        $new_price->dmdprdte = now();
        $new_price->expdate = $new_ep->expiry_date;
        $new_price->stock_id = $new_ep->id;
        $new_price->mark_up = $markup_price;
        $new_price->acquisition_cost = $unit_cost;
        $new_price->has_compounding = $this->has_compounding;
        if ($this->has_compounding) {
            $new_price->compounding_fee = $this->compounding_fee;
        }
        $new_price->retail_price = $retail_price;
        $new_price->save();

        $new_ep->dmdprdte = $new_price->dmdprdte;
        $new_ep->save();

        $this->addModal = false;
        $this->resetForm();
        $this->success('Emergency purchase saved!');
    }

    public function push()
    {
        $purchase = DrugEmergencyPurchase::findOrFail($this->pushPurchaseId);

        $drug = Drug::where('dmdcomb', $purchase->dmdcomb)
            ->where('dmdctr', $purchase->dmdctr)
            ->first();

        $add_to = DrugStock::firstOrCreate([
            'dmdcomb' => $purchase->dmdcomb,
            'dmdctr' => $purchase->dmdctr,
            'loc_code' => $purchase->pharm_location_id,
            'chrgcode' => $purchase->charge_code,
            'exp_date' => $purchase->expiry_date,
            'retail_price' => $purchase->retail_price,
            'drug_concat' => $drug ? $drug->drug_concat : $purchase->dmdcomb,
            'dmdnost' => $drug->dmdnost ?? null,
            'strecode' => $drug->strecode ?? null,
            'formcode' => $drug->formcode ?? null,
            'rtecode' => $drug->rtecode ?? null,
            'brandname' => $drug->brandname ?? null,
            'dmdrem' => $drug->dmdrem ?? null,
            'dmdrxot' => $drug->dmdrxot ?? null,
            'gencode' => $drug->generic->gencode ?? null,
            'lot_no' => $purchase->lot_no,
        ]);

        $add_to->stock_bal += $purchase->qty;
        $add_to->beg_bal += $purchase->qty;

        $log = DrugStockLog::firstOrNew([
            'loc_code' => $purchase->pharm_location_id,
            'dmdcomb' => $add_to->dmdcomb,
            'dmdctr' => $add_to->dmdctr,
            'chrgcode' => $add_to->chrgcode,
            'unit_cost' => $purchase->unit_price,
            'unit_price' => $purchase->retail_price,
            'consumption_id' => null,
        ]);
        $log->purchased += $purchase->qty;

        $add_to->dmdprdte = $purchase->dmdprdte;

        $log->save();
        $add_to->save();

        $date = Carbon::now()->startOfMonth()->format('Y-m-d');
        $card = DrugStockCard::firstOrNew([
            'chrgcode' => $add_to->chrgcode,
            'loc_code' => $purchase->pharm_location_id,
            'dmdcomb' => $add_to->dmdcomb,
            'dmdctr' => $add_to->dmdctr,
            'exp_date' => $add_to->exp_date,
            'stock_date' => $date,
            'drug_concat' => $drug ? $drug->drug_concat() : $add_to->drug_concat,
            'dmdprdte' => $add_to->dmdprdte,
        ]);
        $card->reference += $purchase->qty;
        $card->bal += $purchase->qty;
        $card->save();

        $purchase->status = 'pushed';
        $purchase->save();

        $this->pushModal = false;
        $this->pushPurchaseId = null;
        $this->success('Successfully pushed to stocks inventory!');
    }

    public function cancel_purchase()
    {
        $purchase = DrugEmergencyPurchase::findOrFail($this->pushPurchaseId);
        $purchase->status = 'cancelled';
        $purchase->save();

        $this->pushModal = false;
        $this->pushPurchaseId = null;
        $this->success('Emergency purchase cancelled.');
    }

    private function resetForm()
    {
        $this->reset([
            'or_no', 'pharmacy_name', 'dmdcomb', 'qty', 'unit_price',
            'lot_no', 'has_compounding', 'compounding_fee', 'remarks',
        ]);
        $this->purchase_date = date('Y-m-d');
        $this->expiry_date = date('Y-m-d', strtotime('+1 year'));
        $this->charge_code = 'DRUMC';
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
        $purchases = DrugEmergencyPurchase::with(['drug', 'current_price', 'charge', 'user'])
            ->where('pharm_location_id', auth()->user()->pharm_location_id)
            ->when($this->search, function ($query) {
                $query->whereHas('drug', function ($q) {
                    $q->where('drug_concat', 'LIKE', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.pharmacy.purchases.emergency-purchases', [
            'purchases' => $purchases,
        ])->layout('layouts.app', ['title' => 'Emergency Purchases']);
    }
}
