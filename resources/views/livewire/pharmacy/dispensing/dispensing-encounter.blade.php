@php
    $grand_total = 0;
@endphp

<div class="flex flex-col h-full" x-data="{
    selectedItems: @entangle('selected_items'),
    toggleItem(id) {
        if (this.selectedItems.includes(id)) {
            this.selectedItems = this.selectedItems.filter(i => i !== id);
        } else {
            this.selectedItems.push(id);
        }
        $wire.call('updateSelectedItems', this.selectedItems);
    },
    selectAllPending() {
        document.querySelectorAll('.pending-checkbox').forEach(cb => {
            const val = cb.value;
            if (!this.selectedItems.includes(val)) {
                this.selectedItems.push(val);
            }
        });
        $wire.call('updateSelectedItems', this.selectedItems);
    },
    clearSelection() {
        this.selectedItems = [];
        $wire.call('updateSelectedItems', this.selectedItems);
    }
}">
    @if (!$hasEncounter)
        {{-- Empty State: No patient/encounter selected --}}
        <div class="flex flex-col items-center justify-center flex-1 p-8">
            <div class="max-w-md text-center">
                <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full bg-base-200">
                    <x-heroicon-o-clipboard-document-list class="w-10 h-10 text-base-content/30" />
                </div>
                <h2 class="text-2xl font-bold mb-2">Dispensing Encounter</h2>
                <p class="text-base-content/60 mb-6">No patient or encounter selected. Search for a patient and select an
                    encounter to begin dispensing.</p>
                <x-mary-button label="Search Patient & Encounter" icon="o-magnifying-glass" class="btn-primary btn-lg"
                    wire:click="openEncounterSelector" />
            </div>
        </div>
    @else
        {{-- Patient Info Bar --}}
        <div class="border-b bg-base-100 border-base-200">
            <div class="px-4 py-2">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-full bg-primary/10">
                            <x-heroicon-o-user class="w-6 h-6 text-primary" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold">{{ $patlast }}, {{ $patfirst }} {{ $patmiddle }}
                            </h2>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/70">
                                <span>{{ $hpercode }}</span>
                                <span>|</span>
                                <span class="font-medium">
                                    @if ($toecode == 'ADM' || $toecode == 'OPDAD' || $toecode == 'ERADM')
                                        {{ $wardname }} - {{ $rmname }}
                                    @else
                                        {{ $toecode }}
                                    @endif
                                </span>
                                <span>|</span>
                                <span>Class: <strong>{{ $this->getMssClassification() }}</strong></span>
                                @if ($diagtext)
                                    <x-mary-hr />
                                    <span>Dx: {{ \Illuminate\Support\Str::limit($diagtext, 255) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pr-5">
                        @if ($billstat == '02' || $billstat == '03')
                            <div class="badge badge-error gap-1">
                                <x-heroicon-o-lock-closed class="w-3 h-3" /> FINAL BILL
                            </div>
                        @endif
                        <x-mary-button label="Browse Encounters" icon="o-queue-list"
                            class="btn-sm btn-outline btn-accent" wire:click="openEncounterSelector"
                            tooltip-bottom="Browse Encounters (F3)" />
                        <x-mary-button label="Change Patient" icon="o-arrows-right-left" class="btn-sm btn-outline"
                            wire:click="openChangePatient" tooltip-bottom="Change Patient (F2)" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="flex flex-1 overflow-hidden">
            {{-- Left: Orders Table --}}
            <div class="flex flex-col flex-1 overflow-hidden border-r border-base-200">
                {{-- Action Bar --}}
                <div class="border-b bg-base-200/50 border-base-200">
                    <div class="flex items-center justify-between px-4 py-2">
                        <div class="flex gap-2">
                            <x-mary-button label="Prescriptions" icon="o-clipboard-document-list"
                                class="btn-sm btn-outline" wire:click="$set('showPrescriptionListModal', true)"
                                tooltip-bottom="View All Prescriptions (F4)" />
                            <x-mary-button label="Summary" icon="o-document-text" class="btn-sm btn-outline"
                                wire:click="$set('showSummaryModal', true)"
                                tooltip-bottom="Summary of Issued Drugs (F5)" />
                            <a href="{{ route('dispensing.rxo.return.sum', $hpercode) }}" target="_blank"
                                class="btn btn-sm btn-outline tooltip tooltip-bottom"
                                data-tip="View Issued with Return (F6)">
                                <x-heroicon-o-arrow-uturn-left class="w-4 h-4" /> Issued with Return
                            </a>
                        </div>

                        @if ($billstat != '02' && $billstat != '03')
                            <div class="flex gap-2">
                                <x-mary-button label="Select All Pending" icon="o-check-circle" class="btn-sm btn-ghost"
                                    x-on:click="selectAllPending()" tooltip-bottom="Select All Pending (Ctrl+A)" />
                                <x-mary-button label="Clear" icon="o-x-circle" class="btn-sm btn-ghost"
                                    x-on:click="clearSelection()" tooltip-bottom="Clear Selection (Esc)" />

                                <div class="border-l border-base-300 h-6 mx-1"></div>

                                <x-mary-button label="Delete" icon="o-trash" class="btn-sm btn-error btn-outline"
                                    wire:click="delete_item"
                                    wire:confirm="Delete selected pending items? This cannot be undone."
                                    tooltip-bottom="Delete Selected (Del)" />
                                <x-mary-button label="Charge" icon="o-credit-card" class="btn-sm btn-info btn-outline"
                                    wire:click="charge_items" tooltip-bottom="Charge Selected (Ctrl+C)" />
                                <x-mary-button label="Issue" icon="o-paper-airplane" class="btn-sm btn-success"
                                    wire:click="$wire.$set('showIssueModal', true)"
                                    tooltip-bottom="Issue Charged Items (Ctrl+I)" />
                            </div>
                        @endif
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <table class="table table-xs table-pin-rows">
                        <thead>
                            <tr class="bg-base-200">
                                <th class="w-8"></th>
                                <th class="text-center">Status</th>
                                <th>Drug / Medicine</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Amount</th>
                                <th>Remarks</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $rxo)
                                @php $grand_total += $rxo->pcchrgamt; @endphp
                                <tr class="hover" wire:key="order-{{ $rxo->docointkey }}">
                                    <td>
                                        @if ($rxo->estatus == 'U' && !$rxo->pcchrgcod)
                                            <input type="checkbox" class="checkbox checkbox-xs pending-checkbox"
                                                value="{{ $rxo->docointkey }}"
                                                :checked="selectedItems.includes('{{ $rxo->docointkey }}')"
                                                x-on:change="toggleItem('{{ $rxo->docointkey }}')" />
                                        @elseif ($rxo->estatus == 'P' && $rxo->pcchrgcod)
                                            <input type="checkbox" class="checkbox checkbox-xs"
                                                value="{{ $rxo->docointkey }}"
                                                :checked="selectedItems.includes('{{ $rxo->docointkey }}')"
                                                x-on:change="toggleItem('{{ $rxo->docointkey }}')" />
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($rxo->pcchrgcod)
                                            <a href="{{ route('dispensing.rxo.chargeslip', $rxo->pcchrgcod) }}"
                                                target="_blank" class="link link-primary text-xs">
                                                {{ $rxo->pcchrgcod }}
                                            </a>
                                            <br>
                                        @endif
                                        @if ($rxo->estatus == 'U' && !$rxo->pcchrgcod)
                                            <span class="badge badge-xs badge-warning">Pending</span>
                                        @elseif ($rxo->estatus == 'P' && $rxo->pcchrgcod)
                                            <span class="badge badge-xs badge-info">Charged</span>
                                        @elseif ($rxo->estatus == 'S')
                                            <span class="badge badge-xs badge-success">Issued</span>
                                            @if ($rxo->tx_type)
                                                <span
                                                    class="badge badge-xs badge-ghost">{{ strtoupper($rxo->tx_type) }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-xs font-medium max-w-xs truncate"
                                        title="{{ $rxo->drug_concat }}">
                                        {{ $rxo->drug_concat }}
                                        @if ($rxo->prescription_data_id)
                                            <x-heroicon-s-document-check class="inline w-3 h-3 text-primary" />
                                        @endif
                                        <br>
                                        <span class="badge badge-xs badge-ghost">{{ $rxo->chrgdesc }}</span>
                                    </td>
                                    <td class="text-xs text-center font-semibold">
                                        @if ($rxo->estatus == 'S')
                                            {{ number_format($rxo->qtyissued, 0) }}
                                        @else
                                            {{ number_format($rxo->pchrgqty, 0) }}
                                        @endif
                                    </td>
                                    <td class="text-xs text-right">{{ number_format($rxo->pchrgup, 2) }}</td>
                                    <td class="text-xs text-right font-semibold">
                                        {{ number_format($rxo->pcchrgamt, 2) }}
                                    </td>
                                    <td class="text-xs max-w-[120px] truncate" title="{{ $rxo->remarks }}">
                                        {{ $rxo->remarks }}</td>
                                    <td>
                                        <div class="dropdown dropdown-end">
                                            <label tabindex="0" class="btn btn-ghost btn-xs tooltip tooltip-left"
                                                data-tip="Actions">
                                                <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                                            </label>
                                            <ul tabindex="0"
                                                class="dropdown-content menu bg-base-100 rounded-box z-50 w-44 p-2 shadow-lg border border-base-200">
                                                @if ($rxo->estatus == 'U' && !$rxo->pcchrgcod)
                                                    <li>
                                                        <a
                                                            wire:click="openUpdateQtyModal('{{ $rxo->docointkey }}', {{ $rxo->pchrgqty }}, {{ $rxo->pchrgup }})">
                                                            <x-heroicon-o-pencil-square class="w-4 h-4" /> Edit Qty
                                                        </a>
                                                    </li>
                                                @endif

                                                @if ($rxo->estatus == 'S' && $rxo->qtyissued > 0)
                                                    <li>
                                                        <a
                                                            wire:click="openReturnModal('{{ $rxo->docointkey }}', {{ $rxo->pchrgup }})">
                                                            <x-heroicon-o-arrow-uturn-left class="w-4 h-4" /> Return
                                                        </a>
                                                    </li>
                                                @endif

                                                <li>
                                                    <a
                                                        wire:click="openRemarksModal('{{ $rxo->docointkey }}', '{{ addslashes($rxo->remarks) }}')">
                                                        <x-heroicon-o-chat-bubble-left class="w-4 h-4" /> Remarks
                                                    </a>
                                                </li>

                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-8 text-base-content/50">
                                        <x-heroicon-o-inbox class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                        No orders found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Grand Total Footer --}}
                <div class="flex items-center justify-between px-4 py-2 border-t bg-base-200 border-base-300">
                    <span class="text-sm text-base-content/70">{{ count($orders) }} item(s)</span>
                    <span class="text-lg font-bold">Total: {{ number_format($grand_total, 2) }}</span>
                </div>
            </div>

            {{-- Right Sidebar: Stocks & Prescriptions --}}
            <div class="flex flex-col w-[420px] overflow-hidden bg-base-100" x-data="{ stockCount: @entangle('stocksDisplayCount') }">

                {{-- Search & Filter --}}
                <div class="p-2 space-y-2 border-b border-base-200">
                    <x-mary-input wire:model.live.debounce.300ms="generic" icon="o-magnifying-glass"
                        placeholder="Search drug name..." class="input-sm" clearable />
                    <x-mary-choices wire:model.live="charge_code_filter" :options="$charges" option-value="chrgcode"
                        option-label="chrgdesc" placeholder="Filter by fund source..." class="select-sm" multiple />
                </div>

                {{-- Stocks Section --}}
                <div class="flex flex-col flex-1 min-h-0 border-b border-base-200">
                    <div
                        class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide bg-base-200 text-base-content/70 border-b border-base-300">
                        Stocks
                    </div>
                    <div class="flex-1 overflow-y-auto"
                        x-on:scroll.debounce.150ms="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 100) { $wire.loadMoreStocks() }">
                        <table class="table table-xs table-pin-rows table-zebra">
                            <thead>
                                <tr class="bg-base-200">
                                    <th>Drug / Medicine</th>
                                    <th class="text-end">Bal/Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($stocks as $stock)
                                    <tr class="cursor-pointer hover"
                                        wire:key="stock-{{ $stock->id }}-{{ $stock->chrgcode }}"
                                        @if ($billstat != '02' && $billstat != '03') wire:click="selectStock('{{ $stock->id }}', '{{ $stock->chrgcode }}', '{{ $stock->dmdcomb }}',
                                        '{{ $stock->dmdctr }}', '{{ $stock->loc_code }}', '{{ $stock->dmdprdte }}', '{{ $stock->exp_date }}',
                                        '{{ $stock->stock_bal }}', '{{ $stock->dmselprice }}')" @endif>
                                        <td class="text-xs">
                                            <div class="font-medium truncate max-w-[280px]"
                                                title="{{ $stock->drug_concat }}">
                                                {{ $stock->drug_concat }}
                                            </div>
                                            @if (str_contains($stock->chrgdesc, 'Consignment'))
                                                <span class="text-white badge badge-sm bg-pink"
                                                    style="background:#db2777;">
                                                    {{ $stock->chrgdesc }}
                                                </span>
                                            @else
                                                <span class="badge badge-xs badge-ghost">{{ $stock->chrgdesc }}</span>
                                            @endif
                                            @if ($stock->days_to_expiry <= 90)
                                                <span
                                                    class="badge badge-xs badge-error">{{ date('m/Y', strtotime($stock->exp_date)) }}</span>
                                            @elseif ($stock->days_to_expiry <= 180)
                                                <span
                                                    class="badge badge-xs badge-warning">{{ date('m/Y', strtotime($stock->exp_date)) }}</span>
                                            @else
                                                <span
                                                    class="badge badge-xs badge-success">{{ date('m/Y', strtotime($stock->exp_date)) }}</span>
                                            @endif

                                        </td>
                                        <td class="text-xs text-end">
                                            <div class=" font-semibold">
                                                {{ number_format($stock->stock_bal, 0) }}
                                            </div>
                                            <div>
                                                {{ number_format($stock->dmselprice, 2) }}
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-base-content/50">No stocks
                                            found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if (count($stocks) >= $stocksDisplayCount)
                            <div class="p-2 text-center">
                                <button wire:click="loadMoreStocks" class="btn btn-xs btn-ghost">Load more...</button>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Prescriptions Section --}}
                <div class="flex flex-col flex-1 min-h-0">
                    <div
                        class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide bg-base-200 text-base-content/70 border-b border-base-300">
                        Prescriptions
                        @if (count($active_prescription) > 0)
                            <span class="badge badge-xs badge-primary ml-1">{{ count($active_prescription) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        @forelse ($active_prescription as $presc)
                            @forelse ($presc->data_active ?? [] as $presc_data)
                                <div class="flex items-center gap-2 px-3 py-2 border-b border-base-200 hover:bg-base-200/50"
                                    wire:key="rx-{{ $presc_data->id }}">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-medium truncate"
                                            title="{{ $presc_data->dm->drug_concat() }}">
                                            {{ $presc_data->dm->drug_concat() }}
                                        </div>
                                        <div class="flex gap-2 text-xs text-base-content/60">
                                            <span>Qty: {{ $presc_data->qty }}</span>
                                            @switch(strtoupper($presc_data->order_type ?? ''))
                                                @case('G24')
                                                    <span class="badge badge-xs badge-error">G24</span>
                                                @break

                                                @case('OR')
                                                    <span class="badge badge-xs badge-secondary">OR</span>
                                                @break

                                                @default
                                                    <span class="badge badge-xs badge-accent">Basic</span>
                                            @endswitch
                                            @if ($presc_data->remark)
                                                <span>{{ $presc_data->remark }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($billstat != '02' && $billstat != '03')
                                        @if ($toecode == 'OPD' || $toecode == 'WALKN')
                                            <button class="btn btn-xs btn-primary tooltip tooltip-left"
                                                data-tip="Add Prescribed Item"
                                                wire:click="openPrescribedItemModal({{ $presc_data->id }},'{{ $presc_data->dmdcomb }}','{{ $presc_data->dmdctr }}','{{ $presc->empid }}','{{ $presc_data->qty }}')">
                                                <x-heroicon-o-plus class="w-3 h-3" />
                                            </button>
                                        @else
                                            <button class="btn btn-xs btn-ghost tooltip tooltip-left"
                                                data-tip="Search in Stocks"
                                                wire:click="searchGenericItem({{ $presc_data->id }},'{{ explode(',', $presc_data->dm->drug_concat())[0] }}','{{ $presc_data->dmdcomb }}','{{ $presc_data->dmdctr }}','{{ $presc->empid }}')">
                                                <x-heroicon-o-magnifying-glass class="w-3 h-3" />
                                            </button>
                                        @endif

                                        <button class="btn btn-xs btn-ghost btn-error tooltip tooltip-left"
                                            data-tip="Deactivate Rx"
                                            wire:click="confirmDeactivateRx({{ $presc_data->id }})">
                                            <x-heroicon-o-x-mark class="w-3 h-3" />
                                        </button>
                                    @endif

                                </div>
                                @empty
                                @endforelse
                                @empty
                                    <div class="py-8 text-center text-base-content/50">
                                        <x-heroicon-o-clipboard-document class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                        No active prescriptions
                                    </div>
                                @endforelse

                                {{-- Extra prescriptions from previous encounter --}}
                                @if (count($extra_prescriptions) > 0)
                                    <div class="px-3 py-1 text-xs font-semibold bg-base-200">Previous Encounter</div>
                                    @foreach ($extra_prescriptions as $extra)
                                        @forelse ($extra->data_active ?? [] as $extra_data)
                                            <div class="flex items-center gap-2 px-3 py-2 border-b border-base-200 hover:bg-base-200/50 opacity-75"
                                                wire:key="extra-rx-{{ $extra_data->id }}">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-medium truncate">
                                                        {{ $extra_data->dm->drug_concat() }}
                                                    </div>
                                                    <div class="flex gap-2 text-xs text-base-content/60">
                                                        <span>Qty: {{ $extra_data->qty }}</span>
                                                    </div>
                                                </div>
                                                @if ($billstat != '02' && $billstat != '03')
                                                    <button class="btn btn-xs btn-ghost tooltip tooltip-left"
                                                        data-tip="Search in Stocks"
                                                        wire:click="searchExtraGeneric({{ $extra_data->id }},'{{ explode(',', $extra_data->dm->drug_concat())[0] }}','{{ $extra_data->dmdcomb }}','{{ $extra_data->dmdctr }}','{{ $extra->empid }}')">
                                                        <x-heroicon-o-magnifying-glass class="w-3 h-3" />
                                                    </button>
                                                @endif
                                            </div>
                                        @empty
                                        @endforelse
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Add Stock Item Modal --}}
            <x-mary-modal wire:model="showAddItemModal" title="Add Item" class="backdrop-blur">
                <div class="space-y-4">
                    <div class="text-sm font-medium text-base-content/70">
                        Selected stock item
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <x-mary-input label="Quantity" wire:model="order_qty" type="number" min="1"
                            class="input-lg text-4xl text-center font-bold" autofocus />
                        <div class="space-y-2">
                            <x-mary-input label="Unit Price" wire:model="unit_price" type="number" step="0.01" />
                            <div class="text-sm text-right">
                                Total: <strong>{{ number_format(($order_qty ?? 0) * ($unit_price ?? 0), 2) }}</strong>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Remarks</span></label>
                        <textarea wire:model="remarks" class="w-full textarea textarea-bordered" rows="2"
                            placeholder="Optional remarks..."></textarea>
                    </div>
                </div>
                <x-slot:actions>
                    <x-mary-button label="Cancel" @click="$wire.showAddItemModal = false" />
                    <x-mary-button label="Add Item" icon="o-plus" class="btn-primary" wire:click="add_item" spinner />
                </x-slot:actions>
            </x-mary-modal>

            {{-- Add Prescribed Item Modal (OPD/WALKN) --}}
            <x-mary-modal wire:model="showPrescribedItemModal" title="Add Prescribed Item" class="backdrop-blur">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4">
                        <x-mary-input label="Quantity" wire:model="order_qty" type="number" min="1"
                            class="input-lg text-4xl text-center font-bold" autofocus />
                        <div>
                            <label class="label"><span class="label-text">Fund Source</span></label>
                            @if (count($rx_available_charges) > 0)
                                <div class="space-y-1">
                                    @foreach ($rx_available_charges as $avail)
                                        <label
                                            class="flex items-center justify-between p-2 rounded-lg border cursor-pointer hover:bg-base-200 transition-colors {{ $rx_charge_code === $avail->chrgcode ? 'border-primary bg-primary/5' : 'border-base-300' }}">
                                            <div class="flex items-center gap-2">
                                                <input type="radio" name="rx_charge_code"
                                                    class="radio radio-sm radio-primary" wire:model="rx_charge_code"
                                                    value="{{ $avail->chrgcode }}" />
                                                <span class="text-sm">{{ $avail->chrgdesc }}</span>
                                            </div>
                                            <span
                                                class="badge badge-sm badge-ghost font-semibold">{{ number_format($avail->stock_bal, 0) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-3 rounded-lg bg-warning/10 text-sm text-warning">
                                    No stock available for this item in any fund source.
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="label"><span class="label-text">Remarks</span></label>
                            <textarea wire:model="remarks" class="w-full textarea textarea-bordered" rows="2"
                                placeholder="Optional remarks..."></textarea>
                        </div>
                    </div>
                </div>
                <x-slot:actions>
                    <x-mary-button label="Cancel" @click="$wire.showPrescribedItemModal = false" />
                    <x-mary-button label="Add Item" icon="o-plus" class="btn-primary" wire:click="add_prescribed_item"
                        spinner />
                </x-slot:actions>
            </x-mary-modal>

            {{-- Update Qty Modal --}}
            <x-mary-modal wire:model="showUpdateQtyModal" title="Update Order Quantity" class="backdrop-blur">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <x-mary-input label="Quantity" wire:model="order_qty" type="number" min="1"
                            class="input-lg text-4xl text-center font-bold" autofocus />
                        <div class="space-y-2">
                            <x-mary-input label="Unit Price" wire:model="unit_price" type="number" step="0.01"
                                readonly />
                            <div class="text-sm text-right">
                                Total: <strong>{{ number_format(($order_qty ?? 0) * ($unit_price ?? 0), 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <x-slot:actions>
                    <x-mary-button label="Cancel" @click="$wire.showUpdateQtyModal = false" />
                    <x-mary-button label="Update" icon="o-check" class="btn-primary" wire:click="update_qty" spinner />
                </x-slot:actions>
            </x-mary-modal>

            {{-- Return Issued Modal --}}
            <x-mary-modal wire:model="showReturnModal" title="Return Issued Item" class="backdrop-blur">
                <div class="space-y-4">
                    <x-mary-input label="Return Quantity" wire:model="return_qty" type="number" min="1"
                        class="input-lg text-center font-bold" autofocus />
                    <x-mary-input label="Unit Price" wire:model="unit_price" type="number" step="0.01" readonly />
                    <div class="text-sm text-right">
                        Total Return: <strong
                            class="text-error">{{ number_format(($return_qty ?? 0) * ($unit_price ?? 0), 2) }}</strong>
                    </div>
                </div>
                <x-slot:actions>
                    <x-mary-button label="Cancel" @click="$wire.showReturnModal = false" />
                    <x-mary-button label="Return Item" icon="o-arrow-uturn-left" class="btn-error"
                        wire:click="return_issued('{{ $docointkey }}')" spinner />
                </x-slot:actions>
            </x-mary-modal>

            {{-- Deactivate Prescription Modal --}}
            <x-mary-modal wire:model="showDeactivateRxModal" title="Deactivate Prescription" class="backdrop-blur">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg bg-warning/10">
                        <p class="text-sm">This will mark the prescription as inactive. Please provide a reason.</p>
                    </div>
                    <x-mary-input label="Reason / Remarks" wire:model="adttl_remarks" placeholder="Enter reason..." />
                </div>
                <x-slot:actions>
                    <x-mary-button label="Cancel" @click="$wire.showDeactivateRxModal = false" />
                    <x-mary-button label="Deactivate" icon="o-x-mark" class="btn-error" wire:click="deactivate_rx"
                        spinner />
                </x-slot:actions>
            </x-mary-modal>

            {{-- Issue Confirmation Modal --}}
            <x-mary-modal wire:model="showIssueModal" title="Issue Selected Items" class="backdrop-blur">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg bg-info/10">
                        <p class="text-sm">Issue all charged items to the patient.</p>
                    </div>

                    @if ($toecode == 'ADM' || $toecode == 'OPDAD' || $toecode == 'ERADM')
                        {{-- Admitted patient: Basic/Non-Basic toggle --}}
                        <div class="form-control">
                            <label class="label font-bold"><span class="label-text">TAG</span></label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="label-text">Basic (Service)</span>
                                <input type="checkbox" wire:model="bnb" class="toggle toggle-success" />
                                <span class="label-text">NON-Basic (Pay)</span>
                            </label>
                        </div>
                    @else
                        {{-- OPD/ER/WALKN: Tag selection --}}
                        <div class="space-y-3">
                            <label class="label font-bold"><span class="label-text">TAG</span></label>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach (['pay' => 'Pay', 'ems' => 'EMS', 'konsulta' => 'Konsulta', 'wholesale' => 'Wholesale', 'caf' => 'CAF', 'maip' => 'MAIP', 'is_ris' => 'RIS', 'pcso' => 'PCSO', 'phic' => 'PHIC'] as $key => $label)
                                    <label
                                        class="flex items-center gap-2 p-2 rounded-lg border border-base-300 cursor-pointer hover:bg-base-200 transition-colors"
                                        :class="{ 'border-primary bg-primary/5': $wire.{{ $key }} }">
                                        <input type="radio" name="issue_tag" class="radio radio-sm radio-primary"
                                            wire:click="selectIssueTag('{{ $key }}')" @checked($$key ?? false) />
                                        <span class="text-sm">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>

                            @if ($toecode != 'ER')
                                <div>
                                    <label class="label font-bold"><span class="label-text">Department</span></label>
                                    <select wire:model="deptcode" class="w-full select select-bordered">
                                        <option value="">Select department...</option>
                                        @foreach ($departments as $dept)
                                            <option value="{{ $dept->deptcode }}">{{ $dept->deptname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
                <x-slot:actions>
                    <x-mary-button label="Cancel" @click="$wire.showIssueModal = false" />
                    <x-mary-button label="Confirm Issue" icon="o-paper-airplane" class="btn-success"
                        wire:click="issue_order" spinner />
                </x-slot:actions>
            </x-mary-modal>

            {{-- Summary Modal --}}
            <x-mary-modal wire:model="showSummaryModal" title="Summary of Issued Drugs and Meds" class="backdrop-blur"
                box-class="max-w-3xl">
                <table class="table table-xs">
                    <thead>
                        <tr class="bg-base-200">
                            <th>Item Description</th>
                            <th class="text-right">Qty Issued</th>
                            <th>Time of Last Issuance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summaries as $sum)
                            <tr class="hover">
                                <td class="text-xs">{{ $sum->drug_concat }}</td>
                                <td class="text-xs text-right font-semibold">{{ $sum->qty_issued }}</td>
                                <td class="text-xs">{{ $sum->last_issue }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-base-content/50">No issued items</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <x-slot:actions>
                    <x-mary-button label="Close" @click="$wire.showSummaryModal = false" />
                </x-slot:actions>
            </x-mary-modal>

            {{-- Prescription List Modal (All including inactive) --}}
            <x-mary-modal wire:model="showPrescriptionListModal" title="All Prescriptions" class="backdrop-blur"
                box-class="max-w-4xl">
                <table class="table table-xs">
                    <thead>
                        <tr class="bg-base-200">
                            <th>Date</th>
                            <th>Drug / Medicine</th>
                            <th class="text-center">Type</th>
                            <th>Remark</th>
                            <th>Physician</th>
                            <th class="text-center">Status</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($active_prescription_all as $presc_all)
                            @forelse ($presc_all->data ?? [] as $presc_all_data)
                                <tr class="hover" wire:key="rx-all-{{ $presc_all_data->id }}">
                                    <td class="text-xs">
                                        {{ date('Y-m-d', strtotime($presc_all_data->updated_at)) }}
                                        {{ date('h:i A', strtotime($presc_all_data->updated_at)) }}
                                    </td>
                                    <td class="text-xs cursor-pointer"
                                        @if ($presc_all_data->stat == 'A') @if ($toecode == 'OPD' || $toecode == 'WALKN')
                                            wire:click="openPrescribedItemFromAll({{ $presc_all_data->id }},'{{ $presc_all_data->dmdcomb }}','{{ $presc_all_data->dmdctr }}','{{ $presc_all->empid }}','{{ $presc_all_data->qty }}')"
                                         @else
                                            wire:click="searchGenericFromAll({{ $presc_all_data->id }},'{{ explode(',', $presc_all_data->dm->drug_concat())[0] }}','{{ $presc_all_data->dmdcomb }}','{{ $presc_all_data->dmdctr }}','{{ $presc_all->empid }}')" @endif
                                        @endif>
                                        {{ $presc_all_data->dm->drug_concat() }}
                                    </td>
                                    <td class="text-xs text-center">
                                        @switch(strtoupper($presc_all_data->order_type ?? ''))
                                            @case('G24')
                                                <span class="badge badge-xs badge-error">G24 ({{ $presc_all_data->qty }})</span>
                                            @break

                                            @case('OR')
                                                <span class="badge badge-xs badge-secondary">OR ({{ $presc_all_data->qty }})</span>
                                            @break

                                            @default
                                                <span class="badge badge-xs badge-accent">{{ $presc_all_data->qty }}</span>
                                        @endswitch
                                    </td>
                                    <td class="text-xs">{{ $presc_all_data->remark }}</td>
                                    <td class="text-xs">
                                        {{ $presc_all_data->employee ? $presc_all_data->employee->fullname : '' }}</td>
                                    <td class="text-xs text-center">
                                        @if ($presc_all_data->stat == 'A')
                                            <span class="badge badge-xs badge-primary">A</span>
                                        @else
                                            <span class="badge badge-xs badge-error">{{ $presc_all_data->stat }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($presc_all_data->stat == 'A')
                                            <button class="btn btn-xs btn-ghost btn-error tooltip tooltip-left"
                                                data-tip="Deactivate"
                                                wire:click="confirmDeactivatePrescription({{ $presc_all_data->id }},'{{ $presc_all_data->dmdcomb }}','{{ $presc_all_data->dmdctr }}','{{ $presc_all->empid }}')">
                                                <x-heroicon-o-x-mark class="w-3 h-3" />
                                            </button>
                                        @else
                                            <button class="btn btn-xs btn-ghost btn-success tooltip tooltip-left"
                                                data-tip="Reactivate" wire:click="reactivate_rx({{ $presc_all_data->id }})">
                                                <x-heroicon-o-check class="w-3 h-3" />
                                            </button>
                                        @endif
                                    </td>

                                </tr>
                                @empty
                                @endforelse
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-base-content/50">No prescriptions found</td>
                                    </tr>
                                @endforelse

                                {{-- Extra prescriptions from previous encounter --}}
                                @if (count($extra_prescriptions_all) > 0)
                                    <tr>
                                        <td colspan="7" class="font-semibold bg-base-200">Previous Encounter</td>
                                    </tr>
                                    @foreach ($extra_prescriptions_all as $extra_all)
                                        @forelse ($extra_all->data ?? [] as $extra_all_data)
                                            <tr class="hover opacity-75" wire:key="rx-extra-all-{{ $extra_all_data->id }}">
                                                <td class="text-xs">
                                                    {{ date('Y-m-d h:i A', strtotime($extra_all_data->updated_at)) }}
                                                </td>
                                                <td class="text-xs cursor-pointer"
                                                    @if ($extra_all_data->stat == 'A') wire:click="$wire.$set('rx_id', {{ $extra_all_data->id }});
                                            $wire.$set('generic', '{{ explode(',', $extra_all_data->dm->drug_concat())[0] }}');
                                            $wire.$set('rx_dmdcomb', '{{ $extra_all_data->dmdcomb }}');
                                            $wire.$set('rx_dmdctr', '{{ $extra_all_data->dmdctr }}');
                                            $wire.$set('empid', '{{ $extra_all->empid }}')" @endif>
                                                    {{ $extra_all_data->dm->drug_concat() }}
                                                </td>
                                                <td class="text-xs text-center">
                                                    @switch(strtoupper($extra_all_data->order_type ?? ''))
                                                        @case('G24')
                                                            <span class="badge badge-xs badge-error">G24 ({{ $extra_all_data->qty }})</span>
                                                        @break

                                                        @case('OR')
                                                            <span class="badge badge-xs badge-secondary">OR
                                                                ({{ $extra_all_data->qty }})
                                                            </span>
                                                        @break

                                                        @default
                                                            <span class="badge badge-xs badge-accent">{{ $extra_all_data->qty }}</span>
                                                    @endswitch
                                                </td>
                                                <td class="text-xs">{{ $extra_all_data->remark }}</td>
                                                <td class="text-xs">
                                                    {{ $extra_all->employee ? $extra_all->employee->fullname : '' }}</td>
                                                <td class="text-xs text-center">
                                                    <span
                                                        class="badge badge-xs {{ $extra_all_data->stat == 'A' ? 'badge-primary' : 'badge-error' }}">
                                                        {{ $extra_all_data->stat }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($extra_all_data->stat == 'A')
                                                        <button class="btn btn-xs btn-ghost btn-error tooltip tooltip-left"
                                                            data-tip="Deactivate"
                                                            wire:click="$wire.$set('rx_id', {{ $extra_all_data->id }});
                                                $wire.$set('rx_dmdcomb', '{{ $extra_all_data->dmdcomb }}');
                                                $wire.$set('rx_dmdctr', '{{ $extra_all_data->dmdctr }}');
                                                $wire.$set('empid', '{{ $extra_all->empid }}');
                                                $wire.$set('showDeactivateRxModal', true)">
                                                            <x-heroicon-o-x-mark class="w-3 h-3" />
                                                        </button>
                                                    @else
                                                        <button class="btn btn-xs btn-ghost btn-success tooltip tooltip-left"
                                                            data-tip="Reactivate"
                                                            wire:click="reactivate_rx({{ $extra_all_data->id }})">
                                                            <x-heroicon-o-check class="w-3 h-3" />
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            @endforelse
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                            <x-slot:actions>
                                <x-mary-button label="Close" @click="$wire.showPrescriptionListModal = false" />
                            </x-slot:actions>
                        </x-mary-modal>

                        {{-- Encounter / Prescription Selector Modal --}}
                        <x-mary-modal wire:model="showEncounterSelectorModal" title="Browse Encounters & Rx/Orders" class="backdrop-blur"
                            box-class="max-w-5xl">
                            <div class="space-y-4">
                                {{-- Mode Switcher --}}
                                <div class="flex items-center justify-between">
                                    <div class="flex gap-1 p-1 rounded-lg bg-base-200">
                                        <button class="btn btn-sm {{ $selector_mode === 'patient' ? 'btn-primary' : 'btn-ghost' }}"
                                            wire:click="switchSelectorMode('patient')">
                                            <x-heroicon-o-user class="w-4 h-4" /> Patient Encounters
                                        </button>
                                        <button class="btn btn-sm {{ $selector_mode === 'rx_orders' ? 'btn-primary' : 'btn-ghost' }}"
                                            wire:click="switchSelectorMode('rx_orders')">
                                            <x-heroicon-o-clipboard-document-list class="w-4 h-4" /> Rx/Orders
                                        </button>
                                    </div>
                                </div>

                                @if ($selector_mode === 'patient')
                                    {{-- ═══════════ PATIENT ENCOUNTERS MODE ═══════════ --}}
                                    {{-- Patient Search Bar --}}
                                    <div class="p-3 rounded-lg border border-base-300 bg-base-200/30">
                                        @if ($selector_selected_hpercode)
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10">
                                                        <x-heroicon-o-user class="w-4 h-4 text-primary" />
                                                    </div>
                                                    <div>
                                                        <div class="font-semibold text-sm">{{ $selector_patient_name }}</div>
                                                        <div class="text-xs text-base-content/60">{{ $selector_selected_hpercode }}</div>
                                                    </div>
                                                </div>
                                                <x-mary-button label="Change Patient" icon="o-arrows-right-left" class="btn-sm btn-ghost"
                                                    wire:click="selectorClearPatient" />
                                            </div>
                                        @else
                                            <div class="space-y-2">
                                                <div class="text-xs font-semibold text-base-content/70">Search Patient</div>
                                                <div class="flex gap-2">
                                                    <x-mary-input wire:model="selector_search_hpercode" placeholder="Hospital #"
                                                        class="input-sm flex-1" />
                                                    <x-mary-input wire:model="selector_search_lastname" placeholder="Last Name"
                                                        class="input-sm flex-1" />
                                                    <x-mary-input wire:model="selector_search_firstname" placeholder="First Name"
                                                        class="input-sm flex-1" />
                                                    <x-mary-button label="Search" icon="o-magnifying-glass" class="btn-sm btn-primary"
                                                        wire:click="selectorSearchPatients" spinner />
                                                </div>
                                                @if (count($selector_patient_results) > 0)
                                                    <div class="max-h-40 overflow-y-auto border rounded border-base-300 bg-base-100">
                                                        @foreach ($selector_patient_results as $selectorPat)
                                                            <div wire:key="sel-pat-{{ $selectorPat->hpercode }}"
                                                                class="flex items-center justify-between px-3 py-1.5 text-xs border-b border-base-200 cursor-pointer hover:bg-base-200/50"
                                                                wire:click="selectorSelectPatient('{{ $selectorPat->hpercode }}')">
                                                                <span class="font-medium">{{ $selectorPat->patlast }},
                                                                    {{ $selectorPat->patfirst }} {{ $selectorPat->patmiddle }}</span>
                                                                <span class="text-base-content/50">{{ $selectorPat->hpercode }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    @if ($selector_selected_hpercode)
                                        {{-- Area Filter Tabs --}}
                                        <div class="flex gap-2">
                                            @foreach (['all' => 'All', 'ward' => 'Ward (Admitted)', 'er' => 'ER', 'opd' => 'OPD'] as $filterKey => $filterLabel)
                                                <button
                                                    class="btn btn-sm {{ $encounter_area_filter === $filterKey ? 'btn-primary' : 'btn-ghost' }}"
                                                    wire:click="$set('encounter_area_filter', '{{ $filterKey }}')">
                                                    {{ $filterLabel }}
                                                </button>
                                            @endforeach
                                            <x-mary-button label="Walk-In" icon="o-arrow-right-circle" wire:click="initiateWalkIn"
                                                spinner="initiateWalkIn" class="btn-error btn-sm" />
                                        </div>

                                        <div class="grid grid-cols-5 gap-4 min-h-[400px]">
                                            {{-- Left: Encounter List --}}
                                            <div class="col-span-2 border rounded-lg border-base-300 overflow-hidden flex flex-col">
                                                <div
                                                    class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide bg-base-200 text-base-content/70 border-b border-base-300">
                                                    Encounters
                                                </div>
                                                <div class="flex-1 overflow-y-auto">
                                                    @forelse ($patient_encounters as $enc)
                                                        <div wire:key="enc-sel-{{ md5($enc->enccode) }}"
                                                            class="px-3 py-2 border-b border-base-200 cursor-pointer hover:bg-base-200/50 transition-colors {{ $selected_encounter_code === $enc->enccode ? 'bg-primary/10 border-l-4 border-l-primary' : '' }}"
                                                            wire:click="selectEncounterPrescriptions('{{ $enc->enccode }}')">
                                                            <div class="flex items-center justify-between">
                                                                <span
                                                                    class="badge badge-xs {{ match ($enc->toecode) {'ADM', 'OPDAD', 'ERADM' => 'badge-info','ER' => 'badge-error','OPD' => 'badge-success',default => 'badge-ghost'} }}">
                                                                    {{ $enc->toecode }}
                                                                </span>
                                                                <div class="flex gap-1">
                                                                    @if ($enc->active_rx_count > 0)
                                                                        <span
                                                                            class="badge badge-xs badge-primary">{{ $enc->active_rx_count }}
                                                                            Rx</span>
                                                                    @else
                                                                        <span class="badge badge-xs badge-ghost">0 Rx</span>
                                                                    @endif
                                                                    @if ($enc->order_count > 0)
                                                                        <span class="badge badge-xs badge-info">{{ $enc->order_count }}
                                                                            Ord</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="text-xs mt-1">
                                                                {{ date('M d, Y h:i A', strtotime($enc->encdate)) }}
                                                            </div>
                                                            @if ($enc->wardname)
                                                                <div class="text-xs text-base-content/60">{{ $enc->wardname }} -
                                                                    {{ $enc->rmname }}</div>
                                                            @endif
                                                            @if ($enc->diagtext)
                                                                <div class="text-xs text-base-content/50 truncate"
                                                                    title="{{ $enc->diagtext }}">Dx:
                                                                    {{ Illuminate\Support\Str::limit($enc->diagtext, 50) }}</div>
                                                            @endif
                                                            @if ($enc->billstat == '02' || $enc->billstat == '03')
                                                                <span class="badge badge-xs badge-error mt-1">Final Bill</span>
                                                            @endif
                                                        </div>
                                                    @empty
                                                        <div class="py-8 text-center text-base-content/50">
                                                            <x-heroicon-o-folder-open class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                                            No encounters found
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>

                                            {{-- Right: Prescriptions & Orders for Selected Encounter --}}
                                            <div class="col-span-3 border rounded-lg border-base-300 overflow-hidden flex flex-col">
                                                <div
                                                    class="px-3 py-1.5 bg-base-200 border-b border-base-300 flex items-center justify-between">
                                                    @if ($selected_encounter_code)
                                                        <div class="flex gap-1">
                                                            <button
                                                                class="btn btn-xs {{ $encounter_detail_tab === 'prescriptions' ? 'btn-primary' : 'btn-ghost' }}"
                                                                wire:click="$set('encounter_detail_tab', 'prescriptions')">
                                                                Prescriptions
                                                                <span
                                                                    class="badge badge-xs {{ $encounter_detail_tab === 'prescriptions' ? 'badge-primary-content' : 'badge-ghost' }}">{{ collect($selected_encounter_prescriptions)->sum(fn($p) => count($p->data_active ?? [])) }}</span>
                                                            </button>
                                                            <button
                                                                class="btn btn-xs {{ $encounter_detail_tab === 'orders' ? 'btn-primary' : 'btn-ghost' }}"
                                                                wire:click="$set('encounter_detail_tab', 'orders')">
                                                                Orders
                                                                <span
                                                                    class="badge badge-xs {{ $encounter_detail_tab === 'orders' ? 'badge-primary-content' : 'badge-ghost' }}">{{ count($selected_encounter_orders) }}</span>
                                                            </button>
                                                        </div>
                                                        <button class="btn btn-xs btn-accent tooltip tooltip-left"
                                                            data-tip="Open this Encounter"
                                                            wire:click="navigateToEncounter('{{ $selected_encounter_code }}')">
                                                            <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3" /> Go to Encounter
                                                        </button>
                                                    @else
                                                        <span class="text-xs font-semibold uppercase tracking-wide text-base-content/70">
                                                            Prescriptions & Orders
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="flex-1 overflow-y-auto">
                                                    @if ($selected_encounter_code)
                                                        @if ($encounter_detail_tab === 'prescriptions')
                                                            {{-- Prescriptions Tab --}}
                                                            <table class="table table-xs table-pin-rows">
                                                                <thead>
                                                                    <tr class="bg-base-200">
                                                                        <th>Drug / Medicine</th>
                                                                        <th class="text-center">Type</th>
                                                                        <th class="text-center">Qty</th>
                                                                        <th class="text-center">Status</th>
                                                                        <th class="w-10"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @php $hasActiveRx = false; @endphp
                                                                    @forelse ($selected_encounter_prescriptions as $selPresc)
                                                                        @forelse ($selPresc->data_active ?? [] as $selData)
                                                                            @php $hasActiveRx = true; @endphp
                                                                            <tr class="hover" wire:key="enc-rx-{{ $selData->id }}">
                                                                                <td class="text-xs font-medium max-w-[220px] truncate"
                                                                                    title="{{ $selData->dm->drug_concat() }}">
                                                                                    {{ $selData->dm->drug_concat() }}
                                                                                    @if ($selData->remark)
                                                                                        <br><span
                                                                                            class="text-base-content/50">{{ $selData->remark }}</span>
                                                                                    @endif
                                                                                </td>
                                                                                <td class="text-xs text-center">
                                                                                    @switch(strtoupper($selData->order_type ?? ''))
                                                                                        @case('G24')
                                                                                            <span class="badge badge-xs badge-error">G24</span>
                                                                                        @break

                                                                                        @case('OR')
                                                                                            <span class="badge badge-xs badge-secondary">OR</span>
                                                                                        @break

                                                                                        @default
                                                                                            <span class="badge badge-xs badge-accent">Basic</span>
                                                                                    @endswitch
                                                                                </td>
                                                                                <td class="text-xs text-center font-semibold">
                                                                                    {{ $selData->qty }}</td>
                                                                                <td class="text-xs text-center">
                                                                                    <span
                                                                                        class="badge badge-xs badge-primary">Active</span>
                                                                                </td>
                                                                                <td>
                                                                                    @if ($hasEncounter && $billstat != '02' && $billstat != '03')
                                                                                        <button
                                                                                            class="btn btn-xs btn-primary tooltip tooltip-left"
                                                                                            data-tip="Add to Current Encounter"
                                                                                            wire:click="addPrescriptionFromEncounter({{ $selData->id }}, '{{ $selData->dmdcomb }}', '{{ $selData->dmdctr }}', '{{ $selPresc->empid }}', '{{ $selData->qty }}')">
                                                                                            <x-heroicon-o-plus class="w-3 h-3" />
                                                                                        </button>
                                                                                    @endif
                                                                                </td>
                                                                            </tr>
                                                                            @empty
                                                                            @endforelse
                                                                            @empty
                                                                            @endforelse

                                                                            @if (!$hasActiveRx)
                                                                                <tr>
                                                                                    <td colspan="5"
                                                                                        class="text-center py-4 text-base-content/50">
                                                                                        No active prescriptions for this encounter
                                                                                    </td>
                                                                                </tr>
                                                                            @endif
                                                                        </tbody>
                                                                    </table>
                                                                @else
                                                                    {{-- Orders Tab --}}
                                                                    <table class="table table-xs table-pin-rows">
                                                                        <thead>
                                                                            <tr class="bg-base-200">
                                                                                <th class="text-center">Status</th>
                                                                                <th>Drug / Medicine</th>
                                                                                <th class="text-center">Qty</th>
                                                                                <th class="text-right">Amount</th>
                                                                                <th>Remarks</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @forelse ($selected_encounter_orders as $selOrder)
                                                                                <tr class="hover"
                                                                                    wire:key="enc-ord-{{ $selOrder->docointkey }}">
                                                                                    <td class="text-xs text-center">
                                                                                        @if ($selOrder->pcchrgcod)
                                                                                            <span
                                                                                                class="text-[10px] text-primary">{{ $selOrder->pcchrgcod }}</span><br>
                                                                                        @endif
                                                                                        @if ($selOrder->estatus == 'U' && !$selOrder->pcchrgcod)
                                                                                            <span
                                                                                                class="badge badge-xs badge-warning">Pending</span>
                                                                                        @elseif ($selOrder->estatus == 'P' && $selOrder->pcchrgcod)
                                                                                            <span class="badge badge-xs badge-info">Charged</span>
                                                                                        @elseif ($selOrder->estatus == 'S')
                                                                                            <span
                                                                                                class="badge badge-xs badge-success">Issued</span>
                                                                                            @if ($selOrder->tx_type)
                                                                                                <span
                                                                                                    class="badge badge-xs badge-ghost">{{ strtoupper($selOrder->tx_type) }}</span>
                                                                                            @endif
                                                                                        @endif
                                                                                    </td>
                                                                                    <td class="text-xs font-medium max-w-[200px] truncate"
                                                                                        title="{{ $selOrder->drug_concat }}">
                                                                                        {{ $selOrder->drug_concat }}
                                                                                        @if ($selOrder->prescription_data_id)
                                                                                            <x-heroicon-s-document-check
                                                                                                class="inline w-3 h-3 text-primary" />
                                                                                        @endif
                                                                                        <br><span
                                                                                            class="badge badge-xs badge-ghost">{{ $selOrder->chrgdesc }}</span>
                                                                                    </td>
                                                                                    <td class="text-xs text-center font-semibold">
                                                                                        @if ($selOrder->estatus == 'S')
                                                                                            {{ number_format($selOrder->qtyissued, 0) }}
                                                                                        @else
                                                                                            {{ number_format($selOrder->pchrgqty, 0) }}
                                                                                        @endif
                                                                                    </td>
                                                                                    <td class="text-xs text-right">
                                                                                        {{ number_format($selOrder->pcchrgamt, 2) }}</td>
                                                                                    <td class="text-xs max-w-[100px] truncate"
                                                                                        title="{{ $selOrder->remarks }}">
                                                                                        {{ $selOrder->remarks }}</td>
                                                                                </tr>
                                                                            @empty
                                                                                <tr>
                                                                                    <td colspan="5"
                                                                                        class="text-center py-4 text-base-content/50">
                                                                                        No orders found for this encounter
                                                                                    </td>
                                                                                </tr>
                                                                            @endforelse
                                                                        </tbody>
                                                                    </table>
                                                                @endif
                                                            @else
                                                                <div class="py-8 text-center text-base-content/50">
                                                                    <x-heroicon-o-arrow-left class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                                                    Select an encounter to view its prescriptions and orders
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <div
                                                    class="py-12 text-center text-base-content/50 min-h-[300px] flex flex-col items-center justify-center">
                                                    <x-heroicon-o-magnifying-glass class="w-10 h-10 mx-auto mb-3 opacity-30" />
                                                    <p class="text-sm">Search for a patient above to view their encounters and prescriptions</p>
                                                </div>
                                            @endif
                                        @else
                                            {{-- ═══════════ RX/ORDERS BROWSING MODE ═══════════ --}}

                                            {{-- Area Tabs --}}
                                            <div class="flex justify-center gap-2">
                                                <button class="btn btn-sm {{ $rx_browse_area === 'ward' ? 'btn-primary' : 'btn-ghost' }}"
                                                    wire:click="setRxBrowseArea('ward')">
                                                    <x-heroicon-o-building-office-2 class="w-4 h-4" /> Wards
                                                </button>
                                                <button class="btn btn-sm {{ $rx_browse_area === 'opd' ? 'btn-primary' : 'btn-ghost' }}"
                                                    wire:click="setRxBrowseArea('opd')">
                                                    <x-heroicon-o-user-group class="w-4 h-4" /> Out Patient Department
                                                </button>
                                                <button class="btn btn-sm {{ $rx_browse_area === 'er' ? 'btn-primary' : 'btn-ghost' }}"
                                                    wire:click="setRxBrowseArea('er')">
                                                    <x-heroicon-o-heart class="w-4 h-4" /> Emergency Room
                                                </button>
                                            </div>

                                            {{-- Filters Row --}}
                                            <div class="grid grid-cols-3 gap-3 p-3 rounded-lg border border-base-300 bg-base-200/30">
                                                {{-- Rx Tag Filter --}}
                                                <div>
                                                    <div class="text-xs font-semibold text-base-content/70 mb-1">Rx Tag</div>
                                                    <div class="flex gap-1 flex-wrap">
                                                        <button wire:click="setRxBrowseTagFilter('all')"
                                                            class="btn btn-xs {{ $rx_browse_tag_filter === 'all' ? 'btn-primary' : 'btn-ghost' }}">All</button>
                                                        <button wire:click="setRxBrowseTagFilter('basic')"
                                                            class="btn btn-xs {{ $rx_browse_tag_filter === 'basic' ? 'btn-accent' : 'btn-ghost' }}">BASIC</button>
                                                        <button wire:click="setRxBrowseTagFilter('g24')"
                                                            class="btn btn-xs {{ $rx_browse_tag_filter === 'g24' ? 'btn-error' : 'btn-ghost' }}">G24</button>
                                                        <button wire:click="setRxBrowseTagFilter('or')"
                                                            class="btn btn-xs {{ $rx_browse_tag_filter === 'or' ? 'btn-secondary' : 'btn-ghost' }}">OR</button>
                                                    </div>
                                                </div>
                                                {{-- Patient Search --}}
                                                <div>
                                                    <div class="text-xs font-semibold text-base-content/70 mb-1">Patient</div>
                                                    <x-mary-input wire:model.live.debounce.300ms="rx_browse_search" icon="o-magnifying-glass"
                                                        placeholder="Search patient..." class="input-sm" />
                                                </div>
                                                {{-- Date / Ward Filter --}}
                                                <div>
                                                    @if ($rx_browse_area === 'ward')
                                                        <div class="text-xs font-semibold text-base-content/70 mb-1">Ward</div>
                                                        <select wire:model.live="rx_browse_wardcode"
                                                            class="select select-sm select-bordered w-full">
                                                            <option value="">All Wards</option>
                                                            @foreach (\App\Models\Hospital\Ward::where('wardstat', 'A')->orderBy('wardname')->get() as $ward)
                                                                <option value="{{ $ward->wardcode }}">{{ $ward->wardname }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <div class="text-xs font-semibold text-base-content/70 mb-1">Date</div>
                                                        <x-mary-input type="date" wire:model.live="rx_browse_date" :max="date('Y-m-d')"
                                                            class="input-sm" />
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Loading State --}}
                                            <div wire:loading wire:target="setRxBrowseArea,loadRxBrowseResults,rx_browse_date,rx_browse_wardcode"
                                                class="flex items-center gap-2 p-4">
                                                <span class="loading loading-spinner loading-sm"></span>
                                                <span class="text-sm">Loading prescriptions...</span>
                                            </div>

                                            {{-- Results Table --}}
                                            <div wire:loading.remove
                                                wire:target="setRxBrowseArea,loadRxBrowseResults,rx_browse_date,rx_browse_wardcode"
                                                class="overflow-y-auto max-h-[400px] border rounded-lg border-base-300">
                                                <table class="table table-xs table-pin-rows table-zebra">
                                                    <thead>
                                                        <tr class="bg-base-200">
                                                            <th class="w-8">#</th>
                                                            <th>Date</th>
                                                            <th>Patient Name</th>
                                                            <th>Department</th>
                                                            <th>Classification</th>
                                                            <th>Rx Tag</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php $rxRowNum = 0; @endphp
                                                        @forelse ($rx_browse_results as $rx)
                                                            @php
                                                                // Rx Tag filter
                                                                if ($rx_browse_tag_filter === 'basic') {
                                                                    $showRow = $rx->basic > 0;
                                                                } elseif ($rx_browse_tag_filter === 'g24') {
                                                                    $showRow = $rx->g24 > 0;
                                                                } elseif ($rx_browse_tag_filter === 'or') {
                                                                    $showRow = $rx->or > 0;
                                                                } else {
                                                                    $showRow = true;
                                                                }

                                                                // Search filter
                                                                if ($rx_browse_search && $showRow) {
                                                                    $s = strtolower($rx_browse_search);
                                                                    $name = strtolower(
                                                                        $rx->patlast .
                                                                            ' ' .
                                                                            $rx->patfirst .
                                                                            ' ' .
                                                                            $rx->patmiddle,
                                                                    );
                                                                    $showRow =
                                                                        str_contains($name, $s) ||
                                                                        str_contains(strtolower($rx->hpercode), $s);
                                                                }
                                                            @endphp

                                                            @if ($showRow)
                                                                @php $rxRowNum++; @endphp
                                                                <tr wire:key="rxb-{{ md5($rx->enccode) }}"
                                                                    wire:click="rxBrowseSelectEncounter('{{ $rx->enccode }}')"
                                                                    class="cursor-pointer hover">
                                                                    <td class="text-xs">{{ $rxRowNum }}</td>
                                                                    <td class="text-xs whitespace-nowrap">
                                                                        @if ($rx_browse_area === 'opd')
                                                                            {{ \Carbon\Carbon::parse($rx->opddate)->format('Y/m/d') }}
                                                                            <br><span
                                                                                class="text-base-content/50">{{ \Carbon\Carbon::parse($rx->opdtime)->format('g:i A') }}</span>
                                                                        @elseif ($rx_browse_area === 'ward')
                                                                            {{ $rx->admdate ? \Carbon\Carbon::parse($rx->admdate)->format('Y/m/d') : '---' }}
                                                                        @elseif ($rx_browse_area === 'er')
                                                                            {{ \Carbon\Carbon::parse($rx->erdate)->format('Y/m/d') }}
                                                                            <br><span
                                                                                class="text-base-content/50">{{ \Carbon\Carbon::parse($rx->ertime)->format('g:i A') }}</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="whitespace-nowrap">
                                                                        <div class="text-xs font-medium">
                                                                            {{ strtoupper($rx->patlast) }}, {{ strtoupper($rx->patfirst) }}
                                                                            {{ strtoupper($rx->patsuffix ?? '') }}
                                                                            {{ strtoupper($rx->patmiddle) }}
                                                                        </div>
                                                                        <span class="badge badge-ghost badge-xs">{{ $rx->hpercode }}</span>
                                                                    </td>
                                                                    <td class="text-xs whitespace-nowrap">
                                                                        @if ($rx_browse_area === 'opd')
                                                                            Out Patient Department
                                                                            @if ($rx->tsdesc)
                                                                                <br><span class="text-base-content/50">{{ $rx->tsdesc }}</span>
                                                                            @endif
                                                                        @elseif ($rx_browse_area === 'ward')
                                                                            {{ $rx->wardname ?? '---' }}
                                                                            @if ($rx->rmname)
                                                                                <br><span class="text-base-content/50">{{ $rx->rmname }}</span>
                                                                            @endif
                                                                        @elseif ($rx_browse_area === 'er')
                                                                            Emergency Room
                                                                            @if ($rx->tsdesc)
                                                                                <br><span class="text-base-content/50">{{ $rx->tsdesc }}</span>
                                                                            @endif
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        @php
                                                                            $class = match ($rx->mssikey) {
                                                                                'MSSA11111999', 'MSSB11111999' => 'Pay',
                                                                                'MSSC111111999' => 'PP1',
                                                                                'MSSC211111999' => 'PP2',
                                                                                'MSSC311111999' => 'PP3',
                                                                                'MSSD11111999' => 'Indigent',
                                                                                default => $rx->mssikey
                                                                                    ? '---'
                                                                                    : 'Indigent',
                                                                            };
                                                                            $badgeClass = match ($class) {
                                                                                'Pay' => 'badge-success',
                                                                                'PP1', 'PP2', 'PP3' => 'badge-warning',
                                                                                'Indigent' => 'badge-ghost',
                                                                                default => 'badge-info',
                                                                            };
                                                                        @endphp
                                                                        <span
                                                                            class="badge {{ $badgeClass }} badge-xs">{{ $class }}</span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="flex gap-1">
                                                                            @if ($rx->basic)
                                                                                <span class="badge badge-accent badge-xs tooltip"
                                                                                    data-tip="BASIC">{{ $rx->basic }}</span>
                                                                            @endif
                                                                            @if ($rx->g24)
                                                                                <span class="badge badge-error badge-xs tooltip"
                                                                                    data-tip="Good For 24 Hrs">{{ $rx->g24 }}</span>
                                                                            @endif
                                                                            @if ($rx->or)
                                                                                <span class="badge badge-secondary badge-xs tooltip"
                                                                                    data-tip="For Operating Use">{{ $rx->or }}</span>
                                                                            @endif
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                        @empty
                                                            <tr>
                                                                <td colspan="6" class="text-center py-8 text-base-content/50">
                                                                    <x-heroicon-o-clipboard-document-list
                                                                        class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                                                    No prescriptions found
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif {{-- end selector_mode --}}
                                    </div>
                                    <x-slot:actions>
                                        <x-mary-button label="Close" @click="$wire.showEncounterSelectorModal = false" />
                                    </x-slot:actions>
                                </x-mary-modal>

                                {{-- Inline Remarks Edit --}}
                                @if ($selected_remarks)
                                    <x-mary-modal wire:model="selected_remarks" title="Edit Remarks" class="backdrop-blur">
                                        <x-mary-input label="Remarks" wire:model="new_remarks" placeholder="Enter remarks..." />
                                        <x-slot:actions>
                                            <x-mary-button label="Cancel" wire:click="$wire.$set('selected_remarks', null)" />
                                            <x-mary-button label="Save" icon="o-check" class="btn-primary" wire:click="update_remarks"
                                                spinner />
                                        </x-slot:actions>
                                    </x-mary-modal>
                                @endif

                                {{-- Keyboard Shortcuts --}}
                                @script
                                    <script>
                                        document.addEventListener('keydown', e => {
                                            // Skip if user is typing in an input/textarea/select
                                            const tag = e.target.tagName;
                                            const isInput = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';

                                            // Ctrl+C - Charge items
                                            if (e.ctrlKey && e.key === 'c' && !e.shiftKey) {
                                                e.preventDefault();
                                                $wire.charge_items();
                                            }
                                            // Ctrl+I - Open Issue modal
                                            if (e.ctrlKey && e.key === 'i') {
                                                e.preventDefault();
                                                $wire.set('showIssueModal', true);
                                            }
                                            // Ctrl+A - Select all pending (only outside inputs)
                                            if (e.ctrlKey && e.key === 'a' && !isInput) {
                                                e.preventDefault();
                                                document.querySelectorAll('.pending-checkbox').forEach(cb => {
                                                    const val = cb.value;
                                                    const items = $wire.get('selected_items') || [];
                                                    if (!items.includes(val)) items.push(val);
                                                    $wire.set('selected_items', items);
                                                });
                                                // Trigger Alpine selectAllPending
                                                const el = document.querySelector('[x-data]');
                                                if (el && el.__x) el.__x.$data.selectAllPending();
                                            }
                                            // Escape - Clear selection (only outside inputs)
                                            if (e.key === 'Escape' && !isInput) {
                                                const el = document.querySelector('[x-data]');
                                                if (el && el.__x) el.__x.$data.clearSelection();
                                            }
                                            // Delete - Delete selected pending items
                                            if (e.key === 'Delete' && !isInput) {
                                                e.preventDefault();
                                                $wire.delete_item();
                                            }
                                            // F2 - Change patient
                                            if (e.key === 'F2') {
                                                e.preventDefault();
                                                $wire.openChangePatient();
                                            }
                                            // F3 - Browse encounters
                                            if (e.key === 'F3') {
                                                e.preventDefault();
                                                $wire.openEncounterSelector();
                                            }
                                            // F4 - Prescriptions list
                                            if (e.key === 'F4') {
                                                e.preventDefault();
                                                $wire.set('showPrescriptionListModal', true);
                                            }
                                            // F5 - Summary
                                            if (e.key === 'F5') {
                                                e.preventDefault();
                                                $wire.set('showSummaryModal', true);
                                            }
                                            // F6 - Open Issued with Return in new tab
                                            if (e.key === 'F6') {
                                                e.preventDefault();
                                                const hpercode = '{{ $hpercode ?? '' }}';
                                                if (hpercode) {
                                                    window.open('{{ url('/dispensing/return-slip') }}' + '/' + hpercode, '_blank');
                                                }
                                            }
                                        });

                                        $wire.on('open-charge-slip', ({
                                            pcchrgcod
                                        }) => {
                                            window.open('{{ url('/dispensing/encounter/charge') }}' + '/' + pcchrgcod, '_blank');
                                        });
                                    </script>
                                @endscript
                            </div>
