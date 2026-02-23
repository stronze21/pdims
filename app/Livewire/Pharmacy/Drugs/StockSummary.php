<?php

namespace App\Livewire\Pharmacy\Drugs;

use App\Models\Pharmacy\Drugs\DrugStockReorderLevel;
use App\Models\Pharmacy\PharmLocation;
use App\Models\References\ChargeCode;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class StockSummary extends Component
{
    use Toast;

    public $search = '';
    public $location_id;
    public $selected_fund = '';
    public $charges;
    public $chrgcode = '';
    public $chrgdesc = '';

    public function updatedSelectedFund()
    {
        if ($this->selected_fund) {
            $fund = $this->selected_fund;
            $selected_fund = explode(',', $fund);
            $this->chrgcode = $selected_fund[0];
            $this->chrgdesc = $selected_fund[1];
        } else {
            $this->chrgcode = '';
            $this->chrgdesc = '';
        }
    }

    public function render()
    {
        $locationFilter = $this->location_id ? intval($this->location_id) : '%';
        $searchFilter = '%' . $this->search . '%';

        if ($this->selected_fund && $this->selected_fund != 'all') {
            $stocks = DB::select("SELECT hcharge.chrgdesc, pds.drug_concat, pds.lot_no, pds.exp_date, SUM(pds.stock_bal) as stock_bal,
                                pds.dmdcomb, pds.dmdctr, pds.chrgcode
                            FROM pharm_drug_stocks as pds
                            JOIN hcharge ON pds.chrgcode = hcharge.chrgcode
                            WHERE pds.stock_bal > 0 AND pds.chrgcode LIKE ?
                                AND pds.loc_code LIKE ?
                                AND pds.drug_concat LIKE ?
                            GROUP BY pds.drug_concat, hcharge.chrgdesc, pds.dmdcomb, pds.dmdctr, pds.chrgcode, pds.lot_no, pds.exp_date
                    ", ['%' . $this->chrgcode, $locationFilter, $searchFilter]);
        } else {
            $stocks = DB::select("SELECT 'ALL' as chrgdesc, pds.drug_concat, SUM(pds.stock_bal) as stock_bal,
                    pds.dmdcomb, pds.dmdctr, pds.lot_no, pds.exp_date
                FROM pharm_drug_stocks as pds
                JOIN hcharge ON pds.chrgcode = hcharge.chrgcode
                WHERE pds.stock_bal > 0 AND pds.loc_code LIKE ?
                    AND pds.drug_concat LIKE ?
                GROUP BY pds.drug_concat, pds.dmdcomb, pds.dmdctr, pds.lot_no, pds.exp_date
            ", [$locationFilter, $searchFilter]);
        }

        $locations = PharmLocation::all();

        return view('livewire.pharmacy.drugs.stock-summary', [
            'stocks' => $stocks,
            'locations' => $locations,
        ]);
    }

    public function mount()
    {
        $this->location_id = auth()->user()->pharm_location_id;

        $this->charges = ChargeCode::where('bentypcod', 'DRUME')
            ->where('chrgstat', 'A')
            ->whereIn('chrgcode', app('chargetable'))
            ->get();
    }

    public function updateReorder($dmdcomb, $dmdctr, $chrgcode, $reorder_point)
    {
        DrugStockReorderLevel::updateOrCreate([
            'dmdcomb' => $dmdcomb,
            'dmdctr' => $dmdctr,
            'chrgcode' => $chrgcode,
        ], [
            'reorder_point' => $reorder_point,
            'user_id' => auth()->id(),
        ]);

        $this->success('Reorder level updated');
    }
}
