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
            <h1 class="text-2xl font-bold">Queue Details (Today)</h1>
            <p class="text-sm opacity-60">Prescription queue records for today</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-mary-select wire:model.live="location_id" size="sm" :options="collect($location_options)
                ->map(fn($loc) => ['id' => $loc['id'], 'name' => $loc['description']])
                ->prepend(['id' => 'all', 'name' => 'All Locations'])
                ->values()
                ->toArray()" />

            <x-mary-select wire:model.live="status_filter" size="sm" :options="[
                ['id' => 'all', 'name' => 'All Status'],
                ['id' => 'waiting', 'name' => 'Waiting'],
                ['id' => 'preparing', 'name' => 'Preparing'],
                ['id' => 'charging', 'name' => 'Charging'],
                ['id' => 'ready', 'name' => 'Ready'],
                ['id' => 'dispensed', 'name' => 'Dispensed'],
                ['id' => 'cancelled', 'name' => 'Cancelled'],
            ]" />
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4 lg:grid-cols-7">
        <div class="shadow-sm card bg-base-100 cursor-pointer" wire:click="$set('status_filter', 'all')">
            <div class="p-3 card-body items-center">
                <p class="text-xs opacity-60">Total</p>
                <p class="text-lg font-bold">{{ $summary['total'] }}</p>
            </div>
        </div>
        <div class="shadow-sm card bg-base-100 cursor-pointer {{ $status_filter === 'waiting' ? 'ring-2 ring-warning' : '' }}" wire:click="$set('status_filter', 'waiting')">
            <div class="p-3 card-body items-center">
                <div class="badge badge-warning badge-sm">Waiting</div>
                <p class="text-lg font-bold">{{ $summary['waiting'] }}</p>
            </div>
        </div>
        <div class="shadow-sm card bg-base-100 cursor-pointer {{ $status_filter === 'preparing' ? 'ring-2 ring-info' : '' }}" wire:click="$set('status_filter', 'preparing')">
            <div class="p-3 card-body items-center">
                <div class="badge badge-info badge-sm">Preparing</div>
                <p class="text-lg font-bold">{{ $summary['preparing'] }}</p>
            </div>
        </div>
        <div class="shadow-sm card bg-base-100 cursor-pointer {{ $status_filter === 'charging' ? 'ring-2 ring-secondary' : '' }}" wire:click="$set('status_filter', 'charging')">
            <div class="p-3 card-body items-center">
                <div class="badge badge-secondary badge-sm">Charging</div>
                <p class="text-lg font-bold">{{ $summary['charging'] }}</p>
            </div>
        </div>
        <div class="shadow-sm card bg-base-100 cursor-pointer {{ $status_filter === 'ready' ? 'ring-2 ring-success' : '' }}" wire:click="$set('status_filter', 'ready')">
            <div class="p-3 card-body items-center">
                <div class="badge badge-success badge-sm">Ready</div>
                <p class="text-lg font-bold">{{ $summary['ready'] }}</p>
            </div>
        </div>
        <div class="shadow-sm card bg-base-100 cursor-pointer {{ $status_filter === 'dispensed' ? 'ring-2 ring-base-300' : '' }}" wire:click="$set('status_filter', 'dispensed')">
            <div class="p-3 card-body items-center">
                <div class="badge badge-ghost badge-sm">Dispensed</div>
                <p class="text-lg font-bold">{{ $summary['dispensed'] }}</p>
            </div>
        </div>
        <div class="shadow-sm card bg-base-100 cursor-pointer {{ $status_filter === 'cancelled' ? 'ring-2 ring-error' : '' }}" wire:click="$set('status_filter', 'cancelled')">
            <div class="p-3 card-body items-center">
                <div class="badge badge-error badge-sm">Cancelled</div>
                <p class="text-lg font-bold">{{ $summary['cancelled'] }}</p>
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
            @if ($records->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table table-sm table-zebra">
                        <thead>
                            <tr class="bg-base-200">
                                <th>#</th>
                                <th>Queue No.</th>
                                <th>Patient</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Queued At</th>
                                <th>Preparing At</th>
                                <th>Dispensed At</th>
                                <th>Wait Time</th>
                                <th>Process Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $index => $record)
                                @php
                                    $statusBadge = match($record->queue_status) {
                                        'waiting' => 'badge-warning',
                                        'preparing' => 'badge-info',
                                        'charging' => 'badge-secondary',
                                        'ready' => 'badge-success',
                                        'dispensed' => 'badge-ghost',
                                        'cancelled' => 'badge-error',
                                        default => 'badge-ghost',
                                    };
                                @endphp
                                <tr class="hover">
                                    <td class="opacity-50">{{ $index + 1 }}</td>
                                    <td class="font-bold">{{ $record->queue_prefix }}{{ $record->queue_number }}</td>
                                    <td class="font-medium">
                                        @if ($record->patient)
                                            {{ $record->patient->patlast }}, {{ $record->patient->patfirst }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if ($record->priority === 'priority')
                                            <div class="badge badge-warning badge-sm">Priority</div>
                                        @else
                                            <div class="badge badge-ghost badge-sm">Regular</div>
                                        @endif
                                    </td>
                                    <td><div class="badge {{ $statusBadge }} badge-sm">{{ ucfirst($record->queue_status) }}</div></td>
                                    <td class="whitespace-nowrap">{{ $record->queued_at ? $record->queued_at->format('h:i A') : '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $record->preparing_at ? $record->preparing_at->format('h:i A') : '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $record->dispensed_at ? $record->dispensed_at->format('h:i A') : '-' }}</td>
                                    <td class="text-center">
                                        @if ($record->queued_at && $record->preparing_at)
                                            {{ $record->queued_at->diffInMinutes($record->preparing_at) }} min
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($record->preparing_at && $record->dispensed_at)
                                            {{ $record->preparing_at->diffInMinutes($record->dispensed_at) }} min
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 opacity-40">
                    <x-mary-icon name="o-queue-list" class="w-12 h-12 mb-2" />
                    <p>No queue records found</p>
                </div>
            @endif
        </div>
    </div>
</div>
