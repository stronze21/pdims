<div>
    {{-- Header --}}
    <div class="mb-4">
        <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-sm btn-ghost gap-1">
            <x-mary-icon name="o-arrow-left" class="w-4 h-4" />
            Back to Dashboard
        </a>
    </div>
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ $pageTitle }}</h1>
            <p class="text-sm opacity-60">
                @if ($stock_type === 'near_reorder')
                    Items approaching their reorder point (stock at 30% or less of reorder level)
                @else
                    Items below their reorder level requiring immediate restocking
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-mary-select wire:model.live="location_id" size="sm" :options="collect($location_options)
                ->map(fn($loc) => ['id' => $loc['id'], 'name' => $loc['description']])
                ->prepend(['id' => 'all', 'name' => 'All Locations'])
                ->values()
                ->toArray()" />

            <x-mary-select wire:model.live="stock_type" size="sm" :options="[
                ['id' => 'critical', 'name' => 'Critical Stock'],
                ['id' => 'near_reorder', 'name' => 'Near Reorder Level'],
            ]" />
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
                <div class="p-2 rounded-lg {{ $stock_type === 'near_reorder' ? 'bg-warning/10' : 'bg-error/10' }}">
                    <x-mary-icon name="{{ $stock_type === 'near_reorder' ? 'o-arrow-trending-down' : 'o-exclamation-triangle' }}"
                        class="w-5 h-5 {{ $stock_type === 'near_reorder' ? 'text-warning' : 'text-error' }}" />
                </div>
                <div>
                    <p class="text-sm opacity-60">Total {{ $stock_type === 'near_reorder' ? 'Near Reorder' : 'Critical' }} Items</p>
                    <p class="text-xl font-bold {{ $stock_type === 'near_reorder' ? 'text-warning' : 'text-error' }}">
                        {{ number_format(count($records)) }}
                    </p>
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
                                <th class="text-right">Current Stock</th>
                                <th class="text-right">Avg Daily Usage</th>
                                <th class="text-right">Reorder Level</th>
                                <th>Status</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $index => $record)
                                <tr class="hover">
                                    <td class="opacity-50">{{ $index + 1 }}</td>
                                    <td class="max-w-xs truncate" title="{{ str_replace('_', ' ', $record->drug_concat) }}">
                                        {{ str_replace('_', ' ', $record->drug_concat) }}
                                    </td>
                                    <td class="text-right font-semibold">{{ number_format($record->stock_bal) }}</td>
                                    <td class="text-right">{{ number_format($record->avg_iss ?? 0) }}</td>
                                    <td class="text-right">{{ number_format($record->reorder_level ?? 0) }}</td>
                                    <td>
                                        <div class="badge {{ $record->status === 'CRITICAL' ? 'badge-error' : 'badge-warning' }} badge-sm">
                                            {{ $record->status }}
                                        </div>
                                    </td>
                                    <td>{{ $record->location_name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 opacity-40">
                    <x-mary-icon name="o-exclamation-triangle" class="w-12 h-12 mb-2" />
                    <p>No {{ $stock_type === 'near_reorder' ? 'near reorder' : 'critical stock' }} items found</p>
                </div>
            @endif
        </div>
    </div>
</div>
