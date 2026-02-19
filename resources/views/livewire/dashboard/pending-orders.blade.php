<div>
    {{-- Header --}}
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm breadcrumbs">
                <ul>
                    <li><a href="{{ route('dashboard') }}" wire:navigate>Dashboard</a></li>
                    <li>Pending/Charged Orders</li>
                </ul>
            </div>
            <h1 class="text-2xl font-bold">Pending/Charged Orders</h1>
            <p class="text-sm opacity-60">Drug orders awaiting processing or charging</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-mary-select wire:model.live="location_id" size="sm" :options="collect($location_options)
                ->map(fn($loc) => ['id' => $loc['id'], 'name' => $loc['description']])
                ->prepend(['id' => 'all', 'name' => 'All Locations'])
                ->values()
                ->toArray()" />

            <x-mary-select wire:model.live="date_range" size="sm" :options="[
                ['id' => 'today', 'name' => 'Today'],
                ['id' => 'yesterday', 'name' => 'Yesterday'],
                ['id' => 'this_week', 'name' => 'This Week'],
                ['id' => 'last_week', 'name' => 'Last Week'],
                ['id' => 'this_month', 'name' => 'This Month'],
                ['id' => 'last_month', 'name' => 'Last Month'],
                ['id' => 'custom', 'name' => 'Custom Range'],
            ]" />

            @if ($date_range === 'custom')
                <x-mary-input type="date" size="sm" wire:model.live.debounce.500ms="custom_date_from" />
                <span class="text-sm opacity-60">to</span>
                <x-mary-input type="date" size="sm" wire:model.live.debounce.500ms="custom_date_to" />
            @endif
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
                <div class="p-2 rounded-lg bg-info/10">
                    <x-mary-icon name="o-document-text" class="w-5 h-5 text-info" />
                </div>
                <div>
                    <p class="text-sm opacity-60">Total Records</p>
                    <p class="text-xl font-bold text-info">{{ number_format(count($records)) }}</p>
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
                                <th>Patient</th>
                                <th>Drug</th>
                                <th>Type</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $index => $record)
                                <tr class="hover">
                                    <td class="opacity-50">{{ $index + 1 }}</td>
                                    <td class="font-medium">
                                        {{ $record->patlast }}, {{ $record->patfirst }}
                                        {{ $record->patmiddle ? $record->patmiddle[0] . '.' : '' }}
                                    </td>
                                    <td class="max-w-xs truncate" title="{{ str_replace('_', ' ', $record->drug_concat) }}">
                                        {{ str_replace('_', ' ', $record->drug_concat) }}
                                    </td>
                                    <td><div class="badge badge-outline badge-sm">{{ $record->encounter_type }}</div></td>
                                    <td class="text-right">{{ number_format($record->qty) }}</td>
                                    <td class="text-right">{{ number_format($record->unit_price, 2) }}</td>
                                    <td class="text-right font-semibold">{{ number_format($record->total_amount, 2) }}</td>
                                    <td>
                                        @php
                                            $statusLabel = match($record->estatus) {
                                                'U' => 'Pending',
                                                'P' => 'Charged',
                                                'S' => 'Uncharged',
                                                default => $record->estatus,
                                            };
                                            $statusClass = match($record->estatus) {
                                                'U' => 'badge-warning',
                                                'P' => 'badge-info',
                                                'S' => 'badge-ghost',
                                                default => 'badge-ghost',
                                            };
                                        @endphp
                                        <div class="badge {{ $statusClass }} badge-sm">{{ $statusLabel }}</div>
                                    </td>
                                    <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($record->dodate)->format('M d, Y h:i A') }}</td>
                                    <td>{{ $record->location_name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 opacity-40">
                    <x-mary-icon name="o-document-text" class="w-12 h-12 mb-2" />
                    <p>No pending/charged orders found</p>
                </div>
            @endif
        </div>
    </div>
</div>
