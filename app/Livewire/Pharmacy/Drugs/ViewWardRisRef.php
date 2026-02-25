<?php

namespace App\Livewire\Pharmacy\Drugs;

use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\Drugs\WardRisRequest;
use Livewire\Component;
use Mary\Traits\Toast;

class ViewWardRisRef extends Component
{
    use Toast;

    public $reference_no;

    public function mount($reference_no)
    {
        $this->reference_no = $reference_no;
    }

    public function render()
    {
        $trans = WardRisRequest::with(['charge', 'drug', 'location', 'ward'])
            ->where('loc_code', auth()->user()->pharm_location_id)
            ->where('trans_no', $this->reference_no)
            ->latest()
            ->get();

        return view('livewire.pharmacy.drugs.view-ward-ris-ref', [
            'trans' => $trans,
        ]);
    }

    public function viewByDate($date)
    {
        return $this->redirect(route('inventory.ward-ris.view-date', ['date' => $date]), navigate: true);
    }

    public function cancelIssue($row_id)
    {
        $item = WardRisRequest::find($row_id);
        $drug = DrugStock::find($item->stock_id);
        if ($drug) {
            $drug->stock_bal += $item->issued_qty;
            $drug->save();

            $log = DrugStockLog::firstOrNew([
                'loc_code' => $item->loc_code,
                'dmdcomb' => $item->dmdcomb,
                'dmdctr' => $item->dmdctr,
                'chrgcode' => $item->chrgcode,
                'unit_cost' => $drug->current_price ? $drug->current_price->acquisition_cost : 0,
                'unit_price' => $drug->retail_price,
                'consumption_id' => null,
            ]);
            $log->return_qty += $item->issued_qty;
            $log->save();

            $card = DrugStockCard::firstOrNew([
                'chrgcode' => $item->chrgcode,
                'loc_code' => $item->loc_code,
                'dmdcomb' => $item->dmdcomb,
                'dmdctr' => $item->dmdctr,
                'exp_date' => $drug->exp_date,
                'stock_date' => date('Y-m-d'),
                'drug_concat' => $drug->drug_concat(),
                'dmdprdte' => $drug->dmdprdte,
                'io_trans_ref_no' => 'RIS-' . $item->trans_no,
            ]);
            $card->rec += $item->issued_qty;
            $card->save();

            $item->return_qty = $item->issued_qty;
            $item->issued_qty = 0;
            $item->save();

            $this->success('Items returned successfully!');
        } else {
            $this->error('Item not found!');
        }
    }
}
