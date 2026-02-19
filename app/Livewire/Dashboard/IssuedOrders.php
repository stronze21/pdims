<?php

namespace App\Livewire\Dashboard;

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Pharmacy\PharmLocation;

class IssuedOrders extends Component
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

        $locFilter = $this->location_id !== 'all' ? "AND rxo.loc_code = ?" : "";
        $searchFilter = $this->search ? "AND dm.drug_concat LIKE ?" : "";

        $params = [$from, $to];
        if ($this->location_id !== 'all') $params[] = $this->location_id;
        if ($this->search) $params[] = '%' . $this->search . '%';

        $records = collect(DB::connection('hospital')->select("
            SELECT
                rxo.docointkey,
                rxo.enccode,
                dm.drug_concat,
                rxo.qtyissued,
                rxo.pchrgup as unit_price,
                rxo.pcchrgamt as total_amount,
                rxo.dodate,
                rxo.dodtepost as date_issued,
                rxo.pcchrgcod,
                rxo.loc_code,
                pat.patlast, pat.patfirst, pat.patmiddle,
                enctr.toecode as encounter_type,
                loc.description as location_name
            FROM hrxo rxo
            INNER JOIN hdmhdr dm ON rxo.dmdcomb = dm.dmdcomb AND rxo.dmdctr = dm.dmdctr
            INNER JOIN henctr enctr ON rxo.enccode = enctr.enccode
            INNER JOIN hperson pat ON enctr.hpercode = pat.hpercode
            LEFT JOIN pharm_locations loc ON rxo.loc_code = loc.id
            WHERE rxo.estatus = 'S'
                AND rxo.dodate BETWEEN ? AND ?
                AND rxo.qtyissued > 0
                {$locFilter}
                {$searchFilter}
            ORDER BY rxo.dodate DESC
        ", $params));

        return view('livewire.dashboard.issued-orders', [
            'records' => $records,
        ])->layout('layouts.app', ['title' => 'Issued / Dispensed Orders']);
    }
}
