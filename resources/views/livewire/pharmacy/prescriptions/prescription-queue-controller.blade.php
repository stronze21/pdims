<div class="p-6 space-y-6">
    {{-- Current Queue Window --}}
    <div class="grid grid-cols-12 gap-6">
        {{-- Left: Current Queue (col-4) --}}
        <div class="col-span-4 space-y-4">
            <x-mary-card title="Window {{ $selectedWindow }} - Current Queue" class="border-2 border-primary">
                @if ($currentQueue)
                    <div class="space-y-4">
                        {{-- Queue Info --}}
                        <div class="text-center">
                            <div class="text-5xl font-bold text-primary mb-2">
                                {{ $currentQueue->queue_number }}
                            </div>

                            <div class="badge badge-lg {{ $currentQueue->getStatusBadgeClass() }} mb-2">
                                {{ strtoupper($currentQueue->queue_status) }}
                            </div>

                            @if ($currentQueue->priority !== 'normal')
                                <div class="badge badge-error badge-sm">
                                    {{ strtoupper($currentQueue->priority) }}
                                </div>
                            @endif
                        </div>

                        {{-- Patient Info --}}
                        @if ($currentQueue->patient)
                            <div class="bg-base-200 p-3 rounded">
                                <div class="text-sm opacity-70">Patient</div>
                                <div class="font-semibold">
                                    {{ $currentQueue->patient->patlast }}, {{ $currentQueue->patient->patfirst }}
                                </div>
                            </div>
                        @endif

                        {{-- Timing Info --}}
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="bg-base-200 p-2 rounded">
                                <div class="opacity-70">Queued</div>
                                <div class="font-semibold">{{ $currentQueue->queued_at->format('h:i A') }}</div>
                            </div>
                            <div class="bg-base-200 p-2 rounded">
                                <div class="opacity-70">Wait Time</div>
                                <div class="font-semibold">{{ $currentQueue->getWaitTimeMinutes() }} min</div>
                            </div>
                        </div>

                        {{-- Action Buttons Based on Status --}}
                        <div class="space-y-2 pt-4 border-t">
                            @if ($currentQueue->isPreparing() && !$currentQueue->called_at)
                                {{-- Stage 1: Preparing → Call Patient --}}
                                <button wire:click="callQueue" class="btn btn-primary btn-block btn-lg touch-target">
                                    <x-mary-icon name="o-megaphone" class="w-5 h-5" />
                                    CALL PATIENT
                                </button>
                            @elseif ($currentQueue->isPreparing() && $currentQueue->called_at)
                                {{-- Stage 2: Patient Called → Issue Charge Slip --}}
                                <button wire:click="moveToCharging"
                                    class="btn btn-secondary btn-block btn-lg touch-target">
                                    <x-mary-icon name="o-document-text" class="w-5 h-5" />
                                    ISSUE CHARGE SLIP
                                </button>
                                <div class="alert alert-info">
                                    <span class="text-xs">Patient called. Click when patient arrives.</span>
                                </div>
                            @elseif ($currentQueue->isCharging())
                                {{-- Stage 3: Charging → Ready for Dispensing --}}
                                <button wire:click="readyForDispensing"
                                    class="btn btn-success btn-block btn-lg touch-target">
                                    <x-mary-icon name="o-check-circle" class="w-5 h-5" />
                                    READY TO DISPENSE
                                </button>
                                <div class="alert alert-warning">
                                    <span class="text-xs">Patient is at cashier. Click when payment is confirmed.</span>
                                </div>
                            @elseif ($currentQueue->isReady())
                                {{-- Stage 4: Ready → Dispense Items --}}
                                <button wire:click="dispenseQueue" class="btn btn-accent btn-block btn-lg touch-target">
                                    <x-mary-icon name="o-cube" class="w-5 h-5" />
                                    DISPENSE ITEMS
                                </button>
                                <div class="alert alert-success">
                                    <span class="text-xs">Patient has paid. Dispense medications.</span>
                                </div>
                            @endif

                            {{-- Secondary Actions --}}
                            <div class="flex gap-2">
                                <button wire:click="skipQueue" class="btn btn-warning btn-sm flex-1 touch-target"
                                    title="Move queue back to waiting (frees up window)">
                                    Skip
                                </button>
                                <button wire:click="cancelCurrentQueue"
                                    class="btn btn-error btn-sm flex-1 touch-target">
                                    Cancel
                                </button>
                                <button wire:click="viewQueue({{ $currentQueue->id }})"
                                    class="btn btn-ghost btn-sm flex-1 touch-target">
                                    Details
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-12">
                        <x-mary-icon name="o-inbox" class="w-16 h-16 mx-auto text-gray-400 mb-4" />
                        <div class="text-gray-500 mb-4">No active queue</div>
                        <button wire:click="nextQueue" class="btn btn-primary touch-target">
                            <x-mary-icon name="o-arrow-right" class="w-5 h-5" />
                            Call Next Queue
                        </button>
                    </div>
                @endif
            </x-mary-card>

            {{-- Quick Stats --}}
            <div class="grid grid-cols-3 gap-2">
                <div class="stat bg-warning/10 rounded-lg p-3">
                    <div class="stat-value text-2xl text-warning">{{ $stats['waiting'] }}</div>
                    <div class="stat-title text-xs">Waiting</div>
                </div>
                <div class="stat bg-secondary/10 rounded-lg p-3">
                    <div class="stat-value text-2xl text-secondary">{{ $stats['charging'] }}</div>
                    <div class="stat-title text-xs">Charging</div>
                </div>
                <div class="stat bg-success/10 rounded-lg p-3">
                    <div class="stat-value text-2xl text-success">{{ $stats['ready'] }}</div>
                    <div class="stat-title text-xs">Ready</div>
                </div>
            </div>
        </div>

        {{-- Right: Queue List (col-8) --}}
        <div class="col-span-8">
            <x-mary-card title="Queue List">
                <x-slot:menu>
                    <div class="flex gap-2 items-center">
                        <select wire:model.live="selectedWindow" class="select select-bordered select-sm">
                            @for ($i = 1; $i <= $maxWindows; $i++)
                                <option value="{{ $i }}">Window {{ $i }}</option>
                            @endfor
                        </select>
                        <x-mary-input wire:model.live="dateFilter" type="date" class="input-sm" />
                        <button wire:click="toggleAvailability"
                            class="btn btn-sm {{ $isAvailable ? 'btn-success' : 'btn-error' }}">
                            {{ $isAvailable ? 'Available' : 'Unavailable' }}
                        </button>
                    </div>
                </x-slot:menu>

                <div class="overflow-x-auto max-h-[calc(100vh-400px)] overflow-y-auto">
                    <table class="table table-sm">
                        <thead class="sticky top-0 bg-base-100 z-10">
                            <tr>
                                <th>Queue #</th>
                                <th>Patient</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Time</th>
                                <th>Wait</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($queues as $queue)
                                <tr class="hover">
                                    <td class="font-mono font-bold">{{ $queue->queue_number }}</td>
                                    <td>
                                        @if ($queue->patient)
                                            <div class="text-sm">
                                                {{ $queue->patient->patlast }}, {{ $queue->patient->patfirst }}
                                            </div>
                                            <div class="text-xs opacity-60">{{ $queue->patient->hpercode }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="badge badge-sm {{ $queue->getStatusBadgeClass() }}">
                                            {{ strtoupper($queue->queue_status) }}
                                        </div>
                                        @if ($queue->assigned_window)
                                            <div class="text-xs opacity-60">W{{ $queue->assigned_window }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($queue->priority !== 'normal')
                                            <div class="badge badge-xs {{ $queue->getPriorityBadgeClass() }}">
                                                {{ strtoupper($queue->priority) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        {{ $queue->queued_at->format('h:i A') }}
                                    </td>
                                    <td class="text-xs">
                                        {{ $queue->getWaitTimeMinutes() }}m
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            @if ($queue->isWaiting() && !$queue->assigned_window)
                                                <button wire:click="forceCall({{ $queue->id }})"
                                                    class="btn btn-xs btn-primary">
                                                    Call
                                                </button>
                                            @endif
                                            <button wire:click="viewQueue({{ $queue->id }})"
                                                class="btn btn-xs btn-ghost">
                                                View
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-8 text-gray-400">
                                        No queues for this date
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $queues->links() }}
                </div>
            </x-mary-card>
        </div>
    </div>

    {{-- Queue Details Modal --}}
    <x-mary-modal wire:model="showDetailsModal" title="Queue Details" box-class="w-11/12 max-w-4xl">
        @if ($selectedQueue)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm opacity-70">Queue Number</div>
                        <div class="text-2xl font-bold">{{ $selectedQueue->queue_number }}</div>
                    </div>
                    <div>
                        <div class="text-sm opacity-70">Status</div>
                        <div class="badge {{ $selectedQueue->getStatusBadgeClass() }}">
                            {{ strtoupper($selectedQueue->queue_status) }}
                        </div>
                    </div>
                </div>

                @if ($selectedQueue->patient)
                    <div class="bg-base-200 p-4 rounded">
                        <div class="font-semibold mb-2">Patient Information</div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>Name: {{ $selectedQueue->patient->patlast }}, {{ $selectedQueue->patient->patfirst }}
                            </div>
                            <div>Code: {{ $selectedQueue->patient->hpercode }}</div>
                        </div>
                    </div>
                @endif

                @if ($selectedQueue->prescription_items ?? false)
                    <div>
                        <div class="font-semibold mb-2">Prescription Items</div>
                        <div class="overflow-x-auto">
                            <table class="table table-xs">
                                <thead>
                                    <tr>
                                        <th>Drug</th>
                                        <th>Qty</th>
                                        <th>Freq</th>
                                        <th>Duration</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($selectedQueue->prescription_items as $item)
                                        <tr>
                                            <td>{{ $item->drug_concat }}</td>
                                            <td>{{ $item->qty }}</td>
                                            <td>{{ $item->frequency }}</td>
                                            <td>{{ $item->duration }}</td>
                                            <td>
                                                <div class="badge badge-xs">{{ $item->order_type }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <button wire:click="$set('showDetailsModal', false)" class="btn">Close</button>
        </x-slot:actions>
    </x-mary-modal>
</div>
