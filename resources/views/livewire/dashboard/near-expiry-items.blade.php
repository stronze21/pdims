<div>
    {{-- Header --}}
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm breadcrumbs">
                <ul>
                    <li><a href="{{ route('dashboard') }}" wire:navigate>Dashboard</a></li>
                    <li>Items Near Expiry</li>
                </ul>
            </div>
            <h1 class="text-2xl font-bold">Items Near Expiry</h1>
            <p class="text-sm opacity-60">Stock items expiring within the next 6 months</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-mary-select wire:model.live="location_id" size="sm" :options="collect($location_options)
                ->map(fn($loc) => ['id' => $loc['id'], 'name' => $loc['description']])
                ->prepend(['id' => 'all', 'name' => 'All Locations'])
                ->values()
                ->toArray()" />
        </div>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <x-mary-input icon="o-magnifying-glass" wire:model.live.debounce.300ms="search"
            placeholder="Search by drug name..." size="sm" clearable />
    </div>

    {{-- Summary --}}
    <div class="mb-4 shadow-sm card bg-base-100">
        <div class="p-3 card-body">
            <div class="flex items-center gap-4">
                <div class="p-2 rounded-lg bg-warning/10">
                    <x-mary-icon name="o-clock" class="w-5 h-5 text-warning" />
                </div>
                <div>
                    <p class="text-sm opacity-60">Total Near Expiry Items</p>
                    <p class="text-xl font-bold text-warning">{{ number_format(count($records)) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Loading --}}
    <div wire:loading.flex class="fixed inset-0 z-50 items-center justify-center bg-base-100/50">
        <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    {{-- Table --}}
    <div class="shadow-sm card bg-base-100">
        <div class="card-body p-0">
            @if (count($records) > 0)
                <div class="overflow-x-auto">
                    <table class="table table-sm table-zebra">
                        <thead>
                            <tr class="bg-base-200">
                                <th>#</th>
                                <th>Drug</th>
                                <th>Fund Source</th>
                                <th class="text-right">Stock Bal</th>
                                <th class="text-right">Retail Price</th>
                                <th>Lot No</th>
                                <th>Expiry Date</th>
                                <th>Days Left</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $index => $record)
                                @php
                                    $daysLeft = $record->days_until_expiry;
                                    $badgeClass = $daysLeft <= 30 ? 'badge-error' : ($daysLeft <= 90 ? 'badge-warning' : 'badge-info');
                                @endphp
                                <tr class="hover">
                                    <td class="opacity-50">{{ $index + 1 }}</td>
                                    <td class="max-w-xs truncate" title="{{ str_replace('_', ' ', $record->drug_concat) }}">
                                        {{ str_replace('_', ' ', $record->drug_concat) }}
                                    </td>
                                    <td>{{ $record->charge_desc ?? $record->chrgcode }}</td>
                                    <td class="text-right font-semibold">{{ number_format($record->stock_bal) }}</td>
                                    <td class="text-right">{{ number_format($record->retail_price, 2) }}</td>
                                    <td>{{ $record->lot_no ?? '-' }}</td>
                                    <td>
                                        <div class="badge {{ $badgeClass }} badge-sm">
                                            {{ \Carbon\Carbon::parse($record->exp_date)->format('M d, Y') }}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="font-semibold {{ $daysLeft <= 30 ? 'text-error' : ($daysLeft <= 90 ? 'text-warning' : '') }}">
                                            {{ $daysLeft }}
                                        </span>
                                    </td>
                                    <td>{{ $record->location_name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 opacity-40">
                    <x-mary-icon name="o-clock" class="w-12 h-12 mb-2" />
                    <p>No items near expiry</p>
                </div>
            @endif
        </div>
    </div>
</div>
