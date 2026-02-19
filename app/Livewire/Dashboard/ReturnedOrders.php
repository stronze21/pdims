<?php

namespace App\Livewire\Dashboard;

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Pharmacy\PharmLocation;

class ReturnedOrders extends Component
{
    public string $date_range = 'today';
    public string $custom_date_from = '';
    public string $custom_date_to = '';
    public string $location_id = 'all';
    public string $search = '';
    public array $location_options = [];

    public function mount()
    {
        $this->date_range = request('date_range', 'today');
        $this->location_id = request('location_id', 'all');
        $this->custom_date_from = request('custom_date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $this->custom_date_to = request('custom_date_to', Carbon::now()->format('Y-m-d'));
        $this->loadLocationOptions();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDateRange()
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

    private function getDateRange(): array
    {
        return match ($this->date_range) {
            'today' => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
            'yesterday' => [Carbon::now()->subDay()->startOfDay(), Carbon::now()->subDay()->endOfDay()],
            'this_week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'last_week' => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
            'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'custom' => [Carbon::parse($this->custom_date_from)->startOfDay(), Carbon::parse($this->custom_date_to)->endOfDay()],
            default => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
        };
    }

    public function render()
    {
        [$dateFrom, $dateTo] = $this->getDateRange();
        $from = $dateFrom->format('Y-m-d H:i:s');
        $to = $dateTo->format('Y-m-d H:i:s');

        $locFilter = $this->location_id !== 'all' ? "AND ret.loc_code = ?" : "";
        $searchFilter = $this->search ? "AND dm.drug_concat LIKE ?" : "";

        $params = [$from, $to];
        if ($this->location_id !== 'all') $params[] = $this->location_id;
        if ($this->search) $params[] = '%' . $this->search . '%';

        $records = collect(DB::connection('hospital')->select("
            SELECT
                ret.docointkey,
                ret.enccode,
                dm.drug_concat,
                ret.qty,
                ret.unitprice as unit_price,
                ret.returndate,
                ret.returnfrom,
                ret.remarks,
                pat.patlast, pat.patfirst, pat.patmiddle,
                enctr.toecode as encounter_type,
                emp.lastname as returned_by_last,
                emp.firstname as returned_by_first
            FROM hrxoreturn ret
            INNER JOIN hdmhdr dm ON ret.dmdcomb = dm.dmdcomb AND ret.dmdctr = dm.dmdctr
            INNER JOIN henctr enctr ON ret.enccode = enctr.enccode
            INNER JOIN hperson pat ON enctr.hpercode = pat.hpercode
            LEFT JOIN hpersonal emp ON ret.returnby = emp.employeeid
            WHERE ret.returndate BETWEEN ? AND ?
                {$locFilter}
                {$searchFilter}
            ORDER BY ret.returndate DESC
        ", $params));

        return view('livewire.dashboard.returned-orders', [
            'records' => $records,
        ])->layout('layouts.app', ['title' => 'Returned Orders']);
    }
}
