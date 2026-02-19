<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Pharmacy\PharmLocation;

class CriticalStockItems extends Component
{
    public string $location_id = 'all';
    public string $search = '';
    public string $stock_type = 'critical'; // 'critical' or 'near_reorder'
    public array $location_options = [];

    public function mount()
    {
        $this->location_id = request('location_id', 'all');
        $this->stock_type = request('type', 'critical');
        $this->loadLocationOptions();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedLocationId()
    {
        $this->resetPage();
    }

    public function updatedStockType()
    {
        $this->resetPage();
    }

    private function loadLocationOptions()
    {
        $this->location_options = PharmLocation::orderBy('description')
            ->get()
            ->map(fn($loc) => ['id' => (string) $loc->id, 'description' => $loc->description])
            ->toArray();
    }

    public function render()
    {
        $locFilter = $this->location_id !== 'all' ? "WHERE pds.loc_code = ?" : "";
        $locParam = $this->location_id !== 'all' ? [$this->location_id] : [];

        $statusFilter = $this->stock_type === 'near_reorder' ? 'NEAR CRITICAL' : 'CRITICAL';

        $searchHaving = $this->search ? "AND pds.drug_concat LIKE ?" : "";
        $searchParam = $this->search ? ['%' . $this->search . '%'] : [];

        $records = collect(DB::connection('hospital')->select("
            ;WITH DrugAgg AS (
                SELECT
                    pds.drug_concat,
                    pds.loc_code,
                    SUM(pds.stock_bal) AS stock_bal,
                    ROUND(AVG(card.iss), 0) AS avg_iss,
                    loc.description as location_name
                FROM pharm_drug_stocks pds
                LEFT JOIN pharm_drug_stock_cards card
                    ON card.dmdcomb = pds.dmdcomb
                    AND card.dmdctr = pds.dmdctr
                    AND card.loc_code = pds.loc_code
                    AND card.iss > 0
                    AND card.stock_date BETWEEN DATEADD(DAY, -30, GETDATE()) AND GETDATE()
                LEFT JOIN pharm_locations loc ON pds.loc_code = loc.id
                {$locFilter}
                GROUP BY pds.drug_concat, pds.loc_code, loc.description
            )
            SELECT
                drug_concat,
                loc_code,
                location_name,
                stock_bal,
                avg_iss,
                ROUND(avg_iss * 1.5, 0) as reorder_level,
                CASE
                    WHEN stock_bal <= ROUND(avg_iss * 1.5 * 0.3, 0) THEN 'NEAR CRITICAL'
                    WHEN (ROUND(avg_iss * 1.5, 0) - stock_bal) > 0 THEN 'CRITICAL'
                    ELSE 'NORMAL'
                END AS status
            FROM DrugAgg
            WHERE
                CASE
                    WHEN stock_bal <= ROUND(avg_iss * 1.5 * 0.3, 0) THEN 'NEAR CRITICAL'
                    WHEN (ROUND(avg_iss * 1.5, 0) - stock_bal) > 0 THEN 'CRITICAL'
                    ELSE 'NORMAL'
                END = ?
                {$searchHaving}
            ORDER BY stock_bal ASC
        ", array_merge($locParam, [$statusFilter], $searchParam)));

        $title = $this->stock_type === 'near_reorder' ? 'Near Reorder Level Items' : 'Critical Stock Items';

        return view('livewire.dashboard.critical-stock-items', [
            'records' => $records,
            'pageTitle' => $title,
        ])->layout('layouts.app', ['title' => $title]);
    }
}
