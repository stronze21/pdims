<div>
    <x-mary-header title="Missing Drug Prices"
        subtitle="Stock records with no valid matching entry in the drug price table" separator progress-indicator>
        <x-slot:actions>
            <x-mary-badge value="Super Admin only" class="badge-warning" />
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 gap-4 mb-5 md:grid-cols-3">
        <x-mary-input label="Search drugs, lots, or fund codes" wire:model.live.debounce.400ms="search"
            icon="o-magnifying-glass" placeholder="Example: dapagliflozin or 60052367" class="md:col-span-2" />
        <x-mary-select label="Location" wire:model.live="location_id" :options="$locations"
            option-value="id" option-label="description" placeholder="All locations" />
    </div>

    <x-mary-alert title="Safe repair matching" icon="o-information-circle" class="mb-5 alert-info">
        “Use matching price” is offered only when drug, fund source, and expiry all match. Otherwise, create a verified price manually.
    </x-mary-alert>

    <x-mary-card shadow separator>
        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>Drug</th>
                        <th>Location / Fund</th>
                        <th>Lot / Expiry</th>
                        <th class="text-right">Balance</th>
                        <th>Broken reference</th>
                        <th class="text-right">Repair</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stock)
                        <tr wire:key="missing-price-{{ $stock->id }}">
                            <td>
                                <div class="font-semibold">{{ $stock->drug_concat }}</div>
                                <div class="mt-1 font-mono text-xs text-base-content/55">Stock #{{ $stock->id }}</div>
                            </td>
                            <td>
                                <div>{{ $stock->location_name ?: 'Location #' . $stock->loc_code }}</div>
                                <div class="mt-1 text-xs text-base-content/60">{{ $stock->chrgdesc ?: $stock->chrgcode }}</div>
                            </td>
                            <td>
                                <div class="font-mono text-sm">{{ $stock->lot_no ?: 'No lot number' }}</div>
                                <div class="mt-1 text-xs text-base-content/60">{{ $stock->exp_date ?: 'No expiry' }}</div>
                            </td>
                            <td class="font-semibold text-right tabular-nums">{{ number_format($stock->stock_bal) }}</td>
                            <td><x-mary-badge :value="$stock->dmdprdte ?: 'NULL'" class="badge-error badge-outline" /></td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    @if ($matchingPriceIds[$stock->id])
                                        <x-mary-button label="Use matching price" icon="o-link" class="btn-sm btn-success"
                                            wire:click="attachLatestMatchingPrice({{ $stock->id }})" spinner />
                                    @endif
                                    <x-mary-button label="Create price" icon="o-plus-circle" class="btn-sm btn-primary"
                                        wire:click="selectForManualRepair({{ $stock->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-14 text-center">
                                <x-mary-icon name="o-check-circle" class="w-12 h-12 mx-auto text-success" />
                                <p class="mt-3 text-lg font-bold">No missing price links found</p>
                                <p class="mt-1 text-sm text-base-content/60">Every stock record in this view has a valid price link.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stocks->hasPages())
            <div class="mt-4">{{ $stocks->links() }}</div>
        @endif
    </x-mary-card>

    <x-mary-modal wire:model="repairModal" title="Create and attach a price" subtitle="{{ $selected_drug }}" separator>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-mary-input label="Acquisition cost" wire:model="acquisition_cost" type="number" min="0" step="0.01"
                prefix="₱" />
            <x-mary-input label="Selling price" wire:model="selling_price" type="number" min="0" step="0.01"
                prefix="₱" />
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="cancelManualRepair" />
            <x-mary-button label="Create and attach price" icon="o-check" class="btn-primary"
                wire:click="createPriceAndRepair" spinner="createPriceAndRepair" />
        </x-slot:actions>
    </x-mary-modal>
</div>
