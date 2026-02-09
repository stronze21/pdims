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
    {{-- Patient Info Bar --}}
    <div class="border-b bg-base-100 border-base-200">
        <div class="px-4 py-2">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-full bg-primary/10">
                        <x-heroicon-o-user class="w-6 h-6 text-primary" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold">{{ $patlast }}, {{ $patfirst }} {{ $patmiddle }}</h2>
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
                                <span>|</span>
                                <span>Dx: {{ \Illuminate\Support\Str::limit($diagtext, 50) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if ($billstat == '02' || $billstat == '03')
                        <div class="badge badge-error gap-1">
                            <x-heroicon-o-lock-closed class="w-3 h-3" /> FINAL BILL
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Action Bar --}}
    <div class="border-b bg-base-200/50 border-base-200">
        <div class="flex items-center justify-between px-4 py-2">
            <div class="flex gap-2">
                <x-mary-button label="Prescriptions" icon="o-clipboard-document-list"
                    class="btn-sm btn-outline" wire:click="$set('showPrescriptionListModal', true)" />
                <x-mary-button label="Summary" icon="o-document-text"
                    class="btn-sm btn-outline" wire:click="$set('showSummaryModal', true)" />
                <a href="{{ route('dispensing.rxo.return.sum', $hpercode) }}" target="_blank" class="btn btn-sm btn-outline">
                    <x-heroicon-o-arrow-uturn-left class="w-4 h-4" /> Issued with Return
                </a>
            </div>

            @if ($billstat != '02' && $billstat != '03')
                <div class="flex gap-2">
                    <x-mary-button label="Select All Pending" icon="o-check-circle"
                        class="btn-sm btn-ghost" x-on:click="selectAllPending()" />
                    <x-mary-button label="Clear" icon="o-x-circle"
                        class="btn-sm btn-ghost" x-on:click="clearSelection()" />

                    <div class="border-l border-base-300 h-6 mx-1"></div>

                    <x-mary-button label="Delete" icon="o-trash"
                        class="btn-sm btn-error btn-outline"
                        wire:click="delete_item" wire:confirm="Delete selected pending items? This cannot be undone." />
                    <x-mary-button label="Charge" icon="o-credit-card"
                        class="btn-sm btn-info btn-outline"
                        wire:click="charge_items" />
                    <x-mary-button label="Issue" icon="o-paper-airplane"
                        class="btn-sm btn-success"
                        wire:click="$set('showIssueModal', true)" />
                </div>
            @endif
        </div>
    </div>

    {{-- Main Content --}}
    <div class="flex flex-1 overflow-hidden">
        {{-- Left: Orders Table --}}
        <div class="flex flex-col flex-1 overflow-hidden border-r border-base-200">
            <div class="flex-1 overflow-y-auto">
                <table class="table table-xs table-pin-rows">
                    <thead>
                        <tr class="bg-base-200">
                            <th class="w-8"></th>
                            <th>Drug / Medicine</th>
                            <th class="text-center">Fund</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Charge Slip</th>
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
                                <td class="text-xs font-medium max-w-xs truncate" title="{{ $rxo->drug_concat }}">
                                    {{ $rxo->drug_concat }}
                                    @if ($rxo->prescription_data_id)
                                        <x-heroicon-s-document-check class="inline w-3 h-3 text-primary" />
                                    @endif
                                </td>
                                <td class="text-xs text-center">
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
                                <td class="text-xs text-right font-semibold">{{ number_format($rxo->pcchrgamt, 2) }}</td>
                                <td class="text-center">
                                    @if ($rxo->estatus == 'U' && !$rxo->pcchrgcod)
                                        <span class="badge badge-xs badge-warning">Pending</span>
                                    @elseif ($rxo->estatus == 'P' && $rxo->pcchrgcod)
                                        <span class="badge badge-xs badge-info">Charged</span>
                                    @elseif ($rxo->estatus == 'S')
                                        <span class="badge badge-xs badge-success">Issued</span>
                                        @if ($rxo->tx_type)
                                            <span class="badge badge-xs badge-ghost">{{ strtoupper($rxo->tx_type) }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-xs text-center">
                                    @if ($rxo->pcchrgcod)
                                        <a href="{{ route('dispensing.rxo.chargeslip', $rxo->pcchrgcod) }}"
                                            target="_blank" class="link link-primary text-xs">
                                            {{ $rxo->pcchrgcod }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-xs max-w-[120px] truncate" title="{{ $rxo->remarks }}">{{ $rxo->remarks }}</td>
                                <td>
                                    <div class="dropdown dropdown-end">
                                        <label tabindex="0" class="btn btn-ghost btn-xs">
                                            <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                                        </label>
                                        <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-50 w-44 p-2 shadow-lg border border-base-200">
                                            @if ($rxo->estatus == 'U' && !$rxo->pcchrgcod)
                                                <li>
                                                    <a wire:click="$set('docointkey', '{{ $rxo->docointkey }}'); $set('order_qty', {{ $rxo->pchrgqty }}); $set('unit_price', {{ $rxo->pchrgup }}); $set('showUpdateQtyModal', true)">
                                                        <x-heroicon-o-pencil-square class="w-4 h-4" /> Edit Qty
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($rxo->estatus == 'S' && $rxo->qtyissued > 0)
                                                <li>
                                                    <a wire:click="$set('docointkey', '{{ $rxo->docointkey }}'); $set('unit_price', {{ $rxo->pchrgup }}); $set('showReturnModal', true)">
                                                        <x-heroicon-o-arrow-uturn-left class="w-4 h-4" /> Return
                                                    </a>
                                                </li>
                                            @endif
                                            <li>
                                                <a wire:click="$set('selected_remarks', '{{ $rxo->docointkey }}'); $set('new_remarks', '{{ addslashes($rxo->remarks) }}')">
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
        <div class="flex flex-col w-[420px] overflow-hidden bg-base-100"
            x-data="{ tab: 'stocks', stockCount: @entangle('stocksDisplayCount') }">

            {{-- Tab Switcher --}}
            <div class="flex border-b border-base-200">
                <button class="flex-1 py-2 text-sm font-medium text-center transition-colors"
                    :class="tab === 'stocks' ? 'border-b-2 border-primary text-primary' : 'text-base-content/60 hover:text-base-content'"
                    x-on:click="tab = 'stocks'">
                    Stocks
                </button>
                <button class="flex-1 py-2 text-sm font-medium text-center transition-colors"
                    :class="tab === 'rx' ? 'border-b-2 border-primary text-primary' : 'text-base-content/60 hover:text-base-content'"
                    x-on:click="tab = 'rx'">
                    Prescriptions
                    @if (count($active_prescription) > 0)
                        <span class="badge badge-xs badge-primary ml-1">{{ count($active_prescription) }}</span>
                    @endif
                </button>
            </div>

            {{-- Search & Filter --}}
            <div class="p-2 space-y-2 border-b border-base-200">
                <x-mary-input wire:model.live.debounce.300ms="generic" icon="o-magnifying-glass"
                    placeholder="Search drug name..." class="input-sm" clearable />
                <x-mary-choices wire:model.live="charge_code_filter" :options="$charges"
                    option-value="chrgcode" option-label="chrgdesc"
                    placeholder="Filter by fund source..." class="select-sm"
                    multiple />
            </div>

            {{-- Stocks Tab --}}
            <div class="flex-1 overflow-y-auto" x-show="tab === 'stocks'"
                x-on:scroll.debounce.150ms="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 100) { $wire.loadMoreStocks() }">
                <table class="table table-xs table-pin-rows">
                    <thead>
                        <tr class="bg-base-200">
                            <th>Drug / Medicine</th>
                            <th class="text-center">Bal</th>
                            <th class="text-right">Price</th>
                            <th class="text-center">Exp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stocks as $stock)
                            <tr class="cursor-pointer hover"
                                wire:key="stock-{{ $stock->id }}-{{ $stock->chrgcode }}"
                                @if ($billstat != '02' && $billstat != '03')
                                    wire:click="$set('item_id', '{{ $stock->id }}');
                                        $set('item_chrgcode', '{{ $stock->chrgcode }}');
                                        $set('item_dmdcomb', '{{ $stock->dmdcomb }}');
                                        $set('item_dmdctr', '{{ $stock->dmdctr }}');
                                        $set('item_loc_code', '{{ $stock->loc_code }}');
                                        $set('item_dmdprdte', '{{ $stock->dmdprdte }}');
                                        $set('item_exp_date', '{{ $stock->exp_date }}');
                                        $set('item_stock_bal', '{{ $stock->stock_bal }}');
                                        $set('unit_price', '{{ $stock->dmselprice }}');
                                        $set('showAddItemModal', true)"
                                @endif>
                                <td class="text-xs">
                                    <div class="font-medium truncate max-w-[180px]" title="{{ $stock->drug_concat }}">
                                        {{ $stock->drug_concat }}
                                    </div>
                                    <span class="badge badge-xs badge-ghost">{{ $stock->chrgdesc }}</span>
                                </td>
                                <td class="text-xs text-center font-semibold">
                                    {{ number_format($stock->stock_bal, 0) }}
                                </td>
                                <td class="text-xs text-right">{{ number_format($stock->dmselprice, 2) }}</td>
                                <td class="text-xs text-center">
                                    @if ($stock->days_to_expiry <= 90)
                                        <span class="badge badge-xs badge-error">{{ date('m/y', strtotime($stock->exp_date)) }}</span>
                                    @elseif ($stock->days_to_expiry <= 180)
                                        <span class="badge badge-xs badge-warning">{{ date('m/y', strtotime($stock->exp_date)) }}</span>
                                    @else
                                        <span class="text-base-content/50">{{ date('m/y', strtotime($stock->exp_date)) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-base-content/50">No stocks found</td>
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

            {{-- Prescriptions Tab --}}
            <div class="flex-1 overflow-y-auto" x-show="tab === 'rx'" x-cloak>
                @forelse ($active_prescription as $presc)
                    @forelse ($presc->data_active ?? [] as $presc_data)
                        <div class="flex items-center gap-2 px-3 py-2 border-b border-base-200 hover:bg-base-200/50"
                            wire:key="rx-{{ $presc_data->id }}">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium truncate" title="{{ $presc_data->dm->drug_concat() }}">
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
                                    <button class="btn btn-xs btn-primary"
                                        wire:click="$set('rx_id', {{ $presc_data->id }});
                                            $set('rx_dmdcomb', '{{ $presc_data->dmdcomb }}');
                                            $set('rx_dmdctr', '{{ $presc_data->dmdctr }}');
                                            $set('empid', '{{ $presc->empid }}');
                                            $set('order_qty', '{{ $presc_data->qty }}');
                                            $set('showPrescribedItemModal', true)">
                                        <x-heroicon-o-plus class="w-3 h-3" />
                                    </button>
                                @else
                                    <button class="btn btn-xs btn-ghost"
                                        wire:click="$set('rx_id', {{ $presc_data->id }});
                                            $set('generic', '{{ explode(',', $presc_data->dm->drug_concat())[0] }}');
                                            $set('rx_dmdcomb', '{{ $presc_data->dmdcomb }}');
                                            $set('rx_dmdctr', '{{ $presc_data->dmdctr }}');
                                            $set('empid', '{{ $presc->empid }}')">
                                        <x-heroicon-o-magnifying-glass class="w-3 h-3" />
                                    </button>
                                @endif
                                <button class="btn btn-xs btn-ghost btn-error"
                                    wire:click="$set('rx_id', {{ $presc_data->id }});
                                        $set('showDeactivateRxModal', true)">
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
                                    <div class="text-xs font-medium truncate">{{ $extra_data->dm->drug_concat() }}</div>
                                    <div class="flex gap-2 text-xs text-base-content/60">
                                        <span>Qty: {{ $extra_data->qty }}</span>
                                    </div>
                                </div>
                                @if ($billstat != '02' && $billstat != '03')
                                    <button class="btn btn-xs btn-ghost"
                                        wire:click="$set('rx_id', {{ $extra_data->id }});
                                            $set('generic', '{{ explode(',', $extra_data->dm->drug_concat())[0] }}');
                                            $set('rx_dmdcomb', '{{ $extra_data->dmdcomb }}');
                                            $set('rx_dmdctr', '{{ $extra_data->dmdctr }}');
                                            $set('empid', '{{ $extra->empid }}')">
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

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- MODALS --}}
    {{-- ═══════════════════════════════════════════════ --}}

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
                <textarea wire:model="remarks" class="w-full textarea textarea-bordered" rows="2" placeholder="Optional remarks..."></textarea>
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
                    <select wire:model="rx_charge_code" class="w-full select select-bordered">
                        <option value="">Select fund source...</option>
                        @foreach ($charges as $charge)
                            <option value="{{ $charge->chrgcode }}">{{ $charge->chrgdesc }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label"><span class="label-text">Remarks</span></label>
                    <textarea wire:model="remarks" class="w-full textarea textarea-bordered" rows="2" placeholder="Optional remarks..."></textarea>
                </div>
            </div>
        </div>
        <x-slot:actions>
            <x-mary-button label="Cancel" @click="$wire.showPrescribedItemModal = false" />
            <x-mary-button label="Add Item" icon="o-plus" class="btn-primary" wire:click="add_prescribed_item" spinner />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Update Qty Modal --}}
    <x-mary-modal wire:model="showUpdateQtyModal" title="Update Order Quantity" class="backdrop-blur">
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-mary-input label="Quantity" wire:model="order_qty" type="number" min="1"
                    class="input-lg text-4xl text-center font-bold" autofocus />
                <div class="space-y-2">
                    <x-mary-input label="Unit Price" wire:model="unit_price" type="number" step="0.01" readonly />
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
                Total Return: <strong class="text-error">{{ number_format(($return_qty ?? 0) * ($unit_price ?? 0), 2) }}</strong>
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
            <x-mary-button label="Deactivate" icon="o-x-mark" class="btn-error" wire:click="deactivate_rx" spinner />
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
                        @foreach ([
                            'pay' => 'Pay',
                            'ems' => 'EMS',
                            'konsulta' => 'Konsulta',
                            'wholesale' => 'Wholesale',
                            'caf' => 'CAF',
                            'maip' => 'MAIP',
                            'is_ris' => 'RIS',
                            'pcso' => 'PCSO',
                            'phic' => 'PHIC',
                        ] as $key => $label)
                            <label class="flex items-center gap-2 p-2 rounded-lg border border-base-300 cursor-pointer hover:bg-base-200 transition-colors"
                                :class="{ 'border-primary bg-primary/5': $wire.{{ $key }} }">
                                <input type="radio" name="issue_tag" class="radio radio-sm radio-primary"
                                    wire:click="$set('{{ $key }}', true); @foreach(array_diff(array_keys(['ems' => 1, 'maip' => 1, 'wholesale' => 1, 'caf' => 1, 'konsulta' => 1, 'pcso' => 1, 'phic' => 1, 'is_ris' => 1, 'pay' => 1]), [$key]) as $other) $set('{{ $other }}', false); @endforeach"
                                    @checked($$key ?? false) />
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
            <x-mary-button label="Confirm Issue" icon="o-paper-airplane" class="btn-success" wire:click="issue_order" spinner />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Summary Modal --}}
    <x-mary-modal wire:model="showSummaryModal" title="Summary of Issued Drugs and Meds" class="backdrop-blur" box-class="max-w-3xl">
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
    <x-mary-modal wire:model="showPrescriptionListModal" title="All Prescriptions" class="backdrop-blur" box-class="max-w-4xl">
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
                                @if ($presc_all_data->stat == 'A')
                                    @if ($toecode == 'OPD' || $toecode == 'WALKN')
                                        wire:click="$set('rx_id', {{ $presc_all_data->id }});
                                            $set('rx_dmdcomb', '{{ $presc_all_data->dmdcomb }}');
                                            $set('rx_dmdctr', '{{ $presc_all_data->dmdctr }}');
                                            $set('empid', '{{ $presc_all->empid }}');
                                            $set('order_qty', '{{ $presc_all_data->qty }}');
                                            $set('showPrescribedItemModal', true)"
                                    @else
                                        wire:click="$set('rx_id', {{ $presc_all_data->id }});
                                            $set('generic', '{{ explode(',', $presc_all_data->dm->drug_concat())[0] }}');
                                            $set('rx_dmdcomb', '{{ $presc_all_data->dmdcomb }}');
                                            $set('rx_dmdctr', '{{ $presc_all_data->dmdctr }}');
                                            $set('empid', '{{ $presc_all->empid }}')"
                                    @endif
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
                            <td class="text-xs">{{ $presc_all_data->employee ? $presc_all_data->employee->fullname() : '' }}</td>
                            <td class="text-xs text-center">
                                @if ($presc_all_data->stat == 'A')
                                    <span class="badge badge-xs badge-primary">A</span>
                                @else
                                    <span class="badge badge-xs badge-error">{{ $presc_all_data->stat }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($presc_all_data->stat == 'A')
                                    <button class="btn btn-xs btn-ghost btn-error"
                                        wire:click="$set('rx_id', {{ $presc_all_data->id }});
                                            $set('rx_dmdcomb', '{{ $presc_all_data->dmdcomb }}');
                                            $set('rx_dmdctr', '{{ $presc_all_data->dmdctr }}');
                                            $set('empid', '{{ $presc_all->empid }}');
                                            $set('showDeactivateRxModal', true)">
                                        <x-heroicon-o-x-mark class="w-3 h-3" />
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
                    <tr><td colspan="7" class="font-semibold bg-base-200">Previous Encounter</td></tr>
                    @foreach ($extra_prescriptions_all as $extra_all)
                        @forelse ($extra_all->data ?? [] as $extra_all_data)
                            <tr class="hover opacity-75" wire:key="rx-extra-all-{{ $extra_all_data->id }}">
                                <td class="text-xs">
                                    {{ date('Y-m-d h:i A', strtotime($extra_all_data->updated_at)) }}
                                </td>
                                <td class="text-xs cursor-pointer"
                                    @if ($extra_all_data->stat == 'A')
                                        wire:click="$set('rx_id', {{ $extra_all_data->id }});
                                            $set('generic', '{{ explode(',', $extra_all_data->dm->drug_concat())[0] }}');
                                            $set('rx_dmdcomb', '{{ $extra_all_data->dmdcomb }}');
                                            $set('rx_dmdctr', '{{ $extra_all_data->dmdctr }}');
                                            $set('empid', '{{ $extra_all->empid }}')"
                                    @endif>
                                    {{ $extra_all_data->dm->drug_concat() }}
                                </td>
                                <td class="text-xs text-center">
                                    @switch(strtoupper($extra_all_data->order_type ?? ''))
                                        @case('G24')
                                            <span class="badge badge-xs badge-error">G24 ({{ $extra_all_data->qty }})</span>
                                            @break
                                        @case('OR')
                                            <span class="badge badge-xs badge-secondary">OR ({{ $extra_all_data->qty }})</span>
                                            @break
                                        @default
                                            <span class="badge badge-xs badge-accent">{{ $extra_all_data->qty }}</span>
                                    @endswitch
                                </td>
                                <td class="text-xs">{{ $extra_all_data->remark }}</td>
                                <td class="text-xs">{{ $extra_all->employee ? $extra_all->employee->fullname() : '' }}</td>
                                <td class="text-xs text-center">
                                    <span class="badge badge-xs {{ $extra_all_data->stat == 'A' ? 'badge-primary' : 'badge-error' }}">
                                        {{ $extra_all_data->stat }}
                                    </span>
                                </td>
                                <td>
                                    @if ($extra_all_data->stat == 'A')
                                        <button class="btn btn-xs btn-ghost btn-error"
                                            wire:click="$set('rx_id', {{ $extra_all_data->id }});
                                                $set('rx_dmdcomb', '{{ $extra_all_data->dmdcomb }}');
                                                $set('rx_dmdctr', '{{ $extra_all_data->dmdctr }}');
                                                $set('empid', '{{ $extra_all->empid }}');
                                                $set('showDeactivateRxModal', true)">
                                            <x-heroicon-o-x-mark class="w-3 h-3" />
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

    {{-- Inline Remarks Edit --}}
    @if ($selected_remarks)
        <x-mary-modal wire:model="selected_remarks" title="Edit Remarks" class="backdrop-blur">
            <x-mary-input label="Remarks" wire:model="new_remarks" placeholder="Enter remarks..." />
            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('selected_remarks', null)" />
                <x-mary-button label="Save" icon="o-check" class="btn-primary" wire:click="update_remarks" spinner />
            </x-slot:actions>
        </x-mary-modal>
    @endif

    {{-- Keyboard Shortcuts --}}
    @script
    <script>
        document.addEventListener('keydown', e => {
            if (e.ctrlKey && e.key === 'c' && !e.shiftKey) {
                e.preventDefault();
                $wire.charge_items();
            }
            if (e.ctrlKey && e.key === 'i') {
                e.preventDefault();
                $wire.$set('showIssueModal', true);
            }
        });

        $wire.on('open-charge-slip', ({ pcchrgcod }) => {
            window.open('{{ url('/dispensing/encounter/charge') }}' + '/' + pcchrgcod, '_blank');
        });
    </script>
    @endscript
</div>
