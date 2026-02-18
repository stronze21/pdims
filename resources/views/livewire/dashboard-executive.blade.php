<div>
    {{-- Header with date range filter --}}
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Executive Dashboard</h1>
            <p class="text-sm opacity-60">Pharmacy operations overview</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <select class="select select-sm select-bordered" wire:model.live="date_range">
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="this_week">This Week</option>
                <option value="last_week">Last Week</option>
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="custom">Custom Range</option>
            </select>

            @if ($date_range === 'custom')
                <input type="date" class="input input-sm input-bordered" wire:model.live.debounce.500ms="custom_date_from">
                <span class="text-sm">to</span>
                <input type="date" class="input input-sm input-bordered" wire:model.live.debounce.500ms="custom_date_to">
            @endif

            <button class="btn btn-sm btn-ghost" wire:click="refreshDashboard" title="Refresh">
                <x-mary-icon name="o-arrow-path" class="w-4 h-4" />
            </button>
        </div>
    </div>

    {{-- Loading overlay --}}
    <div wire:loading.flex class="fixed inset-0 z-50 items-center justify-center bg-base-100/50">
        <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    {{-- ============================================ --}}
    {{-- SECTION 1: Inventory KPI Cards --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Total Stock Items --}}
        <div class="shadow-sm card bg-base-100">
            <div class="p-4 card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase opacity-60">Total Stock Items</p>
                        <p class="mt-1 text-3xl font-bold">{{ number_format($total_stock_items) }}</p>
                    </div>
                    <div class="p-3 rounded-xl bg-primary/10">
                        <x-mary-icon name="o-cube" class="w-6 h-6 text-primary" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Near Expiry --}}
        <div class="shadow-sm card bg-base-100">
            <div class="p-4 card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase opacity-60">Near Expiry</p>
                        <p class="mt-1 text-3xl font-bold text-warning">{{ number_format($near_expiry_count) }}</p>
                        <p class="text-xs opacity-50">within 6 months</p>
                    </div>
                    <div class="p-3 rounded-xl bg-warning/10">
                        <x-mary-icon name="o-clock" class="w-6 h-6 text-warning" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Expired --}}
        <div class="shadow-sm card bg-base-100">
            <div class="p-4 card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase opacity-60">Expired Items</p>
                        <p class="mt-1 text-3xl font-bold text-error">{{ number_format($expired_count) }}</p>
                        <p class="text-xs opacity-50">with remaining stock</p>
                    </div>
                    <div class="p-3 rounded-xl bg-error/10">
                        <x-mary-icon name="o-x-circle" class="w-6 h-6 text-error" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Critical Stock --}}
        <div class="shadow-sm card bg-base-100">
            <div class="p-4 card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase opacity-60">Critical Stock</p>
                        <p class="mt-1 text-3xl font-bold text-error">{{ number_format($critical_stock_count) }}</p>
                        <p class="text-xs opacity-50">at/below reorder level</p>
                    </div>
                    <div class="p-3 rounded-xl bg-error/10">
                        <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6 text-error" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- SECTION 2: Dispensing Activity KPI Cards --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Pending Orders --}}
        <div class="shadow-sm card bg-base-100">
            <div class="p-4 card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase opacity-60">Pending Orders</p>
                        <p class="mt-1 text-3xl font-bold text-warning">{{ number_format($pending_orders) }}</p>
                    </div>
                    <div class="p-3 rounded-xl bg-warning/10">
                        <x-mary-icon name="o-document-text" class="w-6 h-6 text-warning" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Charged Orders --}}
        <div class="shadow-sm card bg-base-100">
            <div class="p-4 card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase opacity-60">Charged Orders</p>
                        <p class="mt-1 text-3xl font-bold text-info">{{ number_format($charged_orders) }}</p>
                    </div>
                    <div class="p-3 rounded-xl bg-info/10">
                        <x-mary-icon name="o-clipboard-document-check" class="w-6 h-6 text-info" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Issued Orders --}}
        <div class="shadow-sm card bg-base-100">
            <div class="p-4 card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase opacity-60">Issued / Dispensed</p>
                        <p class="mt-1 text-3xl font-bold text-success">{{ number_format($issued_orders) }}</p>
                    </div>
                    <div class="p-3 rounded-xl bg-success/10">
                        <x-mary-icon name="o-check-circle" class="w-6 h-6 text-success" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Returns --}}
        <div class="shadow-sm card bg-base-100">
            <div class="p-4 card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase opacity-60">Returns</p>
                        <p class="mt-1 text-3xl font-bold">{{ number_format($returned_orders) }}</p>
                    </div>
                    <div class="p-3 rounded-xl bg-base-200">
                        <x-mary-icon name="o-arrow-uturn-left" class="w-6 h-6 opacity-60" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- SECTION 3: Charts Row --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-1 gap-4 mb-6 lg:grid-cols-2">
        {{-- 7-Day Dispensing Trend --}}
        <div class="shadow-sm card bg-base-100">
            <div class="card-body">
                <h2 class="card-title text-base">7-Day Dispensing Trend</h2>
                <div class="w-full h-64" x-data="dispensingChart()" x-init="init()">
                    <canvas x-ref="dispensingCanvas"></canvas>
                </div>
            </div>
        </div>

        {{-- Stock Status Distribution --}}
        <div class="shadow-sm card bg-base-100">
            <div class="card-body">
                <h2 class="card-title text-base">Stock Status Distribution</h2>
                <div class="w-full h-64" x-data="stockStatusChart()" x-init="init()">
                    <canvas x-ref="stockCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- SECTION 4: Queue Performance & Dispensing by Type --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-1 gap-4 mb-6 lg:grid-cols-3">
        {{-- Queue Performance (Today) --}}
        <div class="shadow-sm card bg-base-100">
            <div class="card-body">
                <h2 class="card-title text-base">Queue Performance (Today)</h2>

                @if ($queue_total > 0)
                    <div class="mt-2 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Total Queued</span>
                            <span class="font-bold">{{ $queue_total }}</span>
                        </div>

                        {{-- Status breakdown --}}
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="badge badge-warning badge-sm">Waiting</div>
                                <progress class="flex-1 progress progress-warning" value="{{ $queue_waiting }}" max="{{ $queue_total }}"></progress>
                                <span class="text-sm font-semibold w-8 text-right">{{ $queue_waiting }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="badge badge-info badge-sm">Preparing</div>
                                <progress class="flex-1 progress progress-info" value="{{ $queue_preparing }}" max="{{ $queue_total }}"></progress>
                                <span class="text-sm font-semibold w-8 text-right">{{ $queue_preparing }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="badge badge-secondary badge-sm">Charging</div>
                                <progress class="flex-1 progress progress-secondary" value="{{ $queue_charging }}" max="{{ $queue_total }}"></progress>
                                <span class="text-sm font-semibold w-8 text-right">{{ $queue_charging }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="badge badge-success badge-sm">Ready</div>
                                <progress class="flex-1 progress progress-success" value="{{ $queue_ready }}" max="{{ $queue_total }}"></progress>
                                <span class="text-sm font-semibold w-8 text-right">{{ $queue_ready }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="badge badge-ghost badge-sm">Dispensed</div>
                                <progress class="flex-1 progress" value="{{ $queue_dispensed }}" max="{{ $queue_total }}"></progress>
                                <span class="text-sm font-semibold w-8 text-right">{{ $queue_dispensed }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="badge badge-error badge-sm">Cancelled</div>
                                <progress class="flex-1 progress progress-error" value="{{ $queue_cancelled }}" max="{{ $queue_total }}"></progress>
                                <span class="text-sm font-semibold w-8 text-right">{{ $queue_cancelled }}</span>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-base-200">
                            <div class="flex justify-between text-sm">
                                <span class="opacity-60">Avg Wait Time</span>
                                <span class="font-semibold">{{ $avg_wait_time !== null ? $avg_wait_time . ' min' : 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="opacity-60">Avg Processing Time</span>
                                <span class="font-semibold">{{ $avg_processing_time !== null ? $avg_processing_time . ' min' : 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-center h-32 opacity-40">
                        <p class="text-sm">No queues today</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Dispensing by Encounter Type --}}
        <div class="shadow-sm card bg-base-100">
            <div class="card-body">
                <h2 class="card-title text-base">Dispensing by Encounter Type</h2>
                @if (count($dispensing_by_type) > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th class="text-right">Encounters</th>
                                    <th class="text-right">Qty Issued</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dispensing_by_type as $type)
                                    <tr>
                                        <td>
                                            <div class="badge badge-outline badge-sm">
                                                {{ $type->encounter_type }}
                                            </div>
                                        </td>
                                        <td class="text-right">{{ number_format($type->encounter_count) }}</td>
                                        <td class="text-right font-semibold">{{ number_format($type->total_qty) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex items-center justify-center h-32 opacity-40">
                        <p class="text-sm">No dispensing data for selected period</p>
                    </div>
                @endif

                {{-- Emergency Purchases Summary --}}
                <div class="p-3 mt-4 rounded-lg bg-base-200">
                    <h3 class="text-sm font-semibold mb-2">Emergency Purchases</h3>
                    <div class="flex justify-between text-sm">
                        <span class="opacity-60">Count</span>
                        <span class="font-semibold">{{ number_format($emergency_purchase_count) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="opacity-60">Total Amount</span>
                        <span class="font-semibold">{{ number_format($emergency_purchase_total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pharmacy Locations --}}
        <div class="shadow-sm card bg-base-100">
            <div class="card-body">
                <h2 class="card-title text-base">Pharmacy Locations</h2>
                @if (count($locations) > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Location</th>
                                    <th class="text-right">Stock Items</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($locations as $index => $location)
                                    <tr>
                                        <td class="opacity-50">{{ $index + 1 }}</td>
                                        <td>{{ $location['description'] }}</td>
                                        <td class="text-right font-semibold">{{ number_format($location['stock_items']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex items-center justify-center h-32 opacity-40">
                        <p class="text-sm">No locations configured</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- SECTION 5: Top Drugs & Items Near Expiry --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-1 gap-4 mb-6 lg:grid-cols-2">
        {{-- Top 10 Drugs Dispensed --}}
        <div class="shadow-sm card bg-base-100">
            <div class="card-body">
                <h2 class="card-title text-base">Top 10 Drugs Dispensed</h2>
                @if (count($top_drugs) > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Drug</th>
                                    <th class="text-right">Qty Issued</th>
                                    <th class="text-right">Encounters</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($top_drugs as $index => $drug)
                                    <tr>
                                        <td class="opacity-50">{{ $index + 1 }}</td>
                                        <td class="max-w-xs truncate" title="{{ str_replace('_', ' ', $drug->drug_concat) }}">
                                            {{ str_replace('_', ' ', $drug->drug_concat) }}
                                        </td>
                                        <td class="text-right font-semibold">{{ number_format($drug->total_issued) }}</td>
                                        <td class="text-right">{{ number_format($drug->encounter_count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex items-center justify-center h-32 opacity-40">
                        <p class="text-sm">No dispensing data for selected period</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Items Expiring Soon --}}
        <div class="shadow-sm card bg-base-100">
            <div class="card-body">
                <h2 class="card-title text-base">Items Expiring Soon</h2>
                @if (count($expiring_soon) > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Drug</th>
                                    <th class="text-right">Qty</th>
                                    <th>Expiry Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($expiring_soon as $index => $item)
                                    @php
                                        $daysUntil = \Carbon\Carbon::parse($item->exp_date)->diffInDays(now(), false);
                                        $badgeClass = $daysUntil > -30 ? 'badge-error' : ($daysUntil > -90 ? 'badge-warning' : 'badge-info');
                                    @endphp
                                    <tr>
                                        <td class="opacity-50">{{ $index + 1 }}</td>
                                        <td class="max-w-xs truncate" title="{{ str_replace('_', ' ', $item->drug_concat) }}">
                                            {{ str_replace('_', ' ', $item->drug_concat) }}
                                        </td>
                                        <td class="text-right font-semibold">{{ number_format($item->stock_bal) }}</td>
                                        <td>
                                            <div class="badge {{ $badgeClass }} badge-sm">
                                                {{ \Carbon\Carbon::parse($item->exp_date)->format('M d, Y') }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex items-center justify-center h-32 opacity-40">
                        <p class="text-sm">No items expiring soon</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Chart.js CDN --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
@endpush

@script
<script>
    // 7-Day Dispensing Trend Chart
    Alpine.data('dispensingChart', () => ({
        chart: null,
        init() {
            const ctx = this.$refs.dispensingCanvas.getContext('2d');
            const chartData = $wire.daily_dispensing_chart;

            if (!chartData.labels || chartData.labels.length === 0) {
                return;
            }

            this.chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Orders',
                            data: chartData.orders,
                            backgroundColor: 'rgba(99, 102, 241, 0.7)',
                            borderColor: 'rgba(99, 102, 241, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Qty Dispensed',
                            data: chartData.quantities,
                            type: 'line',
                            borderColor: 'rgba(54, 211, 153, 1)',
                            backgroundColor: 'rgba(54, 211, 153, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: 'rgba(54, 211, 153, 1)',
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 16 }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: { display: true, text: 'Orders' },
                            grid: { display: true, color: 'rgba(0,0,0,0.05)' },
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: { display: true, text: 'Quantity' },
                            grid: { display: false },
                        },
                        x: {
                            grid: { display: false },
                        }
                    }
                }
            });
        }
    }));

    // Stock Status Distribution Chart
    Alpine.data('stockStatusChart', () => ({
        chart: null,
        init() {
            const ctx = this.$refs.stockCanvas.getContext('2d');
            const chartData = $wire.stock_status_chart;

            if (!chartData.labels || chartData.data.every(v => v === 0)) {
                return;
            }

            this.chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: chartData.colors,
                        borderWidth: 2,
                        borderColor: 'rgba(255,255,255,0.8)',
                        hoverOffset: 8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 16 }
                        }
                    }
                }
            });
        }
    }));
</script>
@endscript
