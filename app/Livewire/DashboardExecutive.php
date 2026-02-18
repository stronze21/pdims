<?php

namespace App\Livewire;

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugEmergencyPurchase;
use App\Models\Pharmacy\Dispensing\DrugOrder;
use App\Models\Pharmacy\PharmLocation;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;

class DashboardExecutive extends Component
{
    public string $date_range = 'today';
    public string $custom_date_from = '';
    public string $custom_date_to = '';

    // Stats
    public int $near_expiry_count = 0;
    public int $expired_count = 0;
    public int $critical_stock_count = 0;
    public int $near_reorder_count = 0;
    public int $total_stock_items = 0;

    // Dispensing stats
    public int $pending_orders = 0;
    public int $charged_orders = 0;
    public int $issued_orders = 0;
    public int $returned_orders = 0;

    // Queue stats
    public int $queue_waiting = 0;
    public int $queue_preparing = 0;
    public int $queue_charging = 0;
    public int $queue_ready = 0;
    public int $queue_dispensed = 0;
    public int $queue_cancelled = 0;
    public int $queue_total = 0;
    public ?float $avg_wait_time = null;
    public ?float $avg_processing_time = null;

    // Emergency purchases
    public int $emergency_purchase_count = 0;
    public float $emergency_purchase_total = 0;

    // Data collections
    public array $top_drugs = [];
    public array $locations = [];
    public array $expiring_soon = [];
    public array $dispensing_by_type = [];
    public array $daily_dispensing_chart = [];
    public array $queue_status_chart = [];
    public array $stock_status_chart = [];

    public function mount()
    {
        $this->custom_date_from = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->custom_date_to = Carbon::now()->format('Y-m-d');

        $this->loadDashboardData();
    }

    public function updatedDateRange()
    {
        $this->loadDashboardData();
    }

    public function updatedCustomDateFrom()
    {
        if ($this->date_range === 'custom') {
            $this->loadDashboardData();
        }
    }

    public function updatedCustomDateTo()
    {
        if ($this->date_range === 'custom') {
            $this->loadDashboardData();
        }
    }

    public function refreshDashboard()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        [$dateFrom, $dateTo] = $this->getDateRange();

