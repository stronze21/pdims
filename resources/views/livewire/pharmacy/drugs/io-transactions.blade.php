<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="IO Transactions" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center space-x-2 px-3 py-1 bg-white rounded-lg shadow-sm border border-gray-200">
                <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                <span class="text-sm font-semibold text-gray-700">{{ auth()->user()->location->description }}</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <div class="flex items-center space-x-2">
                <x-mary-button label="New Request" icon="o-plus" class="btn-sm btn-primary"
                    wire:click="$set('requestModal', true)" />
                <x-mary-button label="Append Last" icon="o-arrow-path" class="btn-sm btn-warning"
                    wire:click="addMoreRequest" />
            </div>
        </x-slot:actions>
    </x-mary-header>

    <div class="flex items-center space-x-3 mb-4">
        <div class="form-control">
            <label class="label"><span class="label-text text-xs">Issuing Location</span></label>
            <select class="select select-bordered select-sm" wire:model.live="issuing_location_id">
                <option value="">All</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-control">
            <label class="label"><span class="label-text text-xs">Requesting Location</span></label>
            <select class="select select-bordered select-sm" wire:model.live="requesting_location_id">
                <option value="">All</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-control">
            <label class="label"><span class="label-text text-xs">Search</span></label>
            <input type="text" placeholder="Search by ref# or drug name..." class="input input-bordered input-sm"
                wire:model.live.debounce.300ms="search" />
        </div>
    </div>

    {{-- Legend --}}
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

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto max-h-[calc(100vh-400px)]">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Ref #</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Drug Name</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Requested
                        </th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Issued
                        </th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Received
                        </th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">From</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">To</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Status</th>
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
                            wire:key="io-trans-{{ $tran->id }}">
                            <td class="py-3 px-4 text-xs font-mono font-bold text-blue-600">{{ $tran->trans_no }}</td>
                            <td class="py-3 px-4 text-xs font-bold text-gray-900">
                                {{ $tran->drug ? $tran->drug->drug_concat : '' }}</td>
                            <td class="py-3 px-4 text-xs text-center">{{ number_format($tran->requested_qty ?? 0) }}
                            </td>
                            <td class="py-3 px-4 text-xs text-center">{{ number_format($tran->issued_qty ?? 0) }}</td>
                            <td class="py-3 px-4 text-xs text-center">{{ number_format($tran->received_qty ?? 0) }}
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600">
                                {{ $tran->from_location ? $tran->from_location->description : '' }}</td>
                            <td class="py-3 px-4 text-xs text-gray-600">
                                {{ $tran->location ? $tran->location->description : '' }}</td>
                            <td class="py-3 px-4">
                                <span
                                    class="badge badge-sm {{ $status_class }}">{{ $tran->trans_stat == 'Denied' ? 'Declined' : $tran->trans_stat }}</span>
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-500 max-w-[150px] truncate"
                                title="{{ $tran->remarks_request }}">
                                {{ $tran->remarks_request }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex space-x-1">
                                    @if ($tran->trans_stat == 'Requested')
                                        {{-- Warehouse can issue --}}
                                        @if ($tran->request_from == auth()->user()->pharm_location_id)
                                            <x-mary-button icon="o-check" class="btn-xs btn-success"
                                                tooltip-left="Issue" wire:click="selectRequest({{ $tran->id }})"
                                                spinner />
                                            <x-mary-button icon="o-x-mark" class="btn-xs btn-error"
                                                tooltip-left="Decline" wire:click="denyRequest('Declined by admin')"
                                                wire:confirm="Are you sure you want to decline this request?" spinner />
                                        @endif
                                    @elseif ($tran->trans_stat == 'Issued')
                                        {{-- Requestor can receive --}}
                                        @if ($tran->loc_code == auth()->user()->pharm_location_id)
                                            <x-mary-button icon="o-inbox-arrow-down" class="btn-xs btn-success"
                                                tooltip-left="Receive" wire:click="receiveIssued({{ $tran->id }})"
                                                wire:confirm="Confirm receipt of all items?" spinner />
                                        @endif
                                        {{-- Issuer can cancel --}}
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
                                found!</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $trans->links() }}
        </div>
    </div>

    {{-- New Request Modal --}}
    <x-mary-modal wire:model="requestModal" title="New IO Trans Request" class="backdrop-blur">
        <div class="space-y-4">
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Request From Location</span></label>
                <select class="select select-bordered" wire:model="location_id">
                    <option value="">Select location...</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->description }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Drug/Medicine</span></label>
                <select class="select select-bordered" wire:model="stock_id">
                    <option value="">Select drug...</option>
                    @foreach ($drugs as $drug)
                        <option value="{{ $drug->dmdcomb }},{{ $drug->dmdctr }}">{{ $drug->drug_concat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Quantity</span></label>
                <input type="number" class="input input-bordered" wire:model="requested_qty" min="1" />
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Remarks</span></label>
                <input type="text" class="input input-bordered" wire:model="remarks" />
            </div>
        </div>
        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('requestModal', false)" />
            <x-mary-button label="Submit" class="btn-primary" wire:click="addRequest" spinner="addRequest" />
        </x-slot:actions>
    </x-mary-modal>

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
                                ({{ number_format($avail->avail) }} available)</option>
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
