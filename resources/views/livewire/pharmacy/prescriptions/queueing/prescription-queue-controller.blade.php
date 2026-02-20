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
                            @if ($currentQueue->isPreparing())
                                {{-- Preparing → Open Dispensing Window --}}
                                @if ($currentQueue->enccode)
                                    @php
                                        $encryptedEnc = \Illuminate\Support\Facades\Crypt::encrypt(str_replace(' ', '--', $currentQueue->enccode));
                                        $dispensingUrl = route('dispensing.view.enctr', ['enccode' => $encryptedEnc]) . '?queue_id=' . $currentQueue->id;
                                    @endphp
                                    <button
                                        onclick="const width = screen.availWidth; const height = screen.availHeight; window.open('{{ $dispensingUrl }}', 'dispensingApp', `toolbar=no,menubar=no,location=no,status=no,width=${width},height=${height},left=0,top=0`); return false;"
                                        class="btn btn-accent btn-block btn-lg touch-target">
                                        <x-mary-icon name="o-beaker" class="w-5 h-5" />
                                        OPEN DISPENSING
                                    </button>
                                @else
                                    <div class="alert alert-warning">
                                        <span class="text-xs">No encounter linked to this queue.</span>
                                    </div>
                                @endif
                                <div class="alert alert-info">
                                    <span class="text-xs">Preparing medications. Open dispensing to charge and issue items.</span>
                                </div>
                            @elseif ($currentQueue->isReady())
                                {{-- Ready → Waiting for patient to claim --}}
                                <div class="alert alert-success">
                                    <x-mary-icon name="o-check-circle" class="w-5 h-5" />
                                    <span class="text-xs">Items charged. Waiting for patient to claim.</span>
                                </div>
                                @if ($currentQueue->enccode)
                                    @php
                                        $encryptedEnc = \Illuminate\Support\Facades\Crypt::encrypt(str_replace(' ', '--', $currentQueue->enccode));
                                        $dispensingUrl = route('dispensing.view.enctr', ['enccode' => $encryptedEnc]) . '?queue_id=' . $currentQueue->id;
                                    @endphp
                                    <button
                                        onclick="const width = screen.availWidth; const height = screen.availHeight; window.open('{{ $dispensingUrl }}', 'dispensingApp', `toolbar=no,menubar=no,location=no,status=no,width=${width},height=${height},left=0,top=0`); return false;"
                                        class="btn btn-accent btn-block btn-sm touch-target">
                                        <x-mary-icon name="o-beaker" class="w-4 h-4" />
                                        Open Dispensing
                                    </button>
                                @endif
                                <div class="grid grid-cols-2 gap-2">
                                    <button wire:click="dispenseQueue" class="btn btn-success btn-sm touch-target">
                                        <x-mary-icon name="o-check" class="w-4 h-4" />
                                        Mark Dispensed
                                    </button>
                                    <button wire:click="dispenseAndNext" class="btn btn-primary btn-sm touch-target">
                                        <x-mary-icon name="o-forward" class="w-4 h-4" />
                                        Dispense & Next
                                    </button>
                                </div>
                            @endif

                            {{-- Next Queue (always visible) --}}
                            <button wire:click="nextQueue"
                                class="btn btn-outline btn-primary btn-block btn-sm touch-target">
                                <x-mary-icon name="o-arrow-right" class="w-4 h-4" />
                                Next Queue
                            </button>

                            {{-- Secondary Actions --}}
                            <div class="flex gap-2">
                                <button wire:click="skipQueue"
                                    class="btn btn-warning btn-sm flex-1 touch-target tooltip tooltip-right"
                                    data-tip="Move queue back to waiting (frees up window)">
                                    Skip
                                </button>
                                <button wire:click="cancelCurrentQueue"
                                    class="btn btn-error btn-sm flex-1 touch-target">
                                    Cancel
                                </button>
                                <button wire:click="openPrintModal({{ $currentQueue->id }})"
                                    class="btn btn-info btn-sm flex-1 touch-target">
                                    Print
                                </button>
                                <button wire:click="viewQueue({{ $currentQueue->id }})"
                                    class="btn btn-ghost btn-sm flex-1 touch-target">
                                    Details
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <x-mary-icon name="o-inbox" class="w-16 h-16 mx-auto text-gray-400 mb-4" />
                        <div class="text-gray-500 mb-4">No active queue</div>
                        <button wire:click="nextQueue" class="btn btn-primary btn-lg touch-target">
                            <x-mary-icon name="o-arrow-right" class="w-5 h-5" />
                            Call Next Queue
                        </button>
                        <div class="text-xs text-gray-400 mt-2">or select a specific queue from the table</div>
                    </div>
                @endif
            </x-mary-card>

            {{-- Next Charging Queue Card --}}
            @if ($nextChargingQueue)
                <x-mary-card class="border-2 border-warning bg-warning/5">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-warning">
                            <x-mary-icon name="o-clock" class="w-6 h-6" />
                            <span class="font-semibold">Patient at Cashier</span>
                        </div>

                        <div class="text-center">
                            <div class="text-4xl font-bold mb-1">{{ $nextChargingQueue->queue_number }}</div>
                            @if ($nextChargingQueue->patient)
                                <div class="text-sm font-semibold">
                                    {{ $nextChargingQueue->patient->patlast }},
                                    {{ $nextChargingQueue->patient->patfirst }}
                                </div>
                            @endif
                        </div>

                        <div class="text-xs text-center opacity-70 py-2 bg-base-200 rounded">
                            Waiting for payment confirmation from cashier
                        </div>

                        <button wire:click="nextQueue" class="btn btn-primary btn-block btn-sm touch-target">
                            <x-mary-icon name="o-arrow-right" class="w-5 h-5" />
                            Call Next Queue
                        </button>
                    </div>
                </x-mary-card>
            @endif

            {{-- Other Charging Queues Table --}}
            @if (count($otherChargingQueues) > 0)
                <x-mary-card title="Other Patients at Cashier" class="border border-warning/30">
                    <div class="overflow-x-auto max-h-64 overflow-y-auto">
                        <table class="table table-xs">
                            <thead class="sticky top-0 bg-base-100">
                                <tr>
                                    <th>Queue #</th>
                                    <th>Patient</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($otherChargingQueues as $queue)
                                    <tr class="hover">
                                        <td class="font-mono font-bold">{{ $queue->queue_number }}</td>
                                        <td class="text-xs">
                                            @if ($queue->patient)
                                                {{ $queue->patient->patlast }}, {{ $queue->patient->patfirst }}
                                            @endif
                                        </td>
                                        <td class="text-xs opacity-70">
                                            {{ \Carbon\Carbon::parse($queue->charging_at)->format('h:i A') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-mary-card>
            @endif

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
                    <div class="flex items-center gap-4">
                        <x-mary-input type="date" wire:model.live="dateFilter" class="input-sm" />
                        <x-mary-select wire:model.live="selectedWindow" class="select-sm">
                            @for ($i = 1; $i <= $maxWindows; $i++)
                                <option value="{{ $i }}">Window {{ $i }}</option>
                            @endfor
                        </x-mary-select>
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
                                                <button wire:click="selectQueue({{ $queue->id }})"
                                                    class="btn btn-xs btn-primary"
                                                    wire:confirm="Assign {{ $queue->queue_number }} to Window {{ $selectedWindow }}?">
                                                    Select
                                                </button>
                                            @elseif ($queue->isPreparing() && $queue->assigned_window != $selectedWindow)
                                                <button wire:click="selectQueue({{ $queue->id }})"
                                                    class="btn btn-xs btn-accent"
                                                    wire:confirm="Move {{ $queue->queue_number }} from Window {{ $queue->assigned_window }} to Window {{ $selectedWindow }}?">
                                                    Move Here
                                                </button>
                                            @elseif ($queue->isPreparing() && $queue->assigned_window == $selectedWindow)
                                                <span class="badge badge-xs badge-primary">Current</span>
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

    {{-- Print Prescription Modal --}}
    <x-mary-modal wire:model="showPrintModal" title="Print Prescription" box-class="w-11/12 max-w-4xl">
        @if ($printQueue)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 p-4 bg-base-200 rounded">
                    <div>
                        <div class="text-sm opacity-70">Queue Number</div>
                        <div class="text-xl font-bold">{{ $printQueue->queue_number }}</div>
                    </div>
                    @if ($printQueue->patient)
                        <div>
                            <div class="text-sm opacity-70">Patient</div>
                            <div class="font-semibold">
                                {{ $printQueue->patient->patlast }}, {{ $printQueue->patient->patfirst }}
                            </div>
                        </div>
                    @endif
                </div>

                @if (count($printItems) > 0)
                    <div>
                        <div class="flex justify-between items-center mb-3">
                            <div class="font-semibold">Select Items to Print</div>
                            <div class="flex gap-2">
                                <button wire:click="selectAllItems" class="btn btn-xs btn-primary">
                                    Select All
                                </button>
                                <button wire:click="deselectAllItems" class="btn btn-xs btn-ghost">
                                    Deselect All
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto max-h-96 overflow-y-auto border rounded">
                            <table class="table table-sm">
                                <thead class="sticky top-0 bg-base-100">
                                    <tr>
                                        <th>
                                            <input type="checkbox" class="checkbox checkbox-sm"
                                                @if (count($selectedItems) === count($printItems)) checked @endif
                                                wire:click="selectAllItems">
                                        </th>
                                        <th>Drug</th>
                                        <th>Qty</th>
                                        <th>Frequency</th>
                                        <th>Duration</th>
                                        <th>Type</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($printItems as $item)
                                        <tr class="hover">
                                            <td>
                                                <input type="checkbox" class="checkbox checkbox-sm checkbox-primary"
                                                    @if (in_array($item['id'], $selectedItems)) checked @endif
                                                    wire:click="toggleItemSelection({{ $item['id'] }})">
                                            </td>
                                            <td>
                                                <div class="text-sm font-medium">{{ $item['drug_concat'] }}</div>
                                            </td>
                                            <td>{{ $item['qty'] }}</td>
                                            <td>{{ $item['frequency'] }}</td>
                                            <td>{{ $item['duration'] }}</td>
                                            <td>
                                                <div class="badge badge-xs">{{ $item['order_type'] }}</div>
                                            </td>
                                            <td class="text-xs">
                                                @if ($item['remark'])
                                                    <div>{{ $item['remark'] }}</div>
                                                @endif
                                                @if ($item['addtl_remarks'])
                                                    <div class="text-warning">{{ $item['addtl_remarks'] }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 text-sm opacity-70">
                            Selected: <span class="font-semibold">{{ count($selectedItems) }}</span> of
                            <span class="font-semibold">{{ count($printItems) }}</span> items
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <button wire:click="$set('showPrintModal', false)" class="btn">Cancel</button>
            <button wire:click="printPrescription" class="btn btn-primary">
                <x-mary-icon name="o-printer" class="w-4 h-4" />
                Print Selected Items
            </button>
        </x-slot:actions>
    </x-mary-modal>

    {{-- Print Window Script --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            @this.on('open-print-window', (event) => {
                window.open(event.url, '_blank', 'width=800,height=600');
            });
        });
    </script>
</div>
