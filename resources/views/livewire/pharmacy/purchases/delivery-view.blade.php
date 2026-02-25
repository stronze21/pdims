<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Delivery View" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center space-x-2 px-3 py-1 bg-white rounded-lg shadow-sm border border-gray-200">
                <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                <span class="text-sm font-semibold text-gray-700">{{ auth()->user()->location->description }}</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            @if ($details && $details->status == 'pending')
                <x-mary-button label="Save & Lock" icon="o-lock-closed"
                    class="btn-sm btn-warning shadow-md" wire:click="$set('lockModal', true)" />
                @if ($details->delivery_type != 'RIS')
                    <x-mary-button label="Add Item" icon="o-plus"
                        class="btn-sm btn-primary shadow-md" wire:click="openAddItemModal" />
                @endif
            @endif
            <x-mary-button label="Back" icon="o-arrow-left" class="btn-sm btn-ghost"
                link="{{ route('purchases.deliveries') }}" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Loading --}}
    <div wire:loading.flex wire:target="add_item, edit_item, save_lock, delete_item"
        class="fixed inset-0 z-50 items-center justify-center bg-base-100/50">
        <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    @if ($details)
        {{-- Delivery Details Card --}}
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <div class="flex">
                        <span class="w-36 text-gray-500 text-sm">Delivery Date:</span>
                        <span class="font-bold text-sm uppercase">{{ $details->delivery_date }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-36 text-gray-500 text-sm">Supplier:</span>
                        <span class="font-bold text-sm uppercase">{{ $details->supplier ? $details->supplier->suppname : '' }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-36 text-gray-500 text-sm">Source of Fund:</span>
                        <span class="font-bold text-sm uppercase">{{ $details->charge->chrgdesc ?? '-' }}</span>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex">
                        <span class="w-36 text-gray-500 text-sm">{{ $details->charge_code == 'DRUMAK' ? 'Reference' : 'Purchase Order' }} #:</span>
                        <span class="font-bold text-sm uppercase">{{ $details->po_no }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-36 text-gray-500 text-sm">Sales Invoice #:</span>
                        <span class="font-bold text-sm uppercase">{{ $details->si_no }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-36 text-gray-500 text-sm">Status:</span>
                        <span class="badge {{ $details->status == 'pending' ? 'badge-ghost' : 'badge-success' }} badge-sm font-bold uppercase">
                            {{ $details->status }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Items Table --}}
        @php
            $total_qty = 0;
            $total_amount = 0.0;
        @endphp
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table table-sm table-zebra">
                    <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                        <tr>
                            <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Lot #</th>
                            <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Description</th>
                            <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">QTY</th>
                            <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">Unit Cost</th>
                            <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">Retail Price</th>
                            <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">Total Amount</th>
                            @if ($details->status == 'pending')
                                <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-center">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($details->items as $item)
                            @php
                                $total_qty++;
                                $total_amount += $item->total_amount;
                            @endphp
                            <tr class="hover:bg-blue-50/50 transition-colors">
                                <td class="py-3 px-4 text-xs font-mono">{{ $item->lot_no ?? '-' }}</td>
                                <td class="py-3 px-4 text-xs">
                                    <div class="font-semibold">
                                        {{ $item->drug ? str_replace('_', ' ', $item->drug->drug_concat) : 'N/A' }}
                                    </div>
                                    <div class="text-xs opacity-60">
                                        Exp: {{ $item->expiry_date }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right text-xs font-bold">{{ number_format($item->qty) }}</td>
                                <td class="py-3 px-4 text-right text-xs font-mono">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="py-3 px-4 text-right text-xs font-mono">
                                    @if ($item->current_price && $item->current_price->has_compounding)
                                        <span class="tooltip tooltip-left" data-tip="Includes {{ number_format($item->current_price->compounding_fee, 2) }} compounding fee">
                                            <x-mary-icon name="o-information-circle" class="w-3 h-3 inline text-blue-500" />
                                        </span>
                                    @endif
                                    {{ number_format($item->retail_price, 2) }}
                                </td>
                                <td class="py-3 px-4 text-right text-xs font-bold text-green-700">{{ number_format($item->total_amount, 2) }}</td>
                                @if ($details->status == 'pending')
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex justify-center gap-1">
                                            <button class="btn btn-xs btn-warning" wire:click="openEditItemModal({{ $item->id }})">
                                                <x-mary-icon name="o-pencil" class="w-3 h-3" />
                                            </button>
                                            <button class="btn btn-xs btn-error" wire:click="delete_item({{ $item->id }})"
                                                wire:confirm="Delete this item?">
                                                <x-mary-icon name="o-trash" class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $details->status == 'pending' ? 7 : 6 }}">
                                    <div class="flex flex-col items-center justify-center py-12 opacity-40">
                                        <x-mary-icon name="o-inbox" class="w-12 h-12 mb-2" />
                                        <p class="text-sm">No items yet</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($details->items->count() > 0)
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="2" class="py-3 px-4 text-right text-xs font-bold uppercase">Total</td>
                                <td class="py-3 px-4 text-right text-xs font-bold">{{ $total_qty }} Item(s)</td>
                                <td></td>
                                <td></td>
                                <td class="py-3 px-4 text-right text-xs font-bold text-green-700">{{ number_format($total_amount, 2) }}</td>
                                @if ($details->status == 'pending')
                                    <td></td>
                                @endif
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif

    {{-- Add Item Modal --}}
    <x-mary-modal wire:model="addItemModal" title="Add Item" class="backdrop-blur" box-class="max-w-2xl">
        <x-mary-form wire:submit="add_item">
            <x-mary-choices-offline label="Drug/Medicine" wire:model="dmdcomb"
                placeholder="Select drug/medicine" placeholder-value="0"
                :options="$drugs->map(fn($d) => ['id' => $d->dmdcomb . ',' . $d->dmdctr, 'name' => str_replace('_', ' ', $d->drug_name)])"
                option-value="id" option-label="name" icon="o-beaker" single clearable searchable required />

            <x-mary-input label="Expiry Date" wire:model="expiry_date" type="date" icon="o-calendar" required />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-mary-input label="QTY" wire:model="qty" type="number" step="1" min="1"
                    icon="o-calculator" required />
                <x-mary-input label="Unit Cost" wire:model="unit_price" type="number" step="0.01" min="0"
                    icon="o-currency-dollar" required />
            </div>

            <x-mary-input label="Lot No" wire:model="lot_no" icon="o-hashtag" />

            <x-mary-checkbox label="Highly Specialised Drugs" wire:model.live="has_compounding" />

            @if ($has_compounding)
                <x-mary-input label="Compounding Fee" wire:model="compounding_fee" type="number" step="0.01"
                    icon="o-currency-dollar" required />
            @endif

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('addItemModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="add_item" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Edit Item Modal --}}
    <x-mary-modal wire:model="editItemModal" title="Edit Item: {{ $editItemName }}" class="backdrop-blur" box-class="max-w-xl">
        <x-mary-form wire:submit="edit_item">
            <x-mary-input label="Lot No" wire:model="edit_lot_no" icon="o-hashtag" required />
            <x-mary-input label="Expiry Date" wire:model="edit_expiry_date" type="date" icon="o-calendar" required />

            <x-mary-checkbox label="Highly Specialised Drugs" wire:model.live="edit_has_compounding" />

            @if ($edit_has_compounding)
                <x-mary-input label="Compounding Fee" wire:model="edit_compounding_fee" type="number" step="0.01"
                    icon="o-currency-dollar" required />
            @endif

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('editItemModal', false)" />
                <x-mary-button label="Save Changes" type="submit" class="btn-primary" spinner="edit_item" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Lock Confirmation Modal --}}
    <x-mary-modal wire:model="lockModal" title="Confirm Save & Lock" class="backdrop-blur">
        <div class="flex flex-col items-center py-4">
            <x-mary-icon name="o-exclamation-triangle" class="w-16 h-16 text-warning mb-4" />
            <p class="text-sm text-gray-600 text-center">
                All items in this delivery will be added to your current stocks and no changes can be made after.
                <br><strong>This process cannot be undone. Continue?</strong>
            </p>
        </div>
        <div class="flex justify-end gap-2">
            <x-mary-button label="Cancel" wire:click="$set('lockModal', false)" />
            <x-mary-button label="Save & Lock" wire:click="save_lock" class="btn-warning" spinner="save_lock" />
        </div>
    </x-mary-modal>
</div>
