<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Deliveries" separator progress-indicator>
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
            <x-mary-button label="Add Delivery" icon="o-plus"
                class="btn-sm btn-primary shadow-md hover:shadow-lg transition-shadow" wire:click="openAddModal" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Filters --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <x-mary-input icon="o-magnifying-glass" wire:model.live.debounce.300ms="search"
            placeholder="Search by PO# or SI#..." size="sm" clearable />
        <x-mary-select wire:model.live="supplier_id" :options="$suppliers->map(fn($s) => ['id' => $s->suppcode, 'name' => $s->suppname])" option-value="id" option-label="name"
            placeholder="-- Filter Supplier --" size="sm" clearable />
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">#</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Date</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">PO #</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">SI #</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Supplier</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">Total
                            Items</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">Total
                            Amount</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Source of Fund</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Delivery Type</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr class="hover:bg-blue-50/50 transition-colors cursor-pointer"
                            wire:click="$navigate('{{ route('purchases.delivery-view', $delivery->id) }}')">
                            <td class="py-3 px-4 opacity-50 text-xs">{{ $delivery->id }}</td>
                            <td class="py-3 px-4 whitespace-nowrap text-xs">{{ $delivery->delivery_date }}</td>
                            <td class="py-3 px-4 font-medium text-xs">{{ $delivery->po_no }}</td>
                            <td class="py-3 px-4 font-medium text-xs">{{ $delivery->si_no }}</td>
                            <td class="py-3 px-4 text-xs">{{ $delivery->supplier ? $delivery->supplier->suppname : '' }}
                            </td>
                            <td class="py-3 px-4 text-right text-xs font-bold">{{ $delivery->items->sum('qty') }}</td>
                            <td class="py-3 px-4 text-right text-xs font-bold text-green-700">
                                {{ number_format($delivery->items->sum('total_amount'), 2) }}</td>
                            <td class="py-3 px-4 text-xs">{{ $delivery->charge->chrgdesc ?? '-' }}</td>
                            <td class="py-3 px-4 text-xs capitalize">{{ $delivery->delivery_type }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="flex flex-col items-center justify-center py-12 opacity-40">
                                    <x-mary-icon name="o-truck" class="w-12 h-12 mb-2" />
                                    <p class="text-sm">No deliveries found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $deliveries->links() }}
        </div>
    </div>

    {{-- Add Delivery Modal --}}
    <x-mary-modal wire:model="addModal" title="Add Delivery" class="backdrop-blur" box-class="max-w-xl">
        <x-mary-form wire:submit="add_delivery">
            <x-mary-input label="Delivery Date" wire:model="delivery_date" type="date" icon="o-calendar" required />
            <x-mary-input label="Purchase Order No" wire:model="po_no" icon="o-document-text" />
            <x-mary-input label="Sales Invoice No" wire:model="si_no" icon="o-document-text" />
            <x-mary-select label="Supplier" wire:model="suppcode" :options="$suppliers->map(fn($s) => ['id' => $s->suppcode, 'name' => $s->suppname])" option-value="id"
                option-label="name" placeholder="Choose supplier" icon="o-building-storefront" required />
            <x-mary-select label="Source of Fund" wire:model="charge_code" :options="$charges" option-value="chrgcode"
                option-label="chrgdesc" icon="o-banknotes" required />
            <x-mary-select label="Type of Delivery" wire:model="delivery_type" :options="[['id' => 'procured', 'name' => 'Procured'], ['id' => 'received', 'name' => 'Received']]" option-value="id"
                option-label="name" icon="o-truck" />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('addModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="add_delivery" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
