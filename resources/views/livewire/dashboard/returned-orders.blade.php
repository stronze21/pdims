<div>
    {{-- Header --}}
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm breadcrumbs">
                <ul>
                    <li><a href="{{ route('dashboard') }}" wire:navigate>Dashboard</a></li>
                    <li>Returned Orders</li>
                </ul>
            </div>
            <h1 class="text-2xl font-bold">Returned Orders</h1>
            <p class="text-sm opacity-60">Drug orders that have been returned</p>
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
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-4">
                    <div class="p-2 rounded-lg bg-base-200">
                        <x-mary-icon name="o-arrow-uturn-left" class="w-5 h-5 opacity-60" />
                    </div>
                    <div>
                        <p class="text-sm opacity-60">Total Returns</p>
                        <p class="text-xl font-bold">{{ number_format(count($records)) }}</p>
                    </div>
                </div>
                <div class="border-l border-base-200 pl-6">
                    <p class="text-sm opacity-60">Total Qty Returned</p>
                    <p class="text-xl font-bold">{{ number_format($records->sum('qty')) }}</p>
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
                                <th class="text-right">Qty Returned</th>
                                <th class="text-right">Unit Price</th>
                                <th>Return Date</th>
                                <th>Returned By</th>
                                <th>Remarks</th>
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
                                    <td class="text-right font-semibold">{{ number_format($record->qty) }}</td>
                                    <td class="text-right">{{ number_format($record->unit_price, 2) }}</td>
                                    <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($record->returndate)->format('M d, Y h:i A') }}</td>
                                    <td>
                                        @if ($record->returned_by_last)
                                            {{ $record->returned_by_last }}, {{ $record->returned_by_first }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="max-w-xs truncate">{{ $record->remarks ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 opacity-40">
                    <x-mary-icon name="o-arrow-uturn-left" class="w-12 h-12 mb-2" />
                    <p>No returned orders found for the selected period</p>
                </div>
            @endif
        </div>
    </div>
</div>
