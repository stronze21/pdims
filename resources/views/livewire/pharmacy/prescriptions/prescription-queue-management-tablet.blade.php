<div class="h-full">
    {{-- Global Loading Bar --}}
    <x-mary-hr class="progress-primary" />

    {{-- Two Column Layout: Stats & Quick Actions | Queue Grid --}}
    <div class="grid h-full grid-cols-12 gap-4 p-4">

        {{-- Left Sidebar: Stats & Actions (4 columns) --}}
        <div class="col-span-4 space-y-4 overflow-y-auto hide-scrollbar">

            {{-- Quick Stats Grid --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="p-4 shadow-lg card bg-warning/10">
                    <div class="text-xs font-semibold uppercase opacity-70">Waiting</div>
                    <div class="flex items-end justify-between">
                        <div class="text-4xl font-bold text-warning">{{ $stats['waiting'] ?? 0 }}</div>
                        <x-mary-icon name="o-clock" class="w-8 h-8 opacity-30" />
                    </div>
                    @if (($stats['avg_waiting_time'] ?? 0) > 0)
                        <div class="mt-1 text-xs opacity-70">Avg: {{ floor($stats['avg_waiting_time']) }}m</div>
                    @endif
                </div>

                <div class="p-4 shadow-lg card bg-info/10">
                    <div class="text-xs font-semibold uppercase opacity-70">Preparing</div>
                    <div class="flex items-end justify-between">
                        <div class="text-4xl font-bold text-info">{{ $stats['preparing'] ?? 0 }}</div>
                        <x-mary-icon name="o-beaker" class="w-8 h-8 opacity-30" />
                    </div>
                    @if (($stats['avg_preparing_time'] ?? 0) > 0)
                        <div class="mt-1 text-xs opacity-70">Avg: {{ floor($stats['avg_preparing_time']) }}m</div>
                    @endif
                </div>

                <div class="p-4 shadow-lg card bg-success/10">
                    <div class="text-xs font-semibold uppercase opacity-70">Ready</div>
                    <div class="flex items-end justify-between">
                        <div class="text-4xl font-bold text-success">{{ $stats['ready'] ?? 0 }}</div>
                        <x-mary-icon name="o-check-circle" class="w-8 h-8 opacity-30" />
                    </div>
                    @if (($stats['avg_ready_time'] ?? 0) > 0)
                        <div class="mt-1 text-xs opacity-70">Avg: {{ floor($stats['avg_ready_time']) }}m</div>
                    @endif
                </div>

                <div class="p-4 shadow-lg card bg-base-100">
                    <div class="text-xs font-semibold uppercase opacity-70">Dispensed</div>
                    <div class="flex items-end justify-between">
                        <div class="text-4xl font-bold">{{ $stats['dispensed'] ?? 0 }}</div>
                        <x-mary-icon name="o-check-badge" class="w-8 h-8 opacity-30" />
                    </div>
                    @if (($stats['avg_total_time'] ?? 0) > 0)
                        <div class="mt-1 text-xs opacity-70">Total: {{ floor($stats['avg_total_time']) }}m</div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="p-4 shadow-lg card bg-base-100">
                <h3 class="mb-3 text-sm font-bold">Quick Actions</h3>
                <div class="space-y-2">
                    <x-mary-button label="Call Next" icon="o-megaphone" class="w-full btn-success touch-target"
                        wire:click="callNextQueue">
                        <x-mary-loading wire:loading wire:target="callNextQueue" class="loading-spinner loading-sm" />
                    </x-mary-button>

                    @can('manual-queue')
                        <x-mary-button label="Batch Create Queues" icon="o-plus-circle"
                            class="w-full btn-primary touch-target" wire:click="openBatchCreateModal">
                            <x-mary-loading wire:loading wire:target="openBatchCreateModal,executeBatchCreate"
                                class="loading-spinner loading-sm" />
                        </x-mary-button>
                    @endcan

                    <x-mary-button label="Refresh Queues" icon="o-arrow-path" class="w-full btn-ghost touch-target"
                        wire:click="$refresh">
                        <x-mary-loading wire:loading wire:target="$refresh" class="loading-spinner loading-sm" />
                    </x-mary-button>
                </div>
            </div>

            {{-- Window Selection --}}
            <div class="p-4 shadow-lg card bg-base-100">
                <h3 class="mb-3 text-sm font-bold">Dispensing Window</h3>
                <div class="space-y-3">
                    <select class="w-full select select-bordered" wire:model.live="selectedWindow">
                        <option value="">All Windows</option>
                        @foreach (range(1, $maxWindows) as $windowNum)
                            <option value="{{ $windowNum }}">Window {{ $windowNum }}</option>
                        @endforeach
                    </select>

                    @if ($selectedWindow)
                        <div class="p-3 rounded-lg bg-primary/10">
                            <div class="text-xs opacity-70">Current Window</div>
                            <div class="text-2xl font-bold text-primary">Window {{ $selectedWindow }}</div>
                            <div class="mt-2 text-xs">
                                You will only see queues assigned to this window.
                            </div>
                        </div>
                    @endif

                    <div class="text-xs opacity-70">
                        💡 Select your window to avoid conflicts with other pharmacists
                    </div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="p-4 shadow-lg card bg-base-100">
                <h3 class="mb-3 text-sm font-bold">Filters</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-semibold">Status</label>
                        <select class="w-full select select-bordered select-sm" wire:model.live="statusFilter">
                            <option value="">All Statuses</option>
                            <option value="waiting">Waiting</option>
                            <option value="preparing">Preparing</option>
                            <option value="ready">Ready</option>
                            <option value="dispensed">Dispensed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold">Priority</label>
                        <select class="w-full select select-bordered select-sm" wire:model.live="priorityFilter">
                            <option value="">All Priorities</option>
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                            <option value="stat">STAT</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold">Date</label>
                        <input type="date" class="w-full input input-bordered input-sm"
                            wire:model.live="dateFilter" />
                    </div>

                    <x-mary-input wire:model.live.debounce.300ms="search" placeholder="Search..."
                        icon="o-magnifying-glass" clearable />
                </div>
            </div>
        </div>

        {{-- Right: Queue Grid (8 columns) --}}
        <div class="col-span-8 overflow-hidden">
            <div class="h-full p-4 shadow-lg card bg-base-100">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-bold">Active Queues ({{ $queues->total() }})</h2>
                    <select class="select select-bordered select-sm" wire:model.live="perPage">
                        <option value="12">12 per page</option>
                        <option value="24">24 per page</option>
                        <option value="48">48 per page</option>
                    </select>
                </div>

                {{-- Queue Cards Grid --}}
                <div class="overflow-y-auto hide-scrollbar" style="height: calc(100vh - 220px);">
                    @if ($queues->count() > 0)
                        <div class="grid grid-cols-1 gap-3 pb-4">
                            @foreach ($queues as $queue)
                                <div
                                    class="border-2 shadow-md card bg-base-50
                                    {{ $queue->priority === 'stat' ? 'border-error' : '' }}
                                    {{ $queue->priority === 'urgent' ? 'border-warning' : '' }}
                                    {{ $queue->queue_status === 'ready' ? 'bg-success/5 border-success' : '' }}
                                    smooth-transition hover:shadow-lg">

                                    <div class="p-4 card-body">
                                        {{-- Header --}}
                                        <div class="flex items-start justify-between mb-2">
                                            <div>
                                                <div class="text-2xl font-bold text-primary">
                                                    {{ $queue->queue_number }}
                                                </div>
                                                <div class="text-xs opacity-70">
                                                    {{ $queue->queued_at->format('h:i A') }}
                                                </div>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <div class="badge {{ $queue->getStatusBadgeClass() }} badge-sm">
                                                    {{ strtoupper($queue->queue_status) }}
                                                </div>
                                                @if ($queue->priority !== 'normal')
                                                    <div class="badge {{ $queue->getPriorityBadgeClass() }} badge-sm">
                                                        {{ strtoupper($queue->priority) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Patient Info --}}
                                        @if ($queue->patient)
                                            <div class="mb-2">
                                                <div class="text-sm font-semibold truncate">
                                                    {{ $queue->patient->fullname() }}
                                                </div>
                                                <div class="text-xs opacity-70">{{ $queue->hpercode }}</div>
                                            </div>
                                        @endif

                                        {{-- Wait Time --}}
                                        <div class="mb-3 text-xs">
                                            <span class="opacity-70">Wait:</span>
                                            <span class="font-bold">{{ $queue->getWaitTimeMinutes() }} min</span>
                                            @if ($queue->estimated_wait_minutes)
                                                <span class="opacity-70">(Est:
                                                    {{ $queue->estimated_wait_minutes }}m)</span>
                                            @endif
                                        </div>

                                        {{-- Action Buttons --}}
                                        <div class="grid grid-cols-2 gap-2">
                                            <button class="btn btn-xs btn-ghost btn-outline touch-target"
                                                wire:click="viewQueue({{ $queue->id }})">
                                                <x-mary-icon name="o-eye" class="w-4 h-4" />
                                                View
                                                <x-mary-loading wire:loading wire:target="viewQueue"
                                                    class="loading-spinner loading-xs" />
                                            </button>

                                            @if ($queue->isWaiting())
                                                <button class="btn btn-xs btn-info touch-target"
                                                    wire:click="openStatusModal({{ $queue->id }}, 'preparing')">
                                                    <x-mary-icon name="o-play" class="w-4 h-4" />
                                                    Start
                                                    <x-mary-loading wire:loading wire:target="openStatusModal"
                                                        class="loading-spinner loading-xs" />
                                                </button>
                                            @endif

                                            @if ($queue->isPreparing())
                                                <button class="btn btn-xs btn-success touch-target"
                                                    wire:click="callQueue({{ $queue->id }})">
                                                    <x-mary-icon name="o-bell-alert" class="w-4 h-4" />
                                                    Call
                                                    <x-mary-loading wire:loading wire:target="callQueue"
                                                        class="loading-spinner loading-xs" />
                                                </button>
                                            @endif

                                            @if ($queue->isReady())
                                                <button class="btn btn-xs btn-primary touch-target"
                                                    wire:click="openStatusModal({{ $queue->id }}, 'dispensed')">
                                                    <x-mary-icon name="o-check" class="w-4 h-4" />
                                                    Done
                                                    <x-mary-loading wire:loading wire:target="openStatusModal"
                                                        class="loading-spinner loading-xs" />
                                                </button>
                                            @endif

                                            @if ($queue->isActive())
                                                <button class="btn btn-xs btn-error touch-target"
                                                    wire:confirm="Cancel this queue?"
                                                    wire:click="$dispatch('cancel-queue', { queueId: {{ $queue->id }} })">
                                                    <x-mary-icon name="o-x-mark" class="w-4 h-4" />
                                                    Cancel
                                                    <x-mary-loading wire:loading wire:target="cancelQueue"
                                                        class="loading-spinner loading-xs" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Pagination --}}
                        @if ($queues->hasPages())
                            <div class="mt-4">
                                {{ $queues->links() }}
                            </div>
                        @endif
                    @else
                        <div class="flex flex-col items-center justify-center h-full py-12">
                            <x-mary-icon name="o-queue-list" class="w-20 h-20 text-gray-300" />
                            <p class="mt-4 text-lg font-medium text-gray-500">No Queues Found</p>
                            <p class="text-sm text-gray-400">Try adjusting your filters</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modals --}}

    {{-- Batch Create Modal --}}
    <x-mary-modal wire:model="showBatchCreateModal" title="Batch Create Prescription Queues" class="backdrop-blur"
        box-class="max-w-2xl">
        <div class="space-y-4">
            <x-mary-input label="Date" type="date" wire:model="batchDate" />

            <div>
                <label class="block mb-2 text-sm font-semibold">Encounter Types</label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($availableTypes as $code => $name)
                        <label class="cursor-pointer label">
                            <span class="label-text">{{ $name }}</span>
                            <input type="checkbox" class="checkbox checkbox-primary" wire:model="batchTypes"
                                value="{{ $code }}" />
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="p-4 rounded-lg bg-base-200">
                <x-mary-button class="btn btn-sm btn-outline" wire:click="previewBatchCreate">
                    <x-mary-icon name="o-eye" class="w-4 h-4" />
                    Preview Prescriptions
                    <x-mary-loading wire:loading wire:target="previewBatchCreate"
                        class="loading-spinner loading-xs" />
                </x-mary-button>
                <div class="mt-2 text-xs opacity-70">
                    Click to preview how many prescriptions will be queued
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showBatchCreateModal', false)" />
            <x-mary-button label="Create Queues" class="btn-primary" wire:click="executeBatchCreate"
                wire:confirm="Are you sure you want to create queues for the selected prescriptions?">
                <x-mary-loading wire:loading wire:target="executeBatchCreate" class="loading-spinner loading-sm" />
            </x-mary-button>
        </x-slot:actions>
    </x-mary-modal>

    {{-- Queue Details Modal --}}
    @if ($selectedQueue)
        <x-mary-modal wire:model="showDetailsModal" title="Queue Details" class="backdrop-blur"
            box-class="max-w-5xl">
            <div class="space-y-4">
                {{-- Queue Info --}}
                <div class="p-4 rounded-lg bg-base-200">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs opacity-70">Queue Number</div>
                            <div class="text-xl font-bold">{{ $selectedQueue->queue_number }}</div>
                        </div>
                        <div>
                            <div class="text-xs opacity-70">Status</div>
                            <div class="badge {{ $selectedQueue->getStatusBadgeClass() }}">
                                {{ strtoupper($selectedQueue->queue_status) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs opacity-70">Priority</div>
                            <div class="badge {{ $selectedQueue->getPriorityBadgeClass() }}">
                                {{ strtoupper($selectedQueue->priority) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs opacity-70">Wait Time</div>
                            <div class="font-medium">{{ $selectedQueue->getWaitTimeMinutes() }} minutes</div>
                        </div>
                    </div>
                </div>

                {{-- Patient Info --}}
                @if ($selectedQueue->patient)
                    <div class="p-4 border rounded-lg border-base-300">
                        <h3 class="mb-2 font-bold">Patient Information</h3>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="opacity-70">Hospital #:</span>
                                <span class="font-medium">{{ $selectedQueue->hpercode }}</span>
                            </div>
                            <div>
                                <span class="opacity-70">Name:</span>
                                <span class="font-medium">{{ $selectedQueue->patient->fullname() }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Prescription Items --}}
                @if ($selectedQueue->prescription && isset($selectedQueue->prescription_items))
                    <div class="p-4 border rounded-lg border-base-300">
                        <h3 class="mb-3 text-lg font-bold">Prescription Items
                            ({{ $selectedQueue->prescription_items->count() }})</h3>
                        <div class="space-y-3">
                            @foreach ($selectedQueue->prescription_items as $item)
                                <div class="p-4 border rounded-lg border-base-200 bg-base-50">
                                    @php
                                        $drugParts = explode('_,', $item->drug_concat);
                                        $drugName = implode(' ', $drugParts);
                                        $orderType = $item->order_type ?: 'BASIC';
                                        $orderTypeBadge = match ($orderType) {
                                            'BASIC' => 'badge-ghost',
                                            'G24' => 'badge-warning',
                                            'OR' => 'badge-error',
                                            'STAT' => 'badge-error',
                                            default => 'badge-info',
                                        };
                                    @endphp

                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex-1">
                                            <div class="text-base font-bold">{{ $drugName }}</div>
                                        </div>
                                        <div class="ml-2 badge {{ $orderTypeBadge }} badge-sm">
                                            {{ $orderType }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-3 mb-2 text-sm">
                                        <div>
                                            <span class="opacity-70">Qty:</span>
                                            <span class="font-semibold">{{ $item->qty }}</span>
                                        </div>
                                        @if ($item->frequency)
                                            <div>
                                                <span class="opacity-70">Frequency:</span>
                                                <span class="font-semibold">{{ $item->frequency }}</span>
                                            </div>
                                        @endif
                                        @if ($item->duration)
                                            <div>
                                                <span class="opacity-70">Duration:</span>
                                                <span class="font-semibold">{{ $item->duration }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($item->remark)
                                        <div class="mb-1 text-sm">
                                            <span class="opacity-70">Remark:</span>
                                            <span class="italic">{{ $item->remark }}</span>
                                        </div>
                                    @endif

                                    @if ($item->addtl_remarks)
                                        <div class="mb-1 text-sm">
                                            <span class="opacity-70">Additional Remarks:</span>
                                            <span class="italic">{{ $item->addtl_remarks }}</span>
                                        </div>
                                    @endif

                                    @if ($item->tkehome)
                                        <div class="mt-2">
                                            <div class="badge badge-success badge-sm">
                                                <x-mary-icon name="o-home" class="w-3 h-3 mr-1" />
                                                Take Home
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Activity Log --}}
                @if ($selectedQueue->logs && $selectedQueue->logs->count() > 0)
                    <div class="p-4 border rounded-lg border-base-300">
                        <h3 class="mb-2 font-bold">Activity Log</h3>
                        <div class="space-y-2">
                            @foreach ($selectedQueue->logs->take(5) as $log)
                                <div class="p-2 text-xs rounded bg-base-200">
                                    <div class="font-medium">
                                        {{ $log->status_from ?? 'New' }} → {{ $log->status_to }}
                                    </div>
                                    <div class="opacity-70">
                                        {{ $log->created_at->format('M d, Y h:i A') }}
                                        @if ($log->changer)
                                            by {{ $log->changer->fullname() }}
                                        @endif
                                    </div>
                                    @if ($log->remarks)
                                        <div class="mt-1 italic">{{ $log->remarks }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-mary-button label="Close" wire:click="$set('showDetailsModal', false)" />
            </x-slot:actions>
        </x-mary-modal>
    @endif

    {{-- Status Update Modal --}}
    <x-mary-modal wire:model="showStatusModal" title="Update Queue Status" class="backdrop-blur">
        <div class="space-y-4">
            <div class="p-4 rounded-lg alert alert-warning">
                <span>Are you sure you want to change the status to
                    <strong>{{ strtoupper($newStatus ?? '') }}</strong>?</span>
            </div>

            <x-mary-textarea label="Remarks (Optional)" wire:model="statusRemarks"
                placeholder="Add any notes about this status change..." rows="3" />
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showStatusModal', false)" />
            <x-mary-button label="Update Status" class="btn-primary" wire:click="updateStatus">
                <x-mary-loading wire:loading wire:target="updateStatus" class="loading-spinner loading-sm" />
            </x-mary-button>
        </x-slot:actions>
    </x-mary-modal>
</div>
