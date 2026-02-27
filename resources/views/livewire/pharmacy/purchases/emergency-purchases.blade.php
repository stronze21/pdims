<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Emergency Purchases" separator progress-indicator>
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
            <x-mary-button label="Add EP" icon="o-plus"
                class="btn-sm btn-primary shadow-md hover:shadow-lg transition-shadow" wire:click="openAddModal" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Search --}}
    <div class="mb-4">
        <x-mary-input icon="o-magnifying-glass" wire:model.live.debounce.300ms="search"
            placeholder="Search by drug name..." size="sm" clearable />
    </div>

    {{-- Loading --}}
    <div wire:loading.flex wire:target="new_ep, push, cancel_purchase"
        class="fixed inset-0 z-50 items-center justify-center bg-base-100/50">
        <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">#</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Date</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">OR #</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Bought From</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Description</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">Unit Price
                        </th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">QTY</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">Total
                            Amount</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Source of Fund</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Encoded by</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-center">Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchases as $purchase)
                        <tr class="hover:bg-blue-50/50 transition-colors">
                            <td class="py-3 px-4 opacity-50 text-xs">{{ $purchase->id }}</td>
                            <td class="py-3 px-4 whitespace-nowrap text-xs">
                                {{ \Carbon\Carbon::parse($purchase->purchase_date)->format('M d, Y') }}
                            </td>
                            <td class="py-3 px-4 font-medium text-xs">{{ $purchase->or_no }}</td>
                            <td class="py-3 px-4 text-xs">{{ $purchase->pharmacy_name }}</td>
                            <td class="py-3 px-4 text-xs max-w-xs">
                                <div class="font-semibold">
                                    {{ $purchase->drug ? str_replace('_', ' ', $purchase->drug->drug_concat) : 'N/A' }}
                                </div>
                                <div class="text-xs opacity-60">
                                    Exp:
                                    {{ $purchase->expiry_date ? \Carbon\Carbon::parse($purchase->expiry_date)->format('M d, Y') : '-' }}
                                    @if ($purchase->lot_no)
                                        | Lot: {{ $purchase->lot_no }}
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right text-xs">
                                <span class="font-mono">{{ number_format($purchase->unit_price, 2) }}</span>
                            </td>
                            <td class="py-3 px-4 text-right text-xs">
                                <span class="font-bold">{{ number_format($purchase->qty) }}</span>
                            </td>
                            <td class="py-3 px-4 text-right text-xs">
                                <span
                                    class="font-bold text-green-700">{{ number_format($purchase->total_amount, 2) }}</span>
                            </td>
                            <td class="py-3 px-4 text-xs">
                                {{ $purchase->charge->chrgdesc ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-xs">
                                {{ $purchase->user->name ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($purchase->status === 'pending')
                                    <button wire:click="openPushModal({{ $purchase->id }})"
                                        class="btn btn-xs btn-warning shadow-sm hover:shadow-md transition-shadow">
                                        <x-mary-icon name="o-arrow-up-tray" class="w-3 h-3" />
                                        Pending
                                    </button>
                                @elseif ($purchase->status === 'pushed')
                                    <span class="badge badge-sm badge-success shadow-sm">
                                        <x-mary-icon name="o-check" class="w-3 h-3 mr-1" />
                                        Pushed
                                    </span>
                                @else
                                    <span class="badge badge-sm badge-error shadow-sm">
                                        <x-mary-icon name="o-x-mark" class="w-3 h-3 mr-1" />
                                        Cancelled
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="flex flex-col items-center justify-center py-12 opacity-40">
                                    <x-mary-icon name="o-bolt" class="w-12 h-12 mb-2" />
                                    <p class="text-sm">No emergency purchases found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $purchases->links() }}
        </div>
    </div>

    {{-- Add Emergency Purchase Modal --}}
    <x-mary-modal wire:model="addModal" title="Add Emergency Purchase" class="backdrop-blur" box-class="max-w-2xl">
        <x-mary-form wire:submit="new_ep">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-mary-input label="Purchase Date" wire:model="purchase_date" type="date" icon="o-calendar"
                    required />
                <x-mary-input label="OR No" wire:model="or_no" icon="o-document-text" required />
            </div>

            <x-mary-input label="Bought From (Pharmacy Name)" wire:model="pharmacy_name" icon="o-building-storefront"
                required />

            <x-mary-select label="Source of Fund" wire:model="charge_code" :options="$charges" option-value="chrgcode"
                option-label="chrgdesc" icon="o-banknotes" required />

            <x-mary-choices-offline label="Drug/Medicine" wire:model="dmdcomb" placeholder="Select drug/medicine"
                placeholder-value="0" :options="$drugs->map(
                    fn($d) => ['id' => $d->dmdcomb . ',' . $d->dmdctr, 'name' => str_replace('_', ' ', $d->drug_name)],
                )" option-value="id" option-label="name" icon="o-beaker" single
                clearable searchable required />

            <x-mary-input label="Expiry Date" wire:model="expiry_date" type="date" icon="o-calendar" required />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-mary-input label="QTY" wire:model="qty" type="number" step="1" min="1"
                    icon="o-calculator" required />
                <x-mary-input label="Unit Cost" wire:model="unit_price" type="number" step="0.01"
                    min="1" icon="o-currency-dollar" required />
            </div>

            <x-mary-input label="Lot No" wire:model="lot_no" icon="o-hashtag" />

            <x-mary-textarea label="Remarks" wire:model="remarks" rows="2" />

            <x-mary-checkbox label="Highly Specialised Drugs" wire:model.live="has_compounding" />

            @if ($has_compounding)
                <x-mary-input label="Compounding Fee" wire:model="compounding_fee" type="number" step="0.01"
                    icon="o-currency-dollar" required />
            @endif

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('addModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="new_ep" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Push/Cancel Confirmation Modal --}}
    <x-mary-modal wire:model="pushModal" title="Confirm Action" class="backdrop-blur">
        <p class="text-sm text-gray-600 mb-4">
            Push emergency purchased item to stocks? This action cannot be undone.
        </p>
        <div class="flex justify-end gap-2">
            <x-mary-button label="Close" wire:click="$set('pushModal', false)" />
            <x-mary-button label="Cancel Purchase" wire:click="cancel_purchase" class="btn-error"
                spinner="cancel_purchase" />
            <x-mary-button label="Push to Stocks" wire:click="push" class="btn-success" spinner="push" />
        </div>
    </x-mary-modal>
</div>
