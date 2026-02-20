<div>
    {{-- Global Loading Indicator --}}
    <x-mary-hr class="progress-primary" />

    {{-- Header with Stats --}}
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-2xl font-bold">Prescription Queue Management</h1>
                <p class="text-sm text-gray-500">Manage prescription queues for
                    {{ auth()->user()->location->description }}</p>
            </div>
            <div class="flex gap-4 items-center">
                {{-- Live Clock --}}
                <div class="text-right">
                    <div class="text-lg font-bold" id="live-clock"></div>
                    <div class="text-xs opacity-70">{{ now()->format('l, F j, Y') }}</div>
                </div>

                <div class="flex gap-2">
                    @if (env('APP_ENV') === 'local')
                        <x-mary-button label="Batch Create Queues" icon="o-plus-circle" class="btn-primary"
                            wire:click="openBatchCreateModal">
                            <x-mary-loading wire:loading wire:target="openBatchCreateModal,executeBatchCreate"
                                class="loading-spinner loading-sm" />
                        </x-mary-button>
                    @endif
                    <x-mary-button icon="o-arrow-path" class="btn-ghost" wire:click="$refresh" tooltip="Refresh">
                        <x-mary-loading wire:loading wire:target="$refresh" class="loading-spinner loading-sm" />
                    </x-mary-button>
                </div>
            </div>
        </div>

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-6">
            <div class="p-4 shadow-lg card bg-base-100">
                <div class="text-xs opacity-70">Total</div>
                <div class="text-2xl font-bold">{{ $stats['total'] }}</div>
            </div>
            <div class="p-4 shadow-lg card bg-warning/10">
                <div class="text-xs opacity-70">Waiting</div>
                <div class="text-2xl font-bold text-warning">{{ $stats['waiting'] }}</div>
            </div>
            <div class="p-4 shadow-lg card bg-info/10">
                <div class="text-xs opacity-70">Preparing</div>
                <div class="text-2xl font-bold text-info">{{ $stats['preparing'] }}</div>
            </div>
            <div class="p-4 shadow-lg card bg-success/10">
                <div class="text-xs opacity-70">Ready</div>
                <div class="text-2xl font-bold text-success">{{ $stats['ready'] }}</div>
            </div>
            <div class="p-4 shadow-lg card bg-base-100">
                <div class="text-xs opacity-70">Dispensed</div>
                <div class="text-2xl font-bold text-gray-500">{{ $stats['dispensed'] }}</div>
            </div>
            <div class="p-4 shadow-lg card bg-error/10">
                <div class="text-xs opacity-70">Cancelled</div>
                <div class="text-2xl font-bold text-error">{{ $stats['cancelled'] }}</div>
            </div>
        </div>

        {{-- Average Times --}}
        @if ($stats['avg_wait_time'] > 0)
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div class="p-3 shadow card bg-base-100">
                    <div class="text-xs opacity-70">Avg Total Time</div>
                    <div class="text-lg font-bold">{{ floor($stats['avg_wait_time']) }} min</div>
                </div>
                <div class="p-3 shadow card bg-base-100">
                    <div class="text-xs opacity-70">Avg Processing Time</div>
                    <div class="text-lg font-bold">{{ floor($stats['avg_processing_time']) }} min</div>
                </div>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="mb-6 shadow-lg card bg-base-100">
        <div class="card-body">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <x-mary-input wire:model.live.debounce.300ms="search" placeholder="Search queue, patient, encounter..."
                    icon="o-magnifying-glass" clearable />

                <select class="select select-bordered" wire:model.live="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="waiting">Waiting</option>
                    <option value="preparing">Preparing</option>
                    <option value="ready">Ready</option>
                    <option value="dispensed">Dispensed</option>
                    <option value="cancelled">Cancelled</option>
                </select>

                <select class="select select-bordered" wire:model.live="priorityFilter">
                    <option value="">All Priorities</option>
                    <option value="normal">Normal</option>
                    <option value="urgent">Urgent</option>
                    <option value="stat">STAT</option>
                </select>

                <x-mary-input type="date" wire:model.live="dateFilter" icon="o-calendar" />

                <select class="select select-bordered" wire:model.live="perPage">
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Queues Table --}}
    <div class="shadow-lg card bg-base-100">
        <div class="card-body">
            <div class="overflow-x-auto">
                <table class="table w-full table-zebra table-sm">
                    <thead class="bg-base-200">
                        <tr class="text-xs uppercase">
                            <th>Queue #</th>
                            <th>Patient</th>
                            <th>Encounter</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Queued At</th>
                            <th>Wait Time</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($queues as $queue)
                            <tr class="hover" wire:key="queue-{{ $queue->id }}">
                                <td>
                                    <div class="font-mono font-bold">{{ $queue->queue_number }}</div>
                                    <div class="text-xs opacity-70">Seq: {{ $queue->sequence_number }}</div>
                                </td>
                                <td>
                                    <div class="font-medium">{{ $queue->hpercode }}</div>
                                    @if ($queue->patient)
                                        <div class="text-xs opacity-70">{{ $queue->patient->fullname }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="badge badge-ghost badge-sm">{{ $queue->queue_prefix }}</div>
                                    <div class="text-xs opacity-70">{{ $queue->enccode }}</div>
                                </td>
                                <td>
                                    <div class="badge {{ $queue->getPriorityBadgeClass() }} badge-sm">
                                        {{ strtoupper($queue->priority) }}
                                    </div>
                                </td>
                                <td>
                                    <div class="badge {{ $queue->getStatusBadgeClass() }} badge-sm">
                                        {{ strtoupper($queue->queue_status) }}
                                    </div>
                                </td>
                                <td>
                                    <div class="text-sm">{{ $queue->queued_at->format('h:i A') }}</div>
                                    <div class="text-xs opacity-70">{{ $queue->queued_at->format('M d') }}</div>
                                </td>
                                <td>
                                    <div class="text-sm font-medium">{{ $queue->getWaitTimeMinutes() }} min</div>
                                    @if ($queue->estimated_wait_minutes)
                                        <div class="text-xs opacity-70">Est: {{ $queue->estimated_wait_minutes }}m
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <x-mary-button class="btn btn-xs btn-ghost"
                                            wire:click="viewQueue({{ $queue->id }})" tooltip="View Details">
                                            <x-mary-icon name="o-eye" class="w-4 h-4" />
                                            <x-mary-loading wire:loading wire:target="viewQueue"
                                                class="loading-spinner loading-xs" />
                                        </x-mary-button>

                                        @if ($queue->isWaiting())
                                            <x-mary-button class="btn btn-xs btn-info"
                                                wire:click="openStatusModal({{ $queue->id }}, 'preparing')"
                                                tooltip="Start Preparing">
                                                <x-mary-icon name="o-play" class="w-4 h-4" />
                                                <x-mary-loading wire:loading wire:target="openStatusModal"
                                                    class="loading-spinner loading-xs" />
                                            </x-mary-button>
                                        @endif

                                        @if ($queue->isPreparing())
                                            <x-mary-button class="btn btn-xs btn-success"
                                                wire:click="callQueue({{ $queue->id }})"
                                                tooltip="Call for Pickup">
                                                <x-mary-icon name="o-bell-alert" class="w-4 h-4" />
                                                <x-mary-loading wire:loading wire:target="callQueue"
                                                    class="loading-spinner loading-xs" />
                                            </x-mary-button>
                                        @endif

                                        @if ($queue->isReady())
                                            <x-mary-button class="btn btn-xs btn-primary"
                                                wire:click="openStatusModal({{ $queue->id }}, 'dispensed')"
                                                tooltip="Mark Dispensed">
                                                <x-mary-icon name="o-check" class="w-4 h-4" />
                                                <x-mary-loading wire:loading wire:target="openStatusModal"
                                                    class="loading-spinner loading-xs" />
                                            </x-mary-button>
                                        @endif

                                        @if ($queue->isActive())
                                            <x-mary-button class="btn btn-xs btn-error"
                                                wire:confirm="Cancel this queue?"
                                                wire:click="$dispatch('cancel-queue', { queueId: {{ $queue->id }} })"
                                                tooltip="Cancel">
                                                <x-mary-icon name="o-x-mark" class="w-4 h-4" />
                                                <x-mary-loading wire:loading wire:target="cancelQueue"
                                                    class="loading-spinner loading-xs" />
                                            </x-mary-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <x-mary-icon name="o-queue-list" class="w-16 h-16 text-gray-300" />
                                        <p class="mt-3 font-medium text-gray-500">No queues found</p>
                                        <p class="text-sm text-gray-400">Try adjusting your filters or create new
                                            queues</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($queues->hasPages())
                <div class="mt-4">
                    {{ $queues->links() }}
                </div>
            @endif
        </div>
    </div>

    @if (env('APP_ENV') === 'local')
        {{-- Batch Create Modal --}}
        <x-mary-modal wire:model="showBatchCreateModal" title="Batch Create Prescription Queues"
            class="backdrop-blur" box-class="max-w-2xl">
            <div class="space-y-4">
                <div class="p-4 rounded-lg alert alert-info">
                    <x-mary-icon name="o-information-circle" class="w-5 h-5" />
                    <span>This will automatically create queues for prescriptions matching your criteria.</span>
                </div>

                <x-mary-input label="Date" type="date" wire:model.live="batchDate" icon="o-calendar"
                    hint="Prescriptions from this date onwards" />

                <x-mary-select label="Location" wire:model.live="batchLocation" :options="$locations" option-value="id"
                    option-label="description" icon="o-map-pin" />

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-bold">Encounter Types</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($availableTypes as $code => $name)
                            <label class="cursor-pointer label">
                                <span class="label-text">{{ $name }}</span>
                                <input type="checkbox" class="checkbox checkbox-primary" wire:model.live="batchTypes"
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
                    <x-mary-loading wire:loading wire:target="executeBatchCreate"
                        class="loading-spinner loading-sm" />
                </x-mary-button>
            </x-slot:actions>
        </x-mary-modal>
    @endif

    {{-- Queue Details Modal --}}
    @if ($selectedQueue)
        <x-mary-modal wire:model="showDetailsModal" title="Queue Details" class="backdrop-blur"
            box-class="max-w-3xl">
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
                    <div class="p-4 rounded-lg border border-base-300">
                        <h3 class="mb-2 font-bold">Patient Information</h3>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="opacity-70">Hospital #:</span>
                                <span class="font-medium">{{ $selectedQueue->hpercode }}</span>
                            </div>
                            <div>
                                <span class="opacity-70">Name:</span>
                                <span class="font-medium">{{ $selectedQueue->patient->fullname }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Prescription Items --}}
                @if ($selectedQueue->prescription && isset($selectedQueue->prescription_items))
                    <div class="p-4 rounded-lg border border-base-300">
                        <h3 class="mb-3 font-bold text-lg">Prescription Items
                            ({{ $selectedQueue->prescription_items->count() }})</h3>
                        <div class="space-y-3">
                            @foreach ($selectedQueue->prescription_items as $item)
                                <div class="p-4 rounded-lg border border-base-200 bg-base-50">
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

                                    {{-- Drug Name and Order Type --}}
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex-1">
                                            <div class="font-bold text-base">{{ $drugName }}</div>
                                        </div>
                                        <div class="badge {{ $orderTypeBadge }} badge-sm font-semibold ml-2">
                                            {{ $orderType }}
                                        </div>
                                    </div>

                                    {{-- Dosage Details --}}
                                    <div class="grid grid-cols-3 gap-3 text-sm mb-2">
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

                                    {{-- Remarks --}}
                                    @if ($item->remark)
                                        <div class="text-sm mb-1">
                                            <span class="opacity-70">Remark:</span>
                                            <span class="italic">{{ $item->remark }}</span>
                                        </div>
                                    @endif

                                    @if ($item->addtl_remarks)
                                        <div class="text-sm mb-1">
                                            <span class="opacity-70">Additional Remarks:</span>
                                            <span class="italic">{{ $item->addtl_remarks }}</span>
                                        </div>
                                    @endif

                                    {{-- Take Home Indicator --}}
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

                {{-- Timeline --}}
                <div class="p-4 rounded-lg border border-base-300">
                    <h3 class="mb-2 font-bold">Timeline</h3>
                    <div class="space-y-2 text-sm">
                        @foreach ($selectedQueue->logs as $log)
                            <div class="flex items-start gap-2">
                                <div class="w-16 text-xs opacity-70">{{ $log->created_at->format('h:i A') }}</div>
                                <div class="flex-1">
                                    <div class="font-medium">{{ $log->getStatusChangeLabel() }}</div>
                                    @if ($log->changer)
                                        <div class="text-xs opacity-70">By: {{ $log->changer->fullname }}</div>
                                    @endif
                                    @if ($log->remarks)
                                        <div class="text-xs opacity-70">{{ $log->remarks }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
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

{{-- JavaScript for Live Clock --}}
<script>
    // Update live clock every second
    function updateClock() {
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes();
        const seconds = now.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;

        const timeString =
            `${displayHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} ${ampm}`;

        const clockElement = document.getElementById('live-clock');
        if (clockElement) {
            clockElement.textContent = timeString;
        }
    }

    // Initialize and update every second
    document.addEventListener('DOMContentLoaded', function() {
        updateClock();
        setInterval(updateClock, 1000);
    });

    // Re-initialize on Livewire navigation
    document.addEventListener('livewire:navigated', function() {
        updateClock();
        setInterval(updateClock, 1000);
    });
</script>
