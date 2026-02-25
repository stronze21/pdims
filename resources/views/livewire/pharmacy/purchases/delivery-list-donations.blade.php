<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Donations" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center space-x-2 px-3 py-1 bg-white rounded-lg shadow-sm border border-gray-200">
                <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                <span class="text-sm font-semibold text-gray-700">{{ auth()->user()->location->description }}</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="Add Donation" icon="o-plus"
                class="btn-sm btn-primary shadow-md hover:shadow-lg transition-shadow" wire:click="openAddModal" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Search --}}
    <div class="mb-4">
        <x-mary-input icon="o-magnifying-glass" wire:model.live.debounce.300ms="search"
            placeholder="Search..." size="sm" clearable />
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">#</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Date</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Supplier</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">Total Items</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-right">Total Amount</th>
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
                            <td class="py-3 px-4 text-xs">{{ $delivery->supplier ? $delivery->supplier->suppname : '' }}</td>
                            <td class="py-3 px-4 text-right text-xs font-bold">{{ $delivery->items->sum('qty') }}</td>
                            <td class="py-3 px-4 text-right text-xs font-bold text-green-700">{{ number_format($delivery->items->sum('total_amount'), 2) }}</td>
                            <td class="py-3 px-4 text-xs">{{ $delivery->charge->chrgdesc ?? '-' }}</td>
                            <td class="py-3 px-4 text-xs capitalize">{{ $delivery->delivery_type }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="flex flex-col items-center justify-center py-12 opacity-40">
                                    <x-mary-icon name="o-gift" class="w-12 h-12 mb-2" />
                                    <p class="text-sm">No donations found</p>
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

    {{-- Add Donation Modal --}}
    <x-mary-modal wire:model="addModal" title="Add Donation" class="backdrop-blur" box-class="max-w-xl">
        <x-mary-form wire:submit="add_delivery">
            <x-mary-input label="Delivery Date" wire:model="delivery_date" type="date" icon="o-calendar" required />
            <x-mary-input label="Reference No" wire:model="po_no" icon="o-document-text" />

            <div class="p-3 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600">
                    <strong>Supplier:</strong> {{ $suppliers->first()?->suppname ?? 'N/A' }}
                </p>
                <p class="text-sm text-gray-600">
                    <strong>Type:</strong> Donation
                </p>
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('addModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="add_delivery" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
