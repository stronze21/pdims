<?php

namespace App\Livewire\Dashboard;

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Pharmacy\PharmLocation;
use App\Models\Pharmacy\Drugs\DrugEmergencyPurchase;

class EmergencyPurchases extends Component
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

        $locFilter = $this->location_id !== 'all' ? "AND ep.pharm_location_id = ?" : "";
        $searchFilter = $this->search ? "AND dm.drug_concat LIKE ?" : "";

        $params = [$from, $to];
        if ($this->location_id !== 'all') $params[] = $this->location_id;
        if ($this->search) $params[] = '%' . $this->search . '%';

        $records = collect(DB::connection('hospital')->select("
            SELECT
                ep.id,
                ep.or_no,
                ep.pharmacy_name,
                ep.purchase_date,
                dm.drug_concat,
                ep.qty,
                ep.unit_price,
                ep.retail_price,
                ep.total_amount,
                ep.lot_no,
                ep.expiry_date,
                ep.remarks,
                loc.description as location_name,
                cc.chrgdesc as charge_desc
            FROM pharm_drug_emergency_purchases ep
            INNER JOIN hdmhdr dm ON ep.dmdcomb = dm.dmdcomb AND ep.dmdctr = dm.dmdctr
            LEFT JOIN pharm_locations loc ON ep.pharm_location_id = loc.id
            LEFT JOIN hchrgcod cc ON ep.charge_code = cc.chrgcode
            WHERE ep.purchase_date BETWEEN ? AND ?
                {$locFilter}
                {$searchFilter}
            ORDER BY ep.purchase_date DESC
        ", $params));

        $totalAmount = $records->sum('total_amount');

        return view('livewire.dashboard.emergency-purchases', [
            'records' => $records,
            'totalAmount' => $totalAmount,
        ])->layout('layouts.app', ['title' => 'Emergency Purchases']);
    }
}
