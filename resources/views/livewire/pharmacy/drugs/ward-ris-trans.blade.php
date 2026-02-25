<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Ward RIS" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center space-x-2 px-3 py-1 bg-white rounded-lg shadow-sm border border-gray-200">
                <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                <span class="text-sm font-semibold text-gray-700">{{ auth()->user()->location->description }}</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <div class="flex items-center space-x-2">
                <x-mary-button label="Issue RIS" icon="o-plus" class="btn-sm btn-primary"
                    wire:click="$set('issueModal', true)" />
                <x-mary-button label="Append Last RIS" icon="o-arrow-path" class="btn-sm btn-warning"
                    wire:click="append" spinner="append" />
            </div>
        </x-slot:actions>
    </x-mary-header>

    <div class="flex items-end space-x-3 mb-4">
        <div class="form-control">
            <label class="label"><span class="label-text text-xs">Search</span></label>
            <input type="text" placeholder="Search drug name..." class="input input-bordered input-sm"
                wire:model.live.debounce.300ms="search" />
        </div>
    </div>

    @if ($errors->first())
        <div class="alert alert-error shadow-lg mb-3 max-w-fit">
            <x-mary-icon name="o-exclamation-triangle" class="w-5 h-5" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto max-h-[calc(100vh-350px)]">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Reference</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Date Issued</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">From</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">To Ward</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Item</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Issued QTY</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Fund Source</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trans as $tran)
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100"
                            wire:key="ris-{{ $tran->id }}">
                            <td class="py-3 px-4 text-xs font-mono font-bold text-blue-600 cursor-pointer hover:underline">
                                <a href="{{ route('inventory.ward-ris.view-ref', ['reference_no' => $tran->trans_no]) }}" wire:navigate>{{ $tran->trans_no }}</a>
                            </td>
                            <td class="py-3 px-4 text-xs text-blue-600 cursor-pointer hover:underline">
                                <a href="{{ route('inventory.ward-ris.view-date', ['date' => date('Y-m-d', strtotime($tran->created_at))]) }}" wire:navigate>{{ $tran->created_at() }}</a>
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600">{{ $tran->location ? $tran->location->description : '' }}</td>
                            <td class="py-3 px-4 text-xs text-gray-600">{{ $tran->ward ? $tran->ward->ward_name : '' }}</td>
                            <td class="py-3 px-4 text-xs font-bold text-gray-900">
                                {{ $tran->drug ? $tran->drug->drug_concat() : '' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($tran->return_qty > 0)
                                    <span class="badge badge-sm badge-error">{{ number_format($tran->return_qty) }} (returned)</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 bg-blue-500 text-white rounded-lg text-xs font-bold">
                                        {{ number_format($tran->issued_qty < 1 ? 0 : $tran->issued_qty) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600">
                                {{ $tran->charge ? $tran->charge->chrgdesc : '' }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($tran->issued_qty > 0 && $tran->return_qty == 0)
                                    <x-mary-button icon="o-x-mark" class="btn-xs btn-error" tooltip="Cancel/Return"
                                        wire:click="cancelIssue({{ $tran->id }})"
                                        wire:confirm="Are you sure you want to cancel this issuance? Items will be returned to stock." spinner />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400 font-semibold">No record found!</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $trans->links() }}
        </div>
    </div>

    {{-- Issue RIS Modal --}}
    <x-mary-modal wire:model="issueModal" title="Issue RIS to Ward" class="backdrop-blur">
        <div class="space-y-4">
            <div class="form-control w-full">
                <label class="label"><span class="label-text">RIS To Ward</span></label>
                <select class="select select-bordered" wire:model="ward_id">
                    <option value="">Select ward...</option>
                    @foreach ($wards as $ward)
                        <option value="{{ $ward->id }}">{{ $ward->ward_name }}</option>
                    @endforeach
                </select>
                @error('ward_id')
                    <span class="text-sm text-red-600">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Fund Source</span></label>
                <select class="select select-bordered" wire:model.live="chrgcode">
                    <option value="">Select fund source...</option>
                    @foreach ($charge_codes as $charge)
                        <option value="{{ $charge->chrgcode }}">{{ $charge->chrgdesc }}</option>
                    @endforeach
                </select>
                @error('chrgcode')
                    <span class="text-sm text-red-600">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Drug/Medicine</span></label>
                <input type="text" placeholder="Type here to search" class="input input-sm input-bordered mb-2"
                    wire:model.live.debounce.300ms="search_drug">
                <select class="select select-bordered h-32" wire:model="stock_id" size="5">
                    @foreach ($drugs as $drug)
                        <option value="{{ $drug->dmdcomb }},{{ $drug->dmdctr }}">
                            {{ $drug->drug_concat }} (Bal: {{ number_format($drug->stock_bal) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">QTY to issue</span></label>
                <input type="number" class="input input-bordered" wire:model="issue_qty" min="1" />
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('issueModal', false)" />
            <x-mary-button label="Issue" class="btn-primary" wire:click="issueRis" spinner="issueRis" />
            <x-mary-button label="Issue & Append" class="btn-warning" wire:click="issueRis(true)" spinner="issueRis" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Issue More (Append) Modal --}}
    <x-mary-modal wire:model="issueMoreModal" title="Append to Reference #: {{ $reference_no }}" class="backdrop-blur">
        <div class="space-y-4">
            <div class="form-control w-full">
                <label class="label"><span class="label-text">RIS To Ward</span></label>
                <select class="select select-bordered" wire:model="ward_id" disabled>
                    <option value="">Select ward...</option>
                    @foreach ($wards as $ward)
                        <option value="{{ $ward->id }}">{{ $ward->ward_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Fund Source</span></label>
                <select class="select select-bordered" wire:model.live="chrgcode">
                    <option value="">Select fund source...</option>
                    @foreach ($charge_codes as $charge)
                        <option value="{{ $charge->chrgcode }}">{{ $charge->chrgdesc }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Drug/Medicine</span></label>
                <input type="text" placeholder="Type here to search" class="input input-sm input-bordered mb-2"
                    wire:model.live.debounce.300ms="search_drug">
                <select class="select select-bordered h-32" wire:model="stock_id" size="5">
                    @foreach ($drugs as $drug)
                        <option value="{{ $drug->dmdcomb }},{{ $drug->dmdctr }}">
                            {{ $drug->drug_concat }} (Bal: {{ number_format($drug->stock_bal) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">QTY to issue</span></label>
                <input type="number" class="input input-bordered" wire:model="issue_qty" min="1" />
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('issueMoreModal', false)" />
            <x-mary-button label="Issue" class="btn-primary" wire:click="issueRis" spinner="issueRis" />
            <x-mary-button label="Issue & Append" class="btn-warning" wire:click="issueRis(true)" spinner="issueRis" />
        </x-slot:actions>
    </x-mary-modal>
</div>