        $this->loadInventoryStats();
        $this->loadDispensingStats($dateFrom, $dateTo);
        $this->loadQueueStats();
        $this->loadTopDrugs($dateFrom, $dateTo);
        $this->loadLocations();
        $this->loadExpiringSoon();
        $this->loadEmergencyPurchases($dateFrom, $dateTo);
        $this->loadDispensingByType($dateFrom, $dateTo);
        $this->loadDailyDispensingChart();
        $this->buildQueueStatusChart();
        $this->buildStockStatusChart();
    }

    private function getDateRange(): array
    {
        return match ($this->date_range) {
            'today' => [
                Carbon::now()->startOfDay(),
                Carbon::now()->endOfDay(),
            ],
            'yesterday' => [
                Carbon::now()->subDay()->startOfDay(),
                Carbon::now()->subDay()->endOfDay(),
            ],
            'this_week' => [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ],
            'last_week' => [
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ],
            'last_month' => [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ],
            'custom' => [
                Carbon::parse($this->custom_date_from)->startOfDay(),
                Carbon::parse($this->custom_date_to)->endOfDay(),
            ],
            default => [
                Carbon::now()->startOfDay(),
                Carbon::now()->endOfDay(),
            ],
        };
    }

    private function loadInventoryStats()
    {
        $sixMonthsFromNow = Carbon::now()->addMonths(6)->format('Y-m-d');

        $this->near_expiry_count = DrugStock::where('exp_date', '<', $sixMonthsFromNow)
            ->where('exp_date', '>', now())
            ->where('stock_bal', '>', 0)
            ->count();

        $this->expired_count = DrugStock::where('exp_date', '<=', now())
            ->where('stock_bal', '>', 0)
            ->count();

        $this->total_stock_items = DrugStock::where('stock_bal', '>', 0)->count();

        // Critical stock - items at or below reorder level
        $this->critical_stock_count = count(DB::connection('hospital')->select("
            SELECT pds.drug_concat, SUM(pds.stock_bal) as stock_bal,
                pds.dmdcomb, pds.dmdctr
            FROM pharm_drug_stocks as pds
            WHERE EXISTS (
                SELECT id FROM pharm_drug_stock_reorder_levels level
                WHERE pds.dmdcomb = level.dmdcomb
                    AND pds.dmdctr = level.dmdctr
                    AND pds.loc_code = level.loc_code
                    AND level.reorder_point > 0
                    AND level.reorder_point >= pds.stock_bal
            )
            AND pds.stock_bal > 0
            GROUP BY pds.drug_concat, pds.dmdcomb, pds.dmdctr
        "));

        // Near reorder level - approaching reorder point
        $this->near_reorder_count = count(DB::connection('hospital')->select("
            SELECT pds.drug_concat, SUM(pds.stock_bal) as stock_bal,
                pds.dmdcomb, pds.dmdctr
            FROM pharm_drug_stocks as pds
            WHERE EXISTS (
                SELECT id FROM pharm_drug_stock_reorder_levels level
                WHERE pds.dmdcomb = level.dmdcomb
                    AND pds.dmdctr = level.dmdctr
                    AND pds.loc_code = level.loc_code
                    AND level.reorder_point > 0
                    AND level.reorder_point < pds.stock_bal
                    AND level.reorder_point < (pds.stock_bal - (pds.stock_bal * 0.3))
            )
            AND pds.stock_bal > 0
            GROUP BY pds.drug_concat, pds.dmdcomb, pds.dmdctr
        "));
    }

    private function loadDispensingStats($dateFrom, $dateTo)
    {
        $from = $dateFrom->format('Y-m-d H:i:s');
        $to = $dateTo->format('Y-m-d H:i:s');

        // Pending orders (estatus = 'U' with no charge code)
        $this->pending_orders = DrugOrder::whereBetween('dodate', [$from, $to])
            ->where('estatus', 'U')
            ->count();

        // Charged orders (estatus = 'P')
        $this->charged_orders = DrugOrder::whereBetween('dodate', [$from, $to])
            ->where('estatus', 'P')
            ->count();

        // Issued orders (estatus = 'S')
        $this->issued_orders = DrugOrder::whereBetween('dodate', [$from, $to])
            ->where('estatus', 'S')
            ->count();

        // Returned orders count
        $this->returned_orders = DB::connection('hospital')->table('hrxoreturn')
            ->whereBetween('returndate', [$from, $to])
            ->count();
    }

    private function loadQueueStats()
    {
        try {
            $todayQueues = PrescriptionQueue::whereDate('queued_at', today());

            $this->queue_total = (clone $todayQueues)->count();
            $this->queue_waiting = (clone $todayQueues)->where('queue_status', 'waiting')->count();
            $this->queue_preparing = (clone $todayQueues)->where('queue_status', 'preparing')->count();
            $this->queue_charging = (clone $todayQueues)->where('queue_status', 'charging')->count();
            $this->queue_ready = (clone $todayQueues)->where('queue_status', 'ready')->count();
            $this->queue_dispensed = (clone $todayQueues)->where('queue_status', 'dispensed')->count();
            $this->queue_cancelled = (clone $todayQueues)->where('queue_status', 'cancelled')->count();

            // Average wait time (queued_at to preparing_at for dispensed queues)
            $dispensedQueues = PrescriptionQueue::whereDate('queued_at', today())
                ->where('queue_status', 'dispensed')
                ->whereNotNull('preparing_at')
                ->get();

            if ($dispensedQueues->count() > 0) {
                $this->avg_wait_time = round($dispensedQueues->avg(function ($q) {
                    return $q->queued_at->diffInMinutes($q->preparing_at);
                }), 1);
            }

            // Average processing time (preparing_at to dispensed_at)
            $processedQueues = PrescriptionQueue::whereDate('queued_at', today())
                ->where('queue_status', 'dispensed')
                ->whereNotNull('preparing_at')
                ->whereNotNull('dispensed_at')
                ->get();

            if ($processedQueues->count() > 0) {
                $this->avg_processing_time = round($processedQueues->avg(function ($q) {
                    return $q->preparing_at->diffInMinutes($q->dispensed_at);
                }), 1);
            }
        } catch (\Exception $e) {
            // Queue table may not exist or be accessible
        }
    }

    private function loadTopDrugs($dateFrom, $dateTo)
    {
        $from = $dateFrom->format('Y-m-d H:i:s');
        $to = $dateTo->format('Y-m-d H:i:s');

        $this->top_drugs = DB::connection('hospital')->select("
            SELECT TOP 10
                dm.drug_concat,
                SUM(rxo.qtyissued) as total_issued,
                COUNT(DISTINCT rxo.enccode) as encounter_count
            FROM hrxo rxo
            INNER JOIN hdmhdr dm ON rxo.dmdcomb = dm.dmdcomb AND rxo.dmdctr = dm.dmdctr
            WHERE rxo.estatus = 'S'
                AND rxo.dodate BETWEEN ? AND ?
                AND rxo.qtyissued > 0
            GROUP BY dm.drug_concat
            ORDER BY total_issued DESC
        ", [$from, $to]);
    }

    private function loadLocations()
    {
        $this->locations = PharmLocation::orderBy('description')
            ->get()
            ->map(function ($location) {
                $stockCount = DrugStock::where('loc_code', $location->id)
                    ->where('stock_bal', '>', 0)
                    ->count();

                return [
                    'id' => $location->id,
                    'description' => $location->description,
                    'stock_items' => $stockCount,
                ];
            })
            ->toArray();
    }

    private function loadExpiringSoon()
    {
        $this->expiring_soon = DB::connection('hospital')->select("
            SELECT TOP 10
                pds.drug_concat,
                pds.exp_date,
                SUM(pds.stock_bal) as stock_bal,
                pds.loc_code
            FROM pharm_drug_stocks pds
            WHERE pds.exp_date > GETDATE()
                AND pds.exp_date < DATEADD(MONTH, 6, GETDATE())
                AND pds.stock_bal > 0
            GROUP BY pds.drug_concat, pds.exp_date, pds.loc_code
            ORDER BY pds.exp_date ASC
        ");
    }

    private function loadEmergencyPurchases($dateFrom, $dateTo)
    {
        $from = $dateFrom->format('Y-m-d H:i:s');
        $to = $dateTo->format('Y-m-d H:i:s');

        $this->emergency_purchase_count = DrugEmergencyPurchase::whereBetween('purchase_date', [$from, $to])->count();

        $this->emergency_purchase_total = (float) DrugEmergencyPurchase::whereBetween('purchase_date', [$from, $to])
            ->sum('total_amount');
    }

    private function loadDispensingByType($dateFrom, $dateTo)
    {
        $from = $dateFrom->format('Y-m-d H:i:s');
        $to = $dateTo->format('Y-m-d H:i:s');

        $this->dispensing_by_type = DB::connection('hospital')->select("
            SELECT
                enctr.toecode as encounter_type,
                COUNT(DISTINCT rxo.enccode) as encounter_count,
                SUM(rxo.qtyissued) as total_qty
            FROM hrxo rxo
            INNER JOIN henctr enctr ON rxo.enccode = enctr.enccode
            WHERE rxo.estatus = 'S'
                AND rxo.dodate BETWEEN ? AND ?
                AND rxo.qtyissued > 0
            GROUP BY enctr.toecode
            ORDER BY total_qty DESC
        ", [$from, $to]);
    }

    private function loadDailyDispensingChart()
    {
        $results = DB::connection('hospital')->select("
            SELECT
                CONVERT(VARCHAR(10), rxo.dodate, 120) as dispense_date,
                COUNT(*) as order_count,
                SUM(rxo.qtyissued) as total_qty
            FROM hrxo rxo
            WHERE rxo.estatus = 'S'
                AND rxo.dodate >= DATEADD(DAY, -7, GETDATE())
                AND rxo.qtyissued > 0
            GROUP BY CONVERT(VARCHAR(10), rxo.dodate, 120)
            ORDER BY dispense_date ASC
        ");

        $labels = [];
        $orderCounts = [];
        $qtys = [];

        foreach ($results as $row) {
            $labels[] = Carbon::parse($row->dispense_date)->format('M d');
            $orderCounts[] = (int) $row->order_count;
            $qtys[] = (float) $row->total_qty;
        }

        $this->daily_dispensing_chart = [
            'labels' => $labels,
            'orders' => $orderCounts,
            'quantities' => $qtys,
        ];
    }

    private function buildQueueStatusChart()
    {
        $this->queue_status_chart = [
            'labels' => ['Waiting', 'Preparing', 'Charging', 'Ready', 'Dispensed', 'Cancelled'],
            'data' => [
                $this->queue_waiting,
                $this->queue_preparing,
                $this->queue_charging,
                $this->queue_ready,
                $this->queue_dispensed,
                $this->queue_cancelled,
            ],
            'colors' => ['#FBBD23', '#3ABFF8', '#6366F1', '#36D399', '#A6ADBB', '#F87272'],
        ];
    }

    private function buildStockStatusChart()
    {
        $goodStock = $this->total_stock_items - $this->near_expiry_count - $this->expired_count;

        $this->stock_status_chart = [
            'labels' => ['Good Stock', 'Near Expiry', 'Expired'],
            'data' => [max(0, $goodStock), $this->near_expiry_count, $this->expired_count],
            'colors' => ['#36D399', '#FBBD23', '#F87272'],
        ];
    }

    public function render()
    {
        return view('livewire.dashboard-executive')
            ->layout('layouts.app', ['title' => 'Executive Dashboard']);
    }
}
