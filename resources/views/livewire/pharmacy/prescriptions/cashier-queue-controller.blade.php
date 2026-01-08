<div class="p-6 space-y-6">
    {{-- Header Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="stat bg-warning/10 rounded-lg">
            <div class="stat-value text-warning">{{ $stats['charging'] }}</div>
            <div class="stat-title">Waiting Payment</div>
        </div>
        <div class="stat bg-success/10 rounded-lg">
            <div class="stat-value text-success">{{ $stats['ready'] }}</div>
            <div class="stat-title">Paid - Ready</div>
        </div>
        <div class="stat bg-base-200 rounded-lg">
            <div class="stat-value">{{ $stats['dispensed'] }}</div>
            <div class="stat-title">Dispensed Today</div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        {{-- Left: Current Queue at Cashier --}}
        <div class="col-span-5 space-y-4">
            <x-mary-card title="Cashier Window - Current Queue" class="border-2 border-warning">
                @if ($currentQueue)
                    <div class="space-y-4">
                        {{-- Queue Info --}}
                        <div class="text-center">
                            <div class="text-6xl font-bold text-warning mb-2">
                                {{ $currentQueue->queue_number }}
                            </div>

                            <div class="badge badge-lg badge-warning mb-2">
                                AT CASHIER
                            </div>

                            @if ($currentQueue->priority !== 'normal')
                                <div class="badge badge-error badge-sm">
                                    {{ strtoupper($currentQueue->priority) }}
                                </div>
                            @endif

                            @if ($currentQueue->assigned_window)
                                <div class="mt-2 text-sm opacity-70">
                                    From Window {{ $currentQueue->assigned_window }}
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
                                <div class="text-xs opacity-60">{{ $currentQueue->patient->hpercode }}</div>
                            </div>
                        @endif

                        {{-- Timing Info --}}
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="bg-base-200 p-2 rounded">
                                <div class="opacity-70">Sent to Cashier</div>
                                <div class="font-semibold">{{ $currentQueue->charging_at->format('h:i A') }}</div>
                            </div>
                            <div class="bg-base-200 p-2 rounded">
                                <div class="opacity-70">Waiting Time</div>
                                <div class="font-semibold">
                                    {{ floor($currentQueue->charging_at->diffInMinutes(now())) }} min</div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="space-y-2 pt-4 border-t">
                            <button wire:click="callQueue" class="btn btn-warning btn-block btn-lg">
                                <x-mary-icon name="o-megaphone" class="w-5 h-5" />
                                CALL QUEUE
                            </button>

                            <button wire:click="confirmPayment" class="btn btn-success btn-block btn-lg">
                                <x-mary-icon name="o-check-circle" class="w-5 h-5" />
                                CONFIRM PAYMENT
                            </button>

                            <div class="flex gap-2">
                                <button wire:click="skipQueue" class="btn btn-ghost btn-sm flex-1">
                                    Skip
                                </button>
                                <button wire:click="viewQueue({{ $currentQueue->id }})"
                                    class="btn btn-ghost btn-sm flex-1">
                                    Details
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-12">
                        <x-mary-icon name="o-inbox" class="w-16 h-16 mx-auto text-gray-400 mb-4" />
                        <div class="text-gray-500 mb-4">No queues waiting for payment</div>
                        <button wire:click="$refresh" class="btn btn-ghost">
                            <x-mary-icon name="o-arrow-path" class="w-5 h-5" />
                            Refresh
                        </button>
                    </div>
                @endif
            </x-mary-card>
        </div>

        {{-- Right: Payment Queue List --}}
        <div class="col-span-7">
            <x-mary-card title="Payment Queue">
                <x-slot:menu>
                    <div class="flex gap-2 items-center">
                        <x-mary-input wire:model.live="dateFilter" type="date" class="input-sm" />
                        <button wire:click="$refresh" class="btn btn-sm btn-ghost">
                            <x-mary-icon name="o-arrow-path" class="w-4 h-4" />
                        </button>
                    </div>
                </x-slot:menu>

                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Queue #</th>
                                <th>Patient</th>
                                <th>Window</th>
                                <th>Priority</th>
                                <th>Time</th>
                                <th>Waiting</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($queues as $queue)
                                <tr
                                    class="hover {{ $currentQueue && $currentQueue->id === $queue->id ? 'bg-warning/20' : '' }}">
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
                                        @if ($queue->assigned_window)
                                            <div class="badge badge-sm">W{{ $queue->assigned_window }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($queue->priority !== 'normal')
                                            <div class="badge badge-xs badge-error">
                                                {{ strtoupper($queue->priority) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        {{ $queue->charging_at->format('h:i A') }}
                                    </td>
                                    <td class="text-xs">
                                        {{ floor($queue->charging_at->diffInMinutes(now())) }}m
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <button wire:click="forceConfirmPayment({{ $queue->id }})"
                                                class="btn btn-xs btn-success">
                                                Confirm
                                            </button>
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
                                        No queues waiting for payment
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
                        <div class="badge badge-warning">AT CASHIER</div>
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
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($selectedQueue->prescription_items as $item)
                                        <tr>
                                            <td>{{ $item->drug_concat }}</td>
                                            <td>{{ $item->qty }}</td>
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
