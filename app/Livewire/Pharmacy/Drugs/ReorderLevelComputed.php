<?php

namespace App\Livewire\Pharmacy\Drugs;

use App\Models\Pharmacy\Drugs\InOutTransaction;
use App\Models\Pharmacy\PharmLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class ReorderLevelComputed extends Component
{
    use Toast;

    public $search = '';
    public $location_id;

    public function render()
    {
        $from = Carbon::parse(now())->startOfWeek()->format('Y-m-d H:i:s');
        $to = Carbon::parse(now())->endOfWeek()->format('Y-m-d H:i:s');

        $where = $this->location_id
            ? "pds.loc_code = " . intval($this->location_id)
            : "1=1";

        $stocks = DB::select("SELECT
                                pds.drug_concat,
                                SUM(pds.stock_bal) AS stock_bal,
                                MAX(level.reorder_point) AS reorder_point,
                                ROUND(AVG(card.iss), 0) AS average,
                                MIN(card.stock_date) AS from_date,
                                MAX(card.stock_date) AS to_date,
                                ROUND(AVG(card.iss) * 1.5, 0) AS max_level,
                                ROUND((AVG(card.iss) * 1.5) - SUM(pds.stock_bal), 0) AS critical,
                                CASE
                                    WHEN (SUM(pds.stock_bal) <= ROUND((AVG(card.iss) * 1.5) * 0.2, 0))
                                        THEN 'NEAR CRITICAL'
                                    WHEN (ROUND((AVG(card.iss) * 1.5) - SUM(pds.stock_bal), 0)) > 0
                                        THEN 'CRITICAL'
                                    ELSE 'NORMAL'
                                END AS status
                            FROM pharm_drug_stocks pds
                            LEFT JOIN pharm_drug_stock_reorder_levels level
                                ON pds.dmdcomb = level.dmdcomb
                            AND pds.dmdctr = level.dmdctr
                            AND pds.loc_code = level.loc_code
                            LEFT JOIN pharm_drug_stock_cards card
                                ON card.dmdcomb = pds.dmdcomb
                            AND card.dmdctr = pds.dmdctr
                            AND card.loc_code = pds.loc_code
                            AND card.iss > 0
                            AND card.stock_date BETWEEN DATEADD(DAY, -30, GETDATE()) AND GETDATE()
                            AND card.loc_code <> 1
                            WHERE {$where}
                            GROUP BY pds.drug_concat
                            ORDER BY pds.drug_concat
                    ");

        $locations = PharmLocation::all();

        $current_io = InOutTransaction::where('remarks_request', 'Reorder level')
            ->where('trans_stat', 'Requested')
            ->where('loc_code', auth()->user()->pharm_location_id)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return view('livewire.pharmacy.drugs.reorder-level-computed', [
            'stocks' => $stocks,
            'locations' => $locations,
            'current_io' => $current_io,
        ]);
    }

    public function mount()
    {
        $this->location_id = auth()->user()->pharm_location_id;
    }
}
