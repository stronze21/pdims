<div class="flex flex-col px-5 mx-auto max-w-screen">
    {{-- Loading --}}
    @if ($loading)
        <div class="flex items-center justify-center py-20">
            <span class="loading loading-spinner loading-lg text-primary"></span>
            <span class="ml-3 text-gray-600">Loading RIS data...</span>
        </div>
    @else
        <x-mary-header title="RIS # {{ $ris->risno ?? ($risNo ?? 'N/A') }}"
            subtitle="{{ $ris->formatted_risdate ?? ($risDate ?? 'N/A') }}" separator progress-indicator>
            <x-slot:actions>
                <x-mary-button label="Print" icon="o-printer"
                    link="{{ route('purchases.ris-print', $risId) }}" external
                    class="btn-sm btn-primary" />
                <x-mary-button label="Back to List" icon="o-arrow-left"
                    link="{{ route('purchases.ris') }}" class="btn-sm btn-ghost" />
            </x-slot:actions>
        </x-mary-header>

        {{-- Flash Messages --}}
        @if (session()->has('error'))
            <x-mary-alert title="{{ session('error') }}" icon="o-exclamation-triangle" class="mb-4 alert-error" />
        @endif
        @if (session()->has('message'))
            <x-mary-alert title="{{ session('message') }}" icon="o-check-circle" class="mb-4 alert-success" />
        @endif

        {{-- Association Status & Transfer Button --}}
        @if (isset($associationStatus))
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-4 mb-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div>
                            <span class="text-sm text-gray-500">Drug Association:</span>
                            <span class="ml-2 font-bold {{ $associationStatus['allAssociated'] ? 'text-green-600' : 'text-amber-600' }}">
                                {{ $associationStatus['associated'] }}/{{ $associationStatus['total'] }}
                                ({{ $associationStatus['percentage'] }}%)
                            </span>
                        </div>
                        <progress class="progress {{ $associationStatus['allAssociated'] ? 'progress-success' : 'progress-warning' }} w-48"
                            value="{{ $associationStatus['percentage'] }}" max="100"></progress>
                    </div>
                    <div>
                        @if ($associationStatus['allAssociated'])
                            @if (isset($ris->transferred_to_pdims) && $ris->transferred_to_pdims)
                                <span class="badge badge-success badge-lg">Transferred to Delivery</span>
                            @else
                                <x-mary-button label="Transfer to Pharmacy Delivery" icon="o-arrow-right-circle"
                                    class="btn-sm btn-success" wire:click="openTransferModal" />
                            @endif
                        @else
                            <span class="text-xs text-amber-600">Link all items to drugs before transferring</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Related Deliveries (if transferred) --}}
        @if (isset($relatedDeliveries) && count($relatedDeliveries) > 0)
            <div class="bg-white rounded-2xl shadow-lg border border-green-200 p-4 mb-4" x-data="{ showDeliveries: false }">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-truck" class="w-5 h-5 text-green-600" />
                        <span class="font-bold text-green-700">Related Deliveries</span>
                        <span class="badge badge-success badge-sm">
                            @php $totalCount = collect($relatedDeliveries)->flatten(1)->count(); @endphp
                            {{ $totalCount }} Delivery{{ $totalCount > 1 ? 's' : '' }}
                        </span>
                    </div>
                    <button @click="showDeliveries = !showDeliveries" class="btn btn-xs btn-ghost">
                        <span x-text="showDeliveries ? 'Hide' : 'Show'">Show</span>
                        <x-mary-icon name="o-chevron-down" class="w-4 h-4 transition-transform"
                            x-bind:class="showDeliveries ? 'rotate-180' : ''" />
                    </button>
                </div>
                <div x-show="showDeliveries" x-cloak x-transition class="mt-4 space-y-3">
                    @foreach ($relatedDeliveries as $invoiceGroup => $deliveries)
                        <div class="border border-gray-200 rounded-lg p-3">
                            <div class="text-sm font-semibold text-gray-700 mb-2">
                                Invoice: {{ $invoiceGroup !== 'NO_INVOICE' ? $invoiceGroup : 'No Invoice Number' }}
                            </div>
                            @foreach ($deliveries as $delivery)
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded mb-1">
                                    <div class="flex gap-4 text-xs">
                                        <span><strong>Delivery #{{ $delivery['id'] }}</strong></span>
                                        <span>{{ date('M d, Y', strtotime($delivery['delivery_date'])) }}</span>
                                        <span>{{ $delivery['supplier_name'] }}</span>
                                        <span>{{ $delivery['items_count'] ?? 0 }} Item(s)</span>
                                        <span class="font-bold text-green-700">{{ number_format($delivery['total_amount'] ?? 0, 2) }}</span>
                                    </div>
                                    <a href="{{ route('purchases.delivery-view', $delivery['id']) }}"
                                        class="btn btn-xs btn-primary">View</a>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    @php
                        $allDeliveries = collect($relatedDeliveries)->flatten(1);
                    @endphp
                    <div class="grid grid-cols-3 gap-4 pt-3 border-t">
                        <div class="text-center">
                            <div class="text-xl font-bold text-blue-600">{{ $allDeliveries->count() }}</div>
                            <div class="text-xs text-gray-500">Total Deliveries</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-bold text-green-600">{{ $allDeliveries->sum('items_count') }}</div>
                            <div class="text-xs text-gray-500">Total Items</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-bold text-purple-600">{{ number_format($allDeliveries->sum('total_amount'), 2) }}</div>
                            <div class="text-xs text-gray-500">Total Value</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($ris || $dataLoaded)
            {{-- RIS Form Table --}}
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden mb-6">
                {{-- Header info --}}
                <div class="p-4 border-b bg-gray-50">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="space-y-1">
                            <div class="flex"><span class="w-28 font-bold text-gray-600">Division:</span><span>Medical Service</span></div>
                            <div class="flex"><span class="w-28 font-bold text-gray-600">Office:</span><span>{{ $ris->officeName ?? ($officeName ?? 'N/A') }}</span></div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex"><span class="w-40 font-bold text-gray-600">Resp. Center Code:</span><span>{{ $ris->rcc ?? ($rcc ?? 'N/A') }}</span></div>
                            <div class="flex"><span class="w-40 font-bold text-gray-600">RIS No:</span><span>{{ $ris->risno ?? ($risNo ?? 'N/A') }}</span></div>
                            <div class="flex"><span class="w-40 font-bold text-gray-600">Date:</span><span>{{ $ris->formatted_risdate ?? ($risDate ?? 'N/A') }}</span></div>
                        </div>
                    </div>
                </div>

                {{-- Items table --}}
                <div class="overflow-x-auto">
                    <table class="table table-xs table-zebra">
                        <thead>
                            <tr class="bg-gray-100">
                                <th colspan="4" class="text-center bg-gray-200 text-xs uppercase font-bold">Requisition</th>
                                <th colspan="6" class="text-center bg-gray-300 text-xs uppercase font-bold">Issuance</th>
                                <th class="text-center bg-gray-200 text-xs uppercase font-bold">Actions</th>
                            </tr>
                            <tr class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                                <th class="text-white text-xs py-3 px-2">Invoice</th>
                                <th class="text-white text-xs py-3 px-2">Stock No.</th>
                                <th class="text-white text-xs py-3 px-2">Unit</th>
                                <th class="text-white text-xs py-3 px-2">Description</th>
                                <th class="text-white text-xs py-3 px-2 text-center">Qty</th>
                                <th class="text-white text-xs py-3 px-2">Batch No.</th>
                                <th class="text-white text-xs py-3 px-2">Expiry Date</th>
                                <th class="text-white text-xs py-3 px-2 text-right">Unit Price</th>
                                <th class="text-white text-xs py-3 px-2 text-right">Total</th>
                                <th class="text-white text-xs py-3 px-2">Fund Source</th>
                                <th class="text-white text-xs py-3 px-2 text-center">Drug Association</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($risDetails ?? [] as $detail)
                                <tr class="hover:bg-blue-50/50 transition-colors">
                                    <td class="text-xs px-2">{{ $detail->invoiceno ?? 'N/A' }}</td>
                                    <td class="text-xs px-2 text-center">{{ number_format($detail->stockno) }}</td>
                                    <td class="text-xs px-2 text-center">{{ $detail->unit ?? 'N/A' }}</td>
                                    <td class="text-xs px-2 font-medium">{{ $detail->description ?? 'N/A' }}</td>
                                    <td class="text-xs px-2 text-center font-bold">{{ isset($detail->itmqty) ? number_format($detail->itmqty, 2) : 'N/A' }}</td>
                                    <td class="text-xs px-2">{{ $detail->batch_no ?? 'N/A' }}</td>
                                    <td class="text-xs px-2 whitespace-nowrap">{{ $detail->sql_formatted_expire_date ?? 'N/A' }}</td>
                                    <td class="text-xs px-2 text-right font-mono">
                                        @if (isset($detail->fundSources) && count($detail->fundSources) > 0)
                                            @foreach ($detail->fundSources as $fund)
                                                <div>{{ number_format($fund->unitprice, 2) }}</div>
                                            @endforeach
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="text-xs px-2 text-right font-mono font-bold text-green-700">
                                        @if (isset($detail->fundSources) && count($detail->fundSources) > 0)
                                            @foreach ($detail->fundSources as $fund)
                                                <div>{{ number_format($detail->itmqty * $fund->unitprice, 2) }}</div>
                                            @endforeach
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="text-xs px-2">
                                        @if (isset($detail->fundSources) && count($detail->fundSources) > 0)
                                            @foreach ($detail->fundSources as $fund)
                                                <div class="text-xs">{{ $fund->fsname ?? 'Unknown' }}</div>
                                            @endforeach
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="text-xs px-2 text-center">
                                        @if (isset($detail->pdims_itemcode) && $detail->pdims_itemcode)
                                            <div class="flex flex-col items-center gap-1">
                                                <div class="text-xs text-green-700 font-semibold max-w-[200px] truncate"
                                                    title="{{ $detail->pdims_drugdesc }}">
                                                    {{ $detail->pdims_drugdesc }}
                                                </div>
                                                <div class="text-xs opacity-50">{{ $detail->pdims_itemcode }}</div>
                                                <button wire:click="removeDrugAssociation({{ $detail->itemID }})"
                                                    class="btn btn-xs btn-ghost text-red-500">
                                                    Remove
                                                </button>
                                            </div>
                                        @else
                                            <button wire:click="openDrugModal({{ $detail->itemID }})"
                                                class="btn btn-xs btn-outline btn-primary">
                                                Link Drug
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-8 text-gray-400">No items found</td>
                                </tr>
                            @endforelse
                        </tbody>
                        {{-- Purpose & Grand Total --}}
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="font-bold text-xs px-2">Purpose:</td>
                                <td colspan="10" class="text-xs px-2">{{ $ris->purpose ?? ($purpose ?? 'N/A') }}</td>
                            </tr>
                            <tr class="font-bold">
                                <td colspan="8" class="text-right text-xs px-2">Grand Total:</td>
                                <td class="text-right text-xs px-2 text-green-700">
                                    @php
                                        $grandTotal = 0;
                                        if (isset($risDetails) && count($risDetails) > 0) {
                                            foreach ($risDetails as $detail) {
                                                if (isset($detail->fundSources) && count($detail->fundSources) > 0) {
                                                    foreach ($detail->fundSources as $fund) {
                                                        $grandTotal += $detail->itmqty * $fund->unitprice;
                                                    }
                                                }
                                            }
                                        }
                                    @endphp
                                    {{ number_format($grandTotal, 2) }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Related IAR --}}
            @if ($relatedIar)
                <div class="bg-white rounded-2xl shadow-lg border border-blue-100 p-6 mb-6">
                    <h3 class="text-lg font-bold text-blue-700 mb-4 flex items-center gap-2">
                        <x-mary-icon name="o-document-text" class="w-5 h-5" />
                        Related IAR Information
                    </h3>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">IAR Number:</span>
                            <div class="font-bold">{{ $relatedIar->iarNo ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">IAR Date:</span>
                            <div class="font-bold">{{ $relatedIar->formatted_iardate ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Supplier:</span>
                            <div class="font-bold">{{ $relatedIar->supplier ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            @endif
        @else
            <div class="flex flex-col items-center justify-center py-20 opacity-40">
                <x-mary-icon name="o-document-text" class="w-16 h-16 mb-4" />
                <h3 class="text-xl font-semibold">No RIS Data Found</h3>
                <p class="text-sm mt-2">The requested RIS information could not be retrieved.</p>
                <x-mary-button label="Return to RIS List" icon="o-arrow-left"
                    link="{{ route('purchases.ris') }}" class="btn-primary mt-4" />
            </div>
        @endif
    @endif

    {{-- Drug Search Modal --}}
    <x-mary-modal wire:model="isDrugModalOpen" title="Associate Drug with Item" class="backdrop-blur" box-class="max-w-lg">
        @if ($selectedItemId)
            @php
                $selectedItem = collect($risDetails ?? [])->firstWhere('itemID', $selectedItemId);
            @endphp
            @if ($selectedItem)
                <div class="p-3 bg-blue-50 rounded-lg mb-4">
                    <p class="text-sm font-semibold text-blue-800">Selected Item:</p>
                    <p class="text-sm mt-1">{{ $selectedItem->description }}</p>
                    @if ($selectedItem->pdims_itemcode)
                        <div class="mt-2 pt-2 border-t border-blue-200">
                            <p class="text-xs text-blue-600">Current: {{ $selectedItem->pdims_drugdesc }}</p>
                        </div>
                    @endif
                </div>
            @endif
        @endif

        <x-mary-input label="Search for a drug" wire:model.live.debounce.300ms="drugSearchTerm"
            wire:keyup="searchDrugs" placeholder="Type at least 2 characters..." icon="o-magnifying-glass" />

        @if (count($searchResults) > 0)
            <div class="mt-3 max-h-60 overflow-y-auto border rounded-lg divide-y">
                @foreach ($searchResults as $drug)
                    <div class="p-2 hover:bg-blue-50 cursor-pointer transition-colors"
                        wire:click="associateDrug('{{ $drug['id'] }}')">
                        <div class="text-sm font-medium">{{ $drug['name'] }}</div>
                        <div class="text-xs text-gray-500">{{ $drug['id'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('isDrugModalOpen', false)" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Transfer to Delivery Modal --}}
    <x-mary-modal wire:model="isTransferModalOpen" title="Transfer RIS to Pharmacy Delivery" class="backdrop-blur" box-class="max-w-xl">
        <div class="p-3 bg-blue-50 rounded-lg mb-4 text-sm">
            <p><strong>RIS #:</strong> {{ $ris->risno ?? ($risNo ?? 'N/A') }}</p>
            <p><strong>Date:</strong> {{ $ris->formatted_risdate ?? ($risDate ?? 'N/A') }}</p>
            <p><strong>Items to transfer:</strong> {{ $associationStatus['total'] ?? 0 }}</p>
        </div>

        <x-mary-form wire:submit="transferToDelivery">
            <x-mary-select label="Supplier" wire:model="deliveryData.suppcode"
                :options="$suppliers->map(fn($s) => ['id' => $s->suppcode, 'name' => $s->suppname])"
                option-value="id" option-label="name" placeholder="Select supplier" icon="o-building-storefront" required />

            <x-mary-select label="Delivery Type" wire:model="deliveryData.delivery_type"
                :options="[['id' => 'RIS', 'name' => 'RIS Transfer'], ['id' => 'REGULAR', 'name' => 'Regular Delivery'], ['id' => 'EMERGENCY', 'name' => 'Emergency']]"
                option-value="id" option-label="name" icon="o-truck" required />

            <x-mary-select label="Fund Source" wire:model="deliveryData.charge_code" :options="$chargeCodes"
                option-value="chrgcode" option-label="chrgdesc" placeholder="Select fund source" icon="o-banknotes" required />

            <x-mary-select label="Pharmacy Location" wire:model="deliveryData.pharm_location_id" :options="$pharmacyLocations"
                option-value="id" option-label="description" placeholder="Select location" icon="o-map-pin" required />

            <x-mary-input label="Delivery Date" wire:model="deliveryData.delivery_date" type="date" icon="o-calendar" required />

            @if ($relatedIar && $relatedIar->invoiceNo)
                <x-mary-input label="SI Number" value="{{ $relatedIar->invoiceNo }}" icon="o-document-text" readonly disabled />
                <p class="text-xs text-blue-600 -mt-2">Using invoice number from related IAR</p>
            @else
                <x-mary-input label="SI Number" wire:model="deliveryData.si_no" icon="o-document-text"
                    placeholder="Enter SI number if available" />
            @endif

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="closeTransferModal" />
                <x-mary-button label="Transfer to Delivery" type="submit" class="btn-success" spinner="transferToDelivery" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
