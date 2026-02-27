<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="IO Transaction - Reference" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-end h-full">
                <div class="flex items-center gap-2 px-3 py-1
                    bg-white rounded-lg shadow-sm border">
                    <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                    <span class="text-sm font-semibold">
                        {{ auth()->user()->location->description }}
                    </span>
                </div>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="Back" icon="o-arrow-left" class="btn-sm btn-ghost"
                link="{{ route('inventory.io-trans') }}" />
            <x-mary-button label="Print" icon="o-printer" class="btn-sm" onclick="printMe()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Reference Info Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <span class="text-xs text-gray-500 uppercase tracking-wide">Reference No</span>
                <p class="text-lg font-bold font-mono text-blue-600">{{ $reference_no }}</p>
            </div>
            @if ($trans->count())
                <div>
                    <span class="text-xs text-gray-500 uppercase tracking-wide">Request From</span>
                    <p class="text-sm font-semibold text-gray-800">
                        {{ $trans->first()->location ? $trans->first()->location->description : '' }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 uppercase tracking-wide">Request To</span>
                    <p class="text-sm font-semibold text-gray-800">
                        {{ $trans->first()->from_location ? $trans->first()->from_location->description : '' }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Status Legend --}}
    <div class="flex items-center space-x-4 mb-3 px-4 py-2 bg-white rounded-lg shadow-sm border border-gray-100">
        <span class="text-xs font-semibold text-gray-600">Status:</span>
        <div class="flex items-center space-x-1">
            <span class="badge badge-xs bg-slate-400 border-0"></span>
            <span class="text-xs text-gray-600">Requested</span>
        </div>
        <div class="flex items-center space-x-1">
            <span class="badge badge-xs bg-blue-500 border-0"></span>
            <span class="text-xs text-gray-600">Issued</span>
        </div>
        <div class="flex items-center space-x-1">
            <span class="badge badge-xs bg-green-500 border-0"></span>
            <span class="text-xs text-gray-600">Received</span>
        </div>
        <div class="flex items-center space-x-1">
            <span class="badge badge-xs bg-red-500 border-0"></span>
            <span class="text-xs text-gray-600">Cancelled/Declined</span>
        </div>
    </div>

    {{-- Transaction Table --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden" id="print">
        <div class="overflow-x-auto max-h-[calc(100vh-450px)]">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">#</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Date Requested</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Item Requested</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Requested
                            QTY</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Issued
                            QTY</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Fund Source</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Status</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Date Updated</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Remarks</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trans as $tran)
                        @php
                            $status_class = match ($tran->trans_stat) {
                                'Requested' => 'badge-neutral',
                                'Issued' => 'badge-info',
                                'Received' => 'badge-success',
                                'Cancelled', 'Declined', 'Denied' => 'badge-error',
                                default => 'badge-ghost',
                            };
                        @endphp
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100"
                            wire:key="io-ref-{{ $tran->id }}">
                            <td class="py-3 px-4 text-xs text-gray-500">{{ $loop->iteration }}</td>
                            <td class="py-3 px-4 text-xs text-gray-600 cursor-pointer hover:text-blue-600"
                                wire:click="viewByDate('{{ date('Y-m-d', strtotime($tran->created_at)) }}')">
                                {{ $tran->created_at() }}
                            </td>
                            <td class="py-3 px-4 text-xs font-bold text-gray-900">
                                {{ $tran->drug ? $tran->drug->drug_concat : '' }}
                            </td>
                            <td class="py-3 px-4 text-xs text-center">{{ number_format($tran->requested_qty ?? 0) }}
                            </td>
                            <td class="py-3 px-4 text-xs text-center">
                                {{ number_format($tran->issued_qty < 1 ? 0 : $tran->issued_qty) }}</td>
                            <td class="py-3 px-4 text-xs text-gray-600">
                                @if (($tran->trans_stat == 'Issued' || $tran->trans_stat == 'Received') && $tran->items->first())
                                    {{ $tran->items->first()->charge ? $tran->items->first()->charge->chrgdesc : '' }}
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    class="badge badge-sm {{ $status_class }}">{{ $tran->trans_stat == 'Denied' ? 'Declined' : $tran->trans_stat }}</span>
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-500">{{ $tran->updated_at2() }}</td>
                            <td class="py-3 px-4 text-xs text-gray-500 max-w-[150px] truncate"
                                title="{{ $tran->remarks_cancel ?: $tran->remarks_request . ' ' . $tran->remarks_issue }}">
                                @if ($tran->remarks_cancel)
                                    {{ $tran->remarks_cancel }}
                                @else
                                    {{ $tran->remarks_request }} {{ $tran->remarks_issue }}
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex space-x-1">
                                    @if ($tran->trans_stat == 'Requested')
                                        @if ($tran->request_from == auth()->user()->pharm_location_id)
                                            <x-mary-button icon="o-check" class="btn-xs btn-success"
                                                tooltip-left="Issue" wire:click="selectRequest({{ $tran->id }})"
                                                spinner />
                                            <x-mary-button icon="o-x-mark" class="btn-xs btn-error"
                                                tooltip-left="Decline" wire:click="denyRequest({{ $tran->id }})"
                                                wire:confirm="Are you sure you want to decline this request?" spinner />
                                        @endif
                                    @elseif ($tran->trans_stat == 'Issued')
                                        @if ($tran->loc_code == auth()->user()->pharm_location_id)
                                            <x-mary-button icon="o-inbox-arrow-down" class="btn-xs btn-success"
                                                tooltip-left="Receive" wire:click="receiveIssued({{ $tran->id }})"
                                                wire:confirm="Confirm receipt of all items?" spinner />
                                        @endif
                                        @if ($tran->request_from == auth()->user()->pharm_location_id)
                                            <x-mary-button icon="o-x-mark" class="btn-xs btn-error"
                                                tooltip-left="Cancel" wire:click="cancelTx({{ $tran->id }})"
                                                wire:confirm="Cancel this transaction? All issued items will be returned."
                                                spinner />
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-400 font-semibold">No transactions
                                found for this reference!</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Issue Modal --}}
    <x-mary-modal wire:model="issueModal" title="Issue Request" class="backdrop-blur">
        @if ($selected_request)
            <div class="space-y-4">
                <div class="bg-blue-50 rounded-lg p-3 text-sm">
                    <p><strong>Drug:</strong> {{ $selected_request->drug ? $selected_request->drug->drug_concat : '' }}
                    </p>
                    <p><strong>Requested QTY:</strong> {{ number_format($selected_request->requested_qty ?? 0) }}</p>
                    <p><strong>Requested by:</strong>
                        {{ $selected_request->location ? $selected_request->location->description : '' }}</p>
                </div>

                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Fund Source (Available stocks)</span></label>
                    <select class="select select-bordered" wire:model="chrgcode">
                        <option value="">Select fund source...</option>
                        @foreach ($available_drugs as $avail)
                            <option value="{{ $avail->chrgcode }}">{{ $avail->chrgdesc }}
                                ({{ number_format($avail->avail) }} available)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Quantity to Issue</span></label>
                    <input type="number" class="input input-bordered" wire:model="issue_qty" min="1" />
                </div>

                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Remarks</span></label>
                    <input type="text" class="input input-bordered" wire:model="remarks" />
                </div>
            </div>
        @endif
        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('issueModal', false)" />
            <x-mary-button label="Issue" class="btn-primary" wire:click="issueRequest" spinner="issueRequest" />
        </x-slot:actions>
    </x-mary-modal>
</div>

@script
    <script>
        window.printMe = function() {
            var printContents = document.getElementById('print').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            window.location.reload();
        }
    </script>
@endscript
