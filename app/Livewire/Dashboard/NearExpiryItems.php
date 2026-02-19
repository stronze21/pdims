<?php

namespace App\Livewire\Dashboard;

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Pharmacy\PharmLocation;

class NearExpiryItems extends Component
{
    public string $location_id = 'all';
    public string $search = '';
    public array $location_options = [];

    public function mount()
    {
        $this->location_id = request('location_id', 'all');
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

    private function loadLocationOptions()
    {
        $this->location_options = PharmLocation::orderBy('description')
            ->get()
            ->map(fn($loc) => ['id' => (string) $loc->id, 'description' => $loc->description])
            ->toArray();
    }

    public function render()
    {
        $sixMonthsFromNow = Carbon::now()->addMonths(6)->format('Y-m-d');

        $locFilter = $this->location_id !== 'all' ? "AND pds.loc_code = ?" : "";
        $searchFilter = $this->search ? "AND pds.drug_concat LIKE ?" : "";

        $params = [$sixMonthsFromNow];
        if ($this->location_id !== 'all') $params[] = $this->location_id;
        if ($this->search) $params[] = '%' . $this->search . '%';

        $records = collect(DB::connection('hospital')->select("
            SELECT
                pds.id,
                pds.drug_concat,
                pds.exp_date,
                pds.stock_bal,
                pds.chrgcode,
                pds.loc_code,
                pds.lot_no,
                pds.retail_price,
                loc.description as location_name,
                cc.chrgdesc as charge_desc,
                DATEDIFF(DAY, GETDATE(), pds.exp_date) as days_until_expiry
            FROM pharm_drug_stocks pds
            LEFT JOIN pharm_locations loc ON pds.loc_code = loc.id
            LEFT JOIN hcharge cc ON pds.chrgcode = cc.chrgcode
            WHERE pds.exp_date > GETDATE()
                AND pds.exp_date < ?
                AND pds.stock_bal > 0
                {$locFilter}
                {$searchFilter}
            ORDER BY pds.exp_date ASC
        ", $params));

        return view('livewire.dashboard.near-expiry-items', [
            'records' => $records,
        ])->layout('layouts.app', ['title' => 'Items Near Expiry']);
    }
}
