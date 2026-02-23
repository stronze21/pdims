<?php

namespace App\Livewire\Pharmacy\Drugs;

use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Models\Pharmacy\Drugs\DrugStockLog;
use App\Models\Pharmacy\Drugs\WardRisRequest;
use App\Models\Pharmacy\PharmLocation;
use App\Models\Pharmacy\RisWard;
use App\Models\References\ChargeCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class WardRisTrans extends Component
{
    use Toast;
    use WithPagination;

    public $search = '';
    public $location_id;
    public $locations;
    public $wards;

    public $reference_no;
    public $ward_id;
    public $stock_id;
    public $issue_qty;
    public $chrgcode = '';
    public $charge_codes;
    public $search_drug = '';

    public $issueMoreModal = false;
    public $issueModal = false;

    public function render()
    {
        $trans = WardRisRequest::whereHas('drug', function ($query) {
            $query->where('drug_concat', 'like', '%' . $this->search . '%');
        })->with('location')
            ->with('charge')
            ->where('loc_code', auth()->user()->pharm_location_id)
            ->latest()
            ->paginate(20);

        $drugs = [];
        if ($this->chrgcode && $this->search_drug) {
            $drugs = DB::select("SELECT TOP 20 hcharge.chrgdesc, pds.drug_concat, SUM(pds.stock_bal) as stock_bal, pds.dmdcomb, pds.dmdctr
                                FROM pharm_drug_stocks as pds
                                JOIN hcharge ON pds.chrgcode = hcharge.chrgcode
                                WHERE pds.loc_code = ? AND pds.drug_concat LIKE ? AND pds.chrgcode = ?
                                GROUP BY pds.drug_concat, pds.loc_code, hcharge.chrgdesc, pds.dmdcomb, pds.dmdctr
                        ", [$this->location_id, $this->search_drug . '%', $this->chrgcode]);
        }

        return view('livewire.pharmacy.drugs.ward-ris-trans', [
            'trans' => $trans,
            'drugs' => $drugs,
        ]);
    }

    public function mount()
    {
        $this->location_id = auth()->user()->pharm_location_id;
        $this->locations = PharmLocation::all();
        $this->wards = RisWard::all();
        $this->charge_codes = ChargeCode::where('bentypcod', 'DRUME')
            ->where('chrgstat', 'A')
            ->whereIn('chrgcode', app('chargetable'))
            ->get();
    }

    public function issueRis($more = null)
    {
        if (!$this->reference_no) {
            $this->reference_no = Carbon::now()->format('y-m-') . (sprintf("%04d", count(WardRisRequest::select(DB::raw('COUNT(trans_no)'))->groupBy('trans_no')->get()) + 1));
        }

        $item_code = explode(',', $this->stock_id);
        $dmdcomb = $item_code[0];
        $dmdctr = $item_code[1];

        $available_qty = DrugStock::where('dmdcomb', $dmdcomb)
            ->where('dmdctr', $dmdctr)
            ->where('chrgcode', $this->chrgcode)
            ->where('exp_date', '>', date('Y-m-d'))
            ->where('loc_code', $this->location_id)
            ->where('stock_bal', '>', '0')
            ->groupBy('chrgcode')
            ->sum('stock_bal');

        $issue_qty = $this->issue_qty;

        if ($available_qty >= $issue_qty) {
            $stocks = DrugStock::where('dmdcomb', $dmdcomb)
                ->where('dmdctr', $dmdctr)
                ->where('chrgcode', $this->chrgcode)
                ->where('loc_code', $this->location_id)
                ->where('exp_date', '>', date('Y-m-d'))
                ->where('stock_bal', '>', '0')
                ->orderBy('exp_date', 'ASC')
                ->get();

            $issued_qty = 0;
            foreach ($stocks as $stock) {
                if ($issue_qty) {
                    if ($issue_qty > $stock->stock_bal) {
                        $trans_qty = $stock->stock_bal;
                        $issue_qty -= $stock->stock_bal;
                        $stock->stock_bal = 0;
                    } else {
                        $trans_qty = $issue_qty;
                        $stock->stock_bal -= $issue_qty;
                        $issue_qty = 0;
                    }

                    $issued_qty += $trans_qty;

                    WardRisRequest::create([
                        'trans_no' => $this->reference_no,
                        'stock_id' => $stock->id,
                        'ris_location_id' => $this->ward_id,
                        'dmdcomb' => $dmdcomb,
                        'dmdctr' => $dmdctr,
                        'loc_code' => $this->location_id,
                        'chrgcode' => $this->chrgcode,
                        'issued_qty' => $issued_qty,
                        'issued_by' => auth()->id(),
                        'dmdprdte' => $stock->dmdprdte,
                    ]);
                    $stock->save();

                    $log = DrugStockLog::firstOrNew([
                        'loc_code' => $this->location_id,
                        'dmdcomb' => $dmdcomb,
                        'dmdctr' => $dmdctr,
                        'chrgcode' => $this->chrgcode,
                        'unit_cost' => $stock->current_price ? $stock->current_price->acquisition_cost : 0,
                        'unit_price' => $stock->retail_price,
                        'consumption_id' => null,
                    ]);
                    $log->ris += $issued_qty;
                    $log->save();

                    $card = DrugStockCard::firstOrNew([
                        'chrgcode' => $this->chrgcode,
                        'loc_code' => $this->location_id,
                        'dmdcomb' => $dmdcomb,
                        'dmdctr' => $dmdctr,
                        'exp_date' => $stock->exp_date,
                        'stock_date' => date('Y-m-d'),
                        'drug_concat' => $stock->drug_concat(),
                        'dmdprdte' => $stock->dmdprdte,
                        'io_trans_ref_no' => 'RIS-' . $this->reference_no,
                    ]);
                    $card->iss += $issued_qty;
                    $card->bal -= $issued_qty;
                    $card->save();
                }
            }

            if ($more) {
                $this->success('Issued successfully and append to reference no: ' . $this->reference_no);
                $this->reset('stock_id', 'issue_qty');
                $this->issueModal = false;
                $this->issueMoreModal = true;
            } else {
                $this->success('Request issued successfully!');
                $this->reset('reference_no', 'ward_id', 'stock_id', 'issue_qty', 'chrgcode');
                $this->issueModal = false;
            }
        } else {
            $this->error('Stock balance insufficient!');
        }
    }

    public function append()
    {
        $ris = WardRisRequest::where('loc_code', $this->location_id)->latest()->first();
        if ($ris) {
            $this->reference_no = $ris->trans_no;
            $this->ward_id = $ris->ris_location_id;
            $this->issueMoreModal = true;
        } else {
            $this->error('No RIS found!');
        }
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
