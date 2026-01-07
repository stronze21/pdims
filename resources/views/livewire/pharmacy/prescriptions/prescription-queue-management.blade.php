<div x-data="{ autoRefresh: @entangle('autoRefresh') }" x-init="if (autoRefresh) {
    setInterval(() => { $wire.call('refreshQueue') }, 30000);
}">
    <div class="flex items-center justify-between">
        <div class="text-sm breadcrumbs">
            <ul>
                <li class="font-bold text-primary">
                    <x-mary-icon name="o-building-office-2" class="inline w-5 h-5" />
                    {{ session('pharm_location_name') }}
                </li>
                <li>
                    <x-mary-icon name="o-queue-list" class="inline w-5 h-5" />
                    Prescription Queue
                </li>
            </ul>
        </div>
        <div class="flex gap-2">
            <x-mary-toggle wire:model.live="autoRefresh" label="Auto-refresh" />
            <x-mary-button icon="o-arrow-path" wire:click="refreshQueue" spinner="refreshQueue" class="btn-sm btn-ghost"
                tooltip="Refresh" />
            <x-mary-button label="Test API" icon="o-beaker" wire:click="openTestApiModal" class="btn-outline btn-sm"
                tooltip="Test queue creation API" />
        </div>
    </div>

    <div class="container px-4 py-4 mx-auto space-y-4">

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-7">
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-title text-xs">Total Today</div>
                    <div class="stat-value text-2xl text-primary">{{ $stats['total_today'] }}</div>
                </div>
            </div>
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-title text-xs">Waiting</div>
                    <div class="stat-value text-2xl text-warning">{{ $stats['waiting'] }}</div>
                </div>
            </div>
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-title text-xs">Preparing</div>
                    <div class="stat-value text-2xl text-info">{{ $stats['preparing'] }}</div>
                </div>
            </div>
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-title text-xs">Ready</div>
                    <div class="stat-value text-2xl text-success">{{ $stats['ready'] }}</div>
                </div>
            </div>
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-title text-xs">Dispensed</div>
                    <div class="stat-value text-2xl">{{ $stats['dispensed'] }}</div>
                </div>
            </div>
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-title text-xs">Cancelled</div>
                    <div class="stat-value text-2xl text-error">{{ $stats['cancelled'] }}</div>
                </div>
            </div>
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-title text-xs">Avg Wait</div>
                    <div class="stat-value text-2xl">{{ $stats['avg_wait_time'] }}<span class="text-sm">m</span></div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <x-mary-card shadow class="bg-base-100">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                <x-mary-input wire:model.live.debounce.300ms="searchQueue" placeholder="Search queue, patient..."
                    icon="o-magnifying-glass" inline clearable />

                <x-mary-input type="date" wire:model.live="dateFilter" label="Date" icon="o-calendar" inline />

                <x-mary-select wire:model.live="statusFilter" inline label="Status" :options="[
                    ['id' => 'all', 'name' => 'All Status'],
                    ['id' => 'active', 'name' => 'Active'],
                    ['id' => 'waiting', 'name' => 'Waiting'],
                    ['id' => 'preparing', 'name' => 'Preparing'],
                    ['id' => 'ready', 'name' => 'Ready'],
                    ['id' => 'dispensed', 'name' => 'Dispensed'],
                    ['id' => 'cancelled', 'name' => 'Cancelled'],
                ]" />

                <x-mary-select wire:model.live="priorityFilter" inline label="Priority" :options="[
                    ['id' => 'all', 'name' => 'All Priority'],
                    ['id' => 'stat', 'name' => 'STAT'],
                    ['id' => 'urgent', 'name' => 'Urgent'],
                    ['id' => 'normal', 'name' => 'Normal'],
                ]" />

                <div class="flex items-end gap-2 md:col-span-2">
                    <x-mary-button label="Call Next" icon="o-bell-alert" wire:click="callNext"
                        class="btn-primary btn-sm flex-1" spinner="callNext" />
                    <x-mary-button icon="o-adjustments-horizontal" class="btn-ghost btn-sm" tooltip="Settings" />
                </div>
            </div>
        </x-mary-card>

        {{-- Queue List --}}
        <x-mary-card shadow class="bg-base-100">
            <x-slot:title>
                <span class="text-lg font-bold">Queue List</span>
            </x-slot:title>

            <div class="overflow-y-auto max-h-[calc(100vh-480px)]">
                @forelse($queues as $queue)
                    <div wire:key="queue-{{ $queue->id }}"
                        class="p-4 mb-3 transition-all border rounded-lg
                            {{ $queue->isWaiting() ? 'border-warning bg-warning/5' : '' }}
                            {{ $queue->isPreparing() ? 'border-info bg-info/5' : '' }}
                            {{ $queue->isReady() ? 'border-success bg-success/5 shadow-md' : '' }}
                            {{ $queue->isDispensed() ? 'border-base-300 bg-base-200' : '' }}
                            {{ $queue->isCancelled() ? 'border-error bg-error/5' : '' }}
                            hover:shadow-lg">

                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <h3 class="text-xl font-bold font-mono">{{ $queue->queue_number }}</h3>
                                    <span class="badge {{ $queue->getStatusBadgeClass() }} badge-sm">
                                        {{ ucfirst($queue->queue_status) }}
                                    </span>
                                    @if ($queue->priority !== 'normal')
                                        <span class="badge {{ $queue->getPriorityBadgeClass() }} badge-sm">
                                            {{ strtoupper($queue->priority) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
                                    <div>
                                        <p class="text-xs text-gray-500">Patient</p>
                                        <p class="font-semibold">
                                            @if ($queue->patient)
                                                {{ $queue->patient->patlast }}, {{ $queue->patient->patfirst }}
                                            @else
                                                N/A
                                            @endif
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Hospital #</p>
                                        <p class="font-mono">{{ $queue->hpercode }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Queued At</p>
                                        <p>{{ $queue->queued_at->format('h:i A') }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Wait Time</p>
                                        <p class="font-semibold">{{ $queue->getWaitTimeMinutes() }} min</p>
                                    </div>
                                </div>

                                @if ($queue->remarks)
                                    <div class="mt-2">
                                        <p class="text-xs text-gray-500">Notes:</p>
                                        <p class="text-sm italic">{{ $queue->remarks }}</p>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col gap-2 ml-4">
                                @if ($queue->isWaiting())
                                    <button class="btn btn-info btn-sm"
                                        wire:click="startPreparing({{ $queue->id }})" wire:loading.attr="disabled">
                                        <i class="las la-play"></i> Start
                                    </button>
                                @endif

                                @if ($queue->isPreparing())
                                    <button class="btn btn-success btn-sm"
                                        wire:click="markReady({{ $queue->id }})" wire:loading.attr="disabled">
                                        <i class="las la-check"></i> Ready
                                    </button>
                                @endif

                                @if ($queue->isReady())
                                    <button class="btn btn-primary btn-sm"
                                        wire:click="markDispensed({{ $queue->id }})" wire:loading.attr="disabled">
                                        <i class="las la-hand-holding-medical"></i> Dispense
                                    </button>
                                @endif

                                {{-- NEW: Dispensing Window Button
                                @if ($queue->isActive())
                                    <button class="btn btn-accent btn-sm"
                                        wire:click="openDispensingWindow({{ $queue->id }})">
                                        <i class="las la-prescription-bottle"></i> Dispense
                                    </button>
                                @endif --}}

                                <button class="btn btn-ghost btn-sm" wire:click="viewDetails({{ $queue->id }})">
                                    <i class="las la-info-circle"></i> Details
                                </button>

                                @if ($queue->isActive())
                                    <button class="btn btn-ghost btn-sm"
                                        wire:click="openNotesModal({{ $queue->id }})">
                                        <i class="las la-sticky-note"></i> Notes
                                    </button>
                                    <button class="btn btn-error btn-sm"
                                        wire:click="openCancelModal({{ $queue->id }})">
                                        <i class="las la-times"></i> Cancel
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12">
                        <x-mary-icon name="o-queue-list" class="w-16 h-16 text-gray-300" />
                        <p class="mt-3 font-medium text-gray-500">No prescriptions in queue</p>
                    </div>
                @endforelse
            </div>
        </x-mary-card>
    </div>

    {{-- Enhanced Details Modal with Prescribed Items --}}
    <x-mary-modal wire:model="showDetailsModal" title="Queue Details" class="backdrop-blur" box-class="max-w-5xl">
        @if ($selectedQueue)
            <div class="space-y-4">
                {{-- Queue Info --}}
                <div class="p-4 rounded-lg bg-base-200">
                    <h3 class="mb-2 text-lg font-bold">{{ $selectedQueue->queue_number }}</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                        <div>
                            <span class="text-gray-500">Status:</span>
                            <span class="ml-2 badge {{ $selectedQueue->getStatusBadgeClass() }} badge-sm">
                                {{ ucfirst($selectedQueue->queue_status) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500">Priority:</span>
                            <span class="ml-2 badge {{ $selectedQueue->getPriorityBadgeClass() }} badge-sm">
                                {{ strtoupper($selectedQueue->priority) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500">Queued:</span>
                            <span class="ml-2">{{ $selectedQueue->queued_at->format('M d, Y h:i A') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Wait Time:</span>
                            <span class="ml-2 font-semibold">{{ $selectedQueue->getWaitTimeMinutes() }} minutes</span>
                        </div>
                    </div>
                </div>

                {{-- Patient Info --}}
                @if ($selectedQueue->patient)
                    <div class="p-4 rounded-lg bg-base-200">
                        <h4 class="mb-2 font-semibold">Patient Information</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-gray-500">Name:</span>
                                <span class="ml-2">{{ $selectedQueue->patient->fullname() }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Hospital #:</span>
                                <span class="ml-2 font-mono">{{ $selectedQueue->hpercode }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- NEW: Enhanced Prescribed Items Display --}}
                @if (count($prescribedItems) > 0)
                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-semibold">Prescribed Items ({{ count($prescribedItems) }})</h4>
                            <div class="badge badge-info badge-sm">
                                {{ collect($prescribedItems)->where('is_fully_issued', false)->count() }} pending
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="table w-full table-sm table-zebra">
                                <thead class="bg-base-300">
                                    <tr class="text-xs">
                                        <th class="w-8">#</th>
                                        <th>Drug Description</th>
                                        <th class="text-center w-20">Ordered</th>
                                        <th class="text-center w-20">Issued</th>
                                        <th class="text-center w-20">Remaining</th>
                                        <th class="w-24">Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($prescribedItems as $index => $item)
                                        <tr class="{{ $item['is_fully_issued'] ? 'opacity-50' : '' }}">
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <div class="font-semibold">{{ $item['generic'] }}</div>
                                                @if ($item['brand'])
                                                    <div class="text-xs text-gray-600">{{ $item['brand'] }}</div>
                                                @endif
                                                @if ($item['order_type'])
                                                    <span
                                                        class="badge badge-xs badge-ghost">{{ $item['order_type'] }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center font-semibold">{{ $item['qty_ordered'] }}</td>
                                            <td
                                                class="text-center {{ $item['qty_issued'] > 0 ? 'text-success font-semibold' : '' }}">
                                                {{ $item['qty_issued'] }}
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="{{ $item['qty_remaining'] > 0 ? 'text-warning font-semibold' : 'text-success' }}">
                                                    {{ $item['qty_remaining'] }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($item['is_fully_issued'])
                                                    <span class="badge badge-success badge-xs">Completed</span>
                                                @elseif($item['qty_issued'] > 0)
                                                    <span class="badge badge-warning badge-xs">Partial</span>
                                                @else
                                                    <span class="badge badge-ghost badge-xs">Pending</span>
                                                @endif
                                            </td>
                                            <td class="text-xs">{{ $item['remark'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-base-300">
                                    <tr class="font-semibold">
                                        <td colspan="2" class="text-right">TOTALS:</td>
                                        <td class="text-center">{{ collect($prescribedItems)->sum('qty_ordered') }}
                                        </td>
                                        <td class="text-center text-success">
                                            {{ collect($prescribedItems)->sum('qty_issued') }}</td>
                                        <td class="text-center text-warning">
                                            {{ collect($prescribedItems)->sum('qty_remaining') }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="p-4 text-center rounded-lg bg-base-200">
                        <p class="text-sm text-gray-500">No prescribed items found</p>
                    </div>
                @endif

                {{-- Status History --}}
                @if ($selectedQueue->logs && $selectedQueue->logs->count() > 0)
                    <div class="p-4 rounded-lg bg-base-200">
                        <h4 class="mb-2 font-semibold">Status History</h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach ($selectedQueue->logs as $log)
                                <div class="text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="badge badge-xs badge-ghost">
                                            {{ $log->getStatusChangeLabel() }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            {{ $log->created_at->format('h:i A') }}
                                        </span>
                                        @if ($log->changer)
                                            <span class="text-xs text-gray-500">
                                                by {{ $log->changer->firstname }} {{ $log->changer->lastname }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($log->remarks)
                                        <p class="mt-1 text-xs italic text-gray-600">{{ $log->remarks }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="$set('showDetailsModal', false)" />
            @if ($selectedQueue && $selectedQueue->isActive())
                <x-mary-button label="Open Dispensing" wire:click="openDispensingWindow({{ $selectedQueue->id }})"
                    class="btn-accent" icon="o-arrow-right-circle" />
            @endif
        </x-slot:actions>
    </x-mary-modal>

    {{-- Continue in next part... --}}
    {{-- NEW: Dispensing Window Modal --}}
    <x-mary-modal wire:model="showDispensingModal" title="Dispensing Window" class="backdrop-blur"
        box-class="max-w-6xl">
        @if ($selectedQueue)
            <div class="space-y-4">
                {{-- Patient & Queue Header --}}
                <div class="p-4 rounded-lg bg-gradient-to-r from-primary/10 to-secondary/10">
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div>
                            <p class="text-xs text-gray-500">Queue Number</p>
                            <p class="text-xl font-bold font-mono text-primary">{{ $selectedQueue->queue_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Patient</p>
                            <p class="font-semibold">
                                @if ($selectedQueue->patient)
                                    {{ $selectedQueue->patient->fullname() }}
                                @else
                                    N/A
                                @endif
                            </p>
                            <p class="text-xs font-mono">{{ $selectedQueue->hpercode }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Status</p>
                            <span class="badge {{ $selectedQueue->getStatusBadgeClass() }}">
                                {{ ucfirst($selectedQueue->queue_status) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Wait Time</p>
                            <p class="font-semibold">{{ $selectedQueue->getWaitTimeMinutes() }} minutes</p>
                        </div>
                    </div>
                </div>

                {{-- Prescribed Items Table --}}
                @if (count($prescribedItems) > 0)
                    <div class="border rounded-lg">
                        <div class="p-3 border-b bg-base-200">
                            <div class="flex items-center justify-between">
                                <h4 class="font-semibold">Items to Dispense</h4>
                                <div class="flex gap-2">
                                    <span class="badge badge-warning badge-sm">
                                        {{ collect($prescribedItems)->where('is_fully_issued', false)->count() }}
                                        Pending
                                    </span>
                                    <span class="badge badge-success badge-sm">
                                        {{ collect($prescribedItems)->where('is_fully_issued', true)->count() }}
                                        Completed
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto max-h-96">
                            <table class="table w-full table-sm">
                                <thead class="sticky top-0 bg-base-200">
                                    <tr class="text-xs">
                                        <th class="w-8">#</th>
                                        <th>Drug Description</th>
                                        <th class="text-center w-20">Ordered</th>
                                        <th class="text-center w-20">Issued</th>
                                        <th class="text-center w-20">Remaining</th>
                                        <th class="w-24">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($prescribedItems as $index => $item)
                                        <tr class="{{ $item['is_fully_issued'] ? 'bg-success/10' : '' }}">
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <div class="font-semibold">{{ $item['generic'] }}</div>
                                                @if ($item['brand'])
                                                    <div class="text-xs text-gray-600">{{ $item['brand'] }}</div>
                                                @endif
                                                @if ($item['order_type'])
                                                    <span
                                                        class="badge badge-xs badge-ghost">{{ $item['order_type'] }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="font-semibold">{{ $item['qty_ordered'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                @if ($item['qty_issued'] > 0)
                                                    <span
                                                        class="font-semibold text-success">{{ $item['qty_issued'] }}</span>
                                                @else
                                                    <span class="text-gray-400">0</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($item['qty_remaining'] > 0)
                                                    <span
                                                        class="px-2 py-1 text-sm font-bold rounded bg-warning/20 text-warning">
                                                        {{ $item['qty_remaining'] }}
                                                    </span>
                                                @else
                                                    <span class="text-success">✓</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($item['is_fully_issued'])
                                                    <span class="badge badge-success badge-sm">
                                                        <i class="las la-check"></i> Done
                                                    </span>
                                                @elseif($item['qty_issued'] > 0)
                                                    <span class="badge badge-warning badge-sm">
                                                        <i class="las la-hourglass-half"></i> Partial
                                                    </span>
                                                @else
                                                    <span class="badge badge-ghost badge-sm">
                                                        <i class="las la-clock"></i> Pending
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Summary Footer --}}
                        <div class="p-3 border-t bg-base-200">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p class="text-xs text-gray-500">Total Ordered</p>
                                    <p class="text-xl font-bold">{{ collect($prescribedItems)->sum('qty_ordered') }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Total Issued</p>
                                    <p class="text-xl font-bold text-success">
                                        {{ collect($prescribedItems)->sum('qty_issued') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Remaining</p>
                                    <p class="text-xl font-bold text-warning">
                                        {{ collect($prescribedItems)->sum('qty_remaining') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Dispensing Progress --}}
                    @php
                        $totalOrdered = collect($prescribedItems)->sum('qty_ordered');
                        $totalIssued = collect($prescribedItems)->sum('qty_issued');
                        $progress = $totalOrdered > 0 ? round(($totalIssued / $totalOrdered) * 100) : 0;
                    @endphp
                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold">Dispensing Progress</span>
                            <span class="text-sm font-bold">{{ $progress }}%</span>
                        </div>
                        <progress
                            class="w-full progress {{ $progress === 100 ? 'progress-success' : 'progress-warning' }}"
                            value="{{ $progress }}" max="100"></progress>
                    </div>
                @else
                    <div class="p-8 text-center rounded-lg bg-base-200">
                        <i class="text-gray-300 las la-prescription-bottle la-3x"></i>
                        <p class="mt-2 text-gray-500">No items to dispense</p>
                    </div>
                @endif

                {{-- Action Buttons in Modal Body --}}
                <div class="p-4 border-t">
                    <div class="flex flex-wrap gap-2">
                        @if ($selectedQueue->isWaiting())
                            <button class="btn btn-info btn-sm"
                                wire:click="startPreparing({{ $selectedQueue->id }}); $set('showDispensingModal', false)">
                                <i class="las la-play"></i> Start Preparing
                            </button>
                        @endif

                        @if ($selectedQueue->isPreparing())
                            <button class="btn btn-success btn-sm"
                                wire:click="markReady({{ $selectedQueue->id }}); $set('showDispensingModal', false)">
                                <i class="las la-check"></i> Mark as Ready
                            </button>
                        @endif

                        @if ($selectedQueue->isReady())
                            <button class="btn btn-primary btn-sm"
                                wire:click="markDispensed({{ $selectedQueue->id }}); $set('showDispensingModal', false)">
                                <i class="las la-hand-holding-medical"></i> Complete Dispensing
                            </button>
                        @endif

                        <button class="btn btn-accent btn-sm" wire:click="navigateToDispensing">
                            <i class="las la-external-link-alt"></i> Open Full Dispensing Page
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="$set('showDispensingModal', false)" />
            @if ($dispensingEnccode)
                <x-mary-button label="Go to Dispensing" wire:click="navigateToDispensing" class="btn-primary"
                    icon="o-arrow-right" />
            @endif
        </x-slot:actions>
    </x-mary-modal>

    {{-- Cancel Modal --}}
    <x-mary-modal wire:model="showCancelModal" title="Cancel Queue" class="backdrop-blur">
        @if ($selectedQueue)
            <p class="mb-4">Are you sure you want to cancel <strong>{{ $selectedQueue->queue_number }}</strong>?</p>

            <x-mary-textarea label="Cancellation Reason *" wire:model="cancellationReason"
                placeholder="Please provide a reason for cancellation..." rows="4" required />
        @endif

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="$set('showCancelModal', false)" />
            <x-mary-button label="Confirm Cancel" wire:click="cancelQueue" class="btn-error"
                spinner="cancelQueue" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Notes Modal --}}
    <x-mary-modal wire:model="showNotesModal" title="Queue Notes" class="backdrop-blur">
        <x-mary-textarea label="Notes" wire:model="queueNotes" placeholder="Add notes about this queue..."
            rows="5" />

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="$set('showNotesModal', false)" />
            <x-mary-button label="Save" wire:click="saveNotes" class="btn-primary" spinner="saveNotes" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Test API Modal --}}
    <x-mary-modal wire:model="showTestApiModal" title="Test Queue API" class="backdrop-blur" box-class="max-w-2xl">
        <x-mary-form wire:submit="submitTestApi">
            {{-- Add Debug Info Section at the top --}}
            <div class="mb-4 p-3 bg-info/10 rounded-lg text-sm">
                <p><strong>API Endpoint:</strong> {{ config('app.url') }}/api/prescription-queue/create</p>
                <p><strong>Environment:</strong> {{ config('app.env') }}</p>
                <p class="text-xs opacity-75 mt-2">Check Laravel logs for detailed error information</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Prescription ID *" wire:model="testPrescriptionId" type="number"
                    icon="o-document-text" required />

                <x-mary-input label="Encounter Code *" wire:model="testEnccode" icon="o-clipboard-document-list"
                    required />

                <x-mary-input label="Hospital Number *" wire:model="testHpercode" icon="o-user" required />

                <x-mary-input label="Location Code *" wire:model="testLocationCode" icon="o-map-pin" required />

                <x-mary-select label="Priority *" wire:model="testPriority" :options="[
                    ['id' => 'normal', 'name' => 'Normal'],
                    ['id' => 'urgent', 'name' => 'Urgent'],
                    ['id' => 'stat', 'name' => 'STAT'],
                ]" icon="o-flag"
                    required />

                <x-mary-input label="Queue Prefix" wire:model="testQueuePrefix" icon="o-hashtag"
                    hint="Optional (e.g., OPD, ER)" />

                <x-mary-input label="Created By" wire:model="testCreatedBy" icon="o-user-circle"
                    hint="Employee ID" />

                <x-mary-input label="Created From" wire:model="testCreatedFrom" icon="o-computer-desktop"
                    hint="Source system" />

                <div class="md:col-span-2">
                    <x-mary-textarea label="Remarks" wire:model="testRemarks" rows="3"
                        hint="Optional notes" />
                </div>
            </div>

            <div class="mt-4 p-4 bg-warning/10 rounded-lg">
                <div class="flex items-start gap-2">
                    <x-mary-icon name="o-information-circle" class="w-5 h-5 text-warning" />
                    <div class="text-sm">
                        <p class="font-semibold mb-1">API Test Mode</p>
                        <p class="text-xs opacity-75">This will make a real API call to create a queue entry. Use valid
                            data or click "Fill Sample" button.</p>
                    </div>
                </div>
            </div>


            <x-slot:actions>
                <x-mary-button label="Test Connection" wire:click="testApiConnection" class="btn-ghost btn-sm"
                    icon="o-signal" />
                <x-mary-button label="Fill Sample" wire:click="fillSampleData" class="btn-ghost" icon="o-beaker" />
                <x-mary-button label="Cancel" wire:click="$set('showTestApiModal', false)" class="btn-ghost" />
                <x-mary-button label="Create Queue" type="submit" class="btn-primary" spinner="submitTestApi"
                    icon="o-plus-circle" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    @script
        <script>
            // Listen for queue status changes and play sound
            $wire.on('queue-status-changed', (event) => {
                console.log('Queue status changed:', event.queueId);
            });

            $wire.on('play-notification-sound', () => {
                // Play notification sound when prescription is ready
                const audio = new Audio('/sounds/notification.mp3');
                audio.play().catch(e => console.log('Audio play failed:', e));
            });
        </script>
    @endscript
</div>
