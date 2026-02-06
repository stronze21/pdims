<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="text-sm breadcrumbs">
                <ul>
                    <li class="font-bold">
                        <i class="mr-1 las la-hospital la-lg"></i>
                        {{ auth()->user()->pharm_location->phlocation ?? 'Pharmacy' }}
                    </li>
                    <li>
                        <i class="mr-1 las la-pills la-lg"></i>
                        Dispensing
                    </li>
                    <li>{{ $code }}</li>
                </ul>
            </div>
        </div>
    </x-slot>

    <div class="px-4 py-4 mx-auto">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-6">
            {{-- Main Content - 4 columns --}}
            <div class="lg:col-span-4">
                {{-- Patient Info Card --}}
                <div class="mb-4 shadow-lg card bg-gradient-to-r from-primary/10 to-secondary/10">
                    <div class="card-body">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <p class="text-xs text-gray-500">Hospital #</p>
                                <p class="font-mono text-lg font-bold">{{ $hpercode }}</p>
                                <p class="mt-1 font-medium">{{ $patlast }}, {{ $patfirst }} {{ $patmiddle }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Encounter Type</p>
                                <div class="badge badge-primary">{{ $toecode }}</div>
                                <p class="mt-1 text-sm">{{ \Carbon\Carbon::parse($encdate)->format('M d, Y h:i A') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Diagnosis</p>
                                <p class="text-sm">{{ $diagtext ?: 'N/A' }}</p>
                                @if ($wardname || $rmname)
                                    <p class="mt-1 text-xs text-gray-600">
                                        {{ $wardname }} {{ $rmname ? '- ' . $rmname : '' }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                @if ($billstat != '02' and $billstat != '03')
                    @if (session('active_consumption'))
                        <div class="flex justify-between gap-2 mb-4">
                            <div class="flex gap-2">
                                <label for="summary" class="btn btn-sm btn-ghost">
                                    <i class="las la-list"></i> Summary
                                </label>
                            </div>
                            <div class="flex gap-2">
                                <button class="btn btn-sm btn-error" wire:click="delete_item"
                                    wire:loading.attr="disabled">
                                    <i class="las la-trash"></i> Delete (Ctrl+X)
                                </button>
                                <button class="btn btn-sm btn-warning" wire:click="charge_items"
                                    wire:loading.attr="disabled">
                                    <i class="las la-file-invoice"></i> Charge (Ctrl+C)
                                </button>
                                <button class="btn btn-sm btn-primary" wire:click="issue_order"
                                    wire:loading.attr="disabled">
                                    <i class="las la-hand-holding-medical"></i> Issue (Ctrl+I)
                                </button>
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Orders Table - VERSION 2 DESIGN --}}
                <div class="shadow-lg card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="card-title">Drug Orders</h2>
                            @if (count($selected_items) > 0)
                                <button class="btn btn-sm btn-ghost" wire:click="$set('selected_items', [])">
                                    <i class="las la-times"></i> Clear Selection
                                </button>
                            @endif
                        </div>

                        <div class="overflow-x-auto max-h-[calc(100vh-21rem)]">
                            <table class="w-full table table-xs table-pin-rows" id="table">
                                <thead>
                                    <tr>
                                        <td class="text-center w-min"></td>
                                        <td class="whitespace-nowrap w-min" onclick="sortTable(1)">Charge Slip <i
                                                class="las la-sort"></i></td>
                                        <td class="whitespace-nowrap w-min" onclick="sortTable(2)">Date of Order <i
                                                class="las la-sort"></i></td>
                                        <td class="w-max whitespace-nowrap" onclick="sortTable(3)">Description <i
                                                class="las la-sort"></i></td>
                                        <td class="w-20 text-right">
                                            <div class="tooltip" data-tip="Quantity Ordered">Q.O.</div>
                                        </td>
                                        <td class="w-20 text-right">
                                            <div class="tooltip" data-tip="Quantity Issued">Q.I.</div>
                                        </td>
                                        <td class="text-right w-min">Price</td>
                                        <td class="text-right w-min">Total</td>
                                        <td>Remarks</td>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($orders as $order)
                                        @php
                                            $concat = explode('_,', $order->drug_concat);
                                            $drug = implode('', $concat);

                                            if ($order->estatus == 'U' || !$order->pcchrgcod) {
                                                $badge =
                                                    '<span class="uppercase badge badge-sm badge-warning">Pending</span>';
                                            } elseif ($order->estatus == 'P' && $order->pcchrgcod) {
                                                $badge =
                                                    '<span class="uppercase badge badge-sm badge-info">Charged</span>';
                                            } elseif ($order->estatus == 'S' && $order->pcchrgcod) {
                                                $badge =
                                                    '<span class="uppercase badge badge-sm badge-success">Issued</span>';
                                            }
                                        @endphp
                                        <tr class="border">
                                            <td class="w-10 text-xs text-center">
                                                <input type="checkbox"
                                                    class="checkbox{{ '-' . ($order->pcchrgcod ?? 'blank') }}{{ date('mdY', strtotime($order->dodate)) }}"
                                                    wire:model="selected_items"
                                                    wire:key="item-{{ $order->docointkey }}" name="docointkey"
                                                    value="'{{ $order->docointkey }}'" />
                                            </td>
                                            <td class="text-xs whitespace-nowrap w-min" title="View Charge Slip">
                                                <div class="flex flex-col align-center">
                                                    {!! $badge !!}
                                                    @if ($order->pcchrgcod)
                                                        <a rel="noopener noreferrer" class="font-semibold text-blue-600"
                                                            href="{{ route('dispensing.rxo.chargeslip', $order->pcchrgcod) }}"
                                                            target="_blank">{{ $order->pcchrgcod }}</a>
                                                    @endif
                                                    <span>{{ $order->tx_type }} {!! $order->prescription_data_id ? '<i class="las la-prescription"></i>' : '' !!}</span>
                                                </div>
                                            </td>
                                            <td class="text-xs align-center whitespace-nowrap w-min">
                                                <div class="flex flex-col">
                                                    <div>{{ date('m/d/Y', strtotime($order->dodate)) }}</div>
                                                    <div>{{ date('h:i A', strtotime($order->dodate)) }}</div>
                                                </div>
                                            </td>
                                            <td class="w-6/12 text-xs">
                                                <span class="hidden">{{ $concat[0] }}</span>
                                                <div class="flex flex-col">
                                                    <div class="text-xs text-slate-600">{{ $order->chrgdesc ?? '' }}
                                                    </div>
                                                    <div class="text-xs font-bold">{{ $concat[0] }}</div>
                                                    <div class="ml-10 text-xs text-slate-800">{{ $concat[1] }}</div>
                                                </div>
                                            </td>
                                            <td class="w-20 text-xs text-right whitespace-nowrap">
                                                @if (!$order->pcchrgcod)
                                                    <span class="cursor-pointer tooltip" data-tip="Update"
                                                        wire:click="$set('docointkey', '{{ $order->docointkey }}'); $set('order_qty', {{ $order->pchrgqty }}); $set('unit_price', {{ $order->pchrgup }})"
                                                        onclick="document.getElementById('updateQtyModal').checked = true">
                                                        <i class="las la-lg la-edit"></i>
                                                        {{ number_format($order->pchrgqty) }}
                                                    </span>
                                                @else
                                                    {{ number_format($order->pchrgqty) }}
                                                @endif
                                            </td>
                                            <td class="w-20 text-xs text-right whitespace-nowrap">
                                                @if ($order->estatus == 'S' and $order->qtyissued > 0)
                                                    <span class="cursor-pointer tooltip" data-tip="Return"
                                                        wire:click="$set('docointkey', '{{ $order->docointkey }}'); $set('order_qty', {{ $order->pchrgqty }}); $set('unit_price', {{ $order->pchrgup }})"
                                                        onclick="document.getElementById('returnModal').checked = true">
                                                        <i class="text-red-600 las la-lg la-undo-alt"></i>
                                                        {{ number_format($order->qtyissued) }}
                                                    </span>
                                                @else
                                                    {{ number_format($order->qtyissued) }}
                                                @endif
                                            </td>
                                            <td class="text-xs text-right w-min">
                                                {{ number_format($order->pchrgup, 2) }}</td>
                                            <td class="text-xs text-right w-min total">
                                                {{ number_format($order->pcchrgamt, 2) }}</td>
                                            <td class="text-xs">
                                                <div class="join">
                                                    @if ($selected_remarks == $order->docointkey)
                                                        <div>
                                                            <textarea class="join-item textarea textarea-bordered textarea-xs min-w-xs" wire:model.lazy="new_remarks"
                                                                wire:key="rem-input-{{ $order->docointkey }}">{{ $order->remarks }}</textarea>
                                                        </div>
                                                        <button class="join-item btn-primary btn"
                                                            wire:click="update_remarks()"
                                                            wire:key="update-rem-{{ $order->docointkey }}">
                                                            <x-mary-icon name="o-pencil-square" class="las la-lg" />
                                                        </button>
                                                    @else
                                                        <div>
                                                            <textarea class="join-item textarea textarea-bordered textarea-xs min-w-xs"
                                                                wire:key="rem-input-dis-{{ $order->docointkey }}" disabled>{{ $order->remarks }}</textarea>
                                                        </div>
                                                        <button class="join-item btn"
                                                            wire:click="$set('selected_remarks', '{{ $order->docointkey }}')"
                                                            wire:key="set-rem-id-dis-{{ $order->docointkey }}">
                                                            <x-mary-icon name="o-pencil-square" class="las la-lg" />
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center text-gray-500">
                                                <i class="las la-inbox la-2x"></i>
                                                <p class="text-sm">No orders yet</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Sidebar - 2 columns --}}
            <div class="space-y-4 lg:col-span-2">
                {{-- Search --}}
                <div class="shadow-lg card bg-base-100">
                    <div class="card-body">
                        <h2 class="text-sm card-title">Search Drug</h2>
                        <input type="text" placeholder="Search drug name..."
                            wire:model.live.debounce.300ms="generic" class="w-full input input-sm input-bordered">
                    </div>
                </div>

                {{-- Charge Code Filter with MaryUI Choices --}}
                <div class="shadow-lg card bg-base-100">
                    <div class="card-body">
                        <h2 class="text-sm card-title">Filter by Fund Source</h2>
                        <x-mary-choices wire:model.live="charge_code_filter" :options="$charges" option-value="chrgcode"
                            option-label="chrgdesc" searchable multiple placeholder="Select charge codes..." />
                    </div>
                </div>

                {{-- Available Stocks with Alpine Auto-Load --}}
                <div class="shadow-lg card bg-base-100">
                    <div class="card-body">
                        <h2 class="text-sm card-title">Available Stocks (FEFO)</h2>

                        <div class="overflow-y-auto max-h-96" x-data="{
                            init() {
                                let container = this.$el;
                                container.addEventListener('scroll', () => {
                                    if (container.scrollTop + container.clientHeight >= container.scrollHeight - 50) {
                                        @this.call('loadMoreStocks');
                                    }
                                });
                            }
                        }">
                            <div class="space-y-2">
                                @forelse($stocks as $stock)
                                    @php
                                        $parts = explode('_,', $stock->drug_concat);
                                        $expDate = \Carbon\Carbon::parse($stock->exp_date);
                                        $daysToExpiry = $stock->days_to_expiry;

                                        if ($expDate->isPast()) {
                                            $expiryBadge = 'badge-error';
                                        } elseif ($daysToExpiry <= 180) {
                                            $expiryBadge = 'badge-warning';
                                        } else {
                                            $expiryBadge = 'badge-success';
                                        }
                                        if (str_contains($stock->chrgdesc, 'Consignment')) {
                                            $consignmentBadge = 'badge-secondary';
                                        } else {
                                            $consignmentBadge = 'badge-outline';
                                        }
                                    @endphp
                                    <div class="p-2 border rounded-lg cursor-pointer hover:bg-base-200 {{ $expDate->isPast() ? 'opacity-50' : '' }}"
                                        wire:click="$set('item_id', '{{ $stock->id }}'); $set('unit_price', {{ $stock->dmselprice }}); $set('item_dmdcomb', '{{ $stock->dmdcomb }}'); $set('item_dmdctr', '{{ $stock->dmdctr }}'); $set('item_chrgcode', '{{ $stock->chrgcode }}'); $set('item_loc_code', '{{ $stock->loc_code }}'); $set('item_dmdprdte', '{{ $stock->dmdprdte }}'); $set('item_exp_date', '{{ $stock->exp_date }}'); $set('item_stock_bal', {{ $stock->stock_bal }})"
                                        onclick="document.getElementById('addItemModal').checked = true"
                                        @if ($expDate->isPast() || $stock->stock_bal <= 0) style="pointer-events: none;" @endif>
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <span
                                                    class="badge badge-xs {{ $consignmentBadge }}">{{ $stock->chrgdesc }}</span>
                                                <p class="text-sm font-medium">{{ $parts[0] ?? '' }}</p>
                                                <p class="text-xs text-gray-600">{{ $parts[1] ?? '' }}</p>
                                                <span class="badge badge-xs {{ $expiryBadge }}">Exp:
                                                    {{ $expDate->format('M Y') }}</span>
                                            </div>
                                            <div class="text-right">
                                                <p
                                                    class="text-lg font-bold {{ $stock->stock_bal <= 10 ? 'text-error' : '' }}">
                                                    {{ number_format($stock->stock_bal) }}</p>
                                                <p class="text-xs text-primary">
                                                    ₱{{ number_format($stock->dmselprice, 2) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-8 text-center">
                                        <i class="text-gray-300 las la-capsules la-3x"></i>
                                        <p class="mt-2 text-sm text-gray-500">No stocks available</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Active Prescriptions --}}
                @if (count($active_prescription) > 0 || count($extra_prescriptions) > 0)
                    <div class="shadow-lg card bg-base-100">
                        <div class="card-body">
                            <h2 class="text-sm card-title">Active Prescriptions</h2>
                            <div class="overflow-auto max-h-64">
                                <table class="w-full rounded-lg shadow-md table-compact">
                                    <thead class="sticky top-0 bg-gray-200 border-b">
                                        <tr>
                                            <td class="text-xs">Order at</td>
                                            <td class="text-xs">Description</td>
                                            <td class="text-xs">QTY</td>
                                            <td class="text-xs">Prescribed by</td>
                                            <td class="text-xs">Deactivate</td>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        @forelse($active_prescription as $presc)
                                            @forelse($presc->data_active->all() as $presc_data)
                                                <tr class="hover"
                                                    wire:key="select-rx-item-{{ $loop->parent->iteration }}-{{ $loop->iteration }}">
                                                    <td class="text-xs">
                                                        {{ date('Y-m-d', strtotime($presc_data->updated_at)) }}
                                                        {{ date('h:i A', strtotime($presc_data->updated_at)) }}
                                                    </td>
                                                    <td class="text-xs cursor-pointer"
                                                        wire:click="$set('rx_id', '{{ $presc_data->id }}'); $set('rx_dmdcomb', '{{ $presc_data->dmdcomb }}'); $set('rx_dmdctr', '{{ $presc_data->dmdctr }}'); $set('empid', '{{ $presc->empid }}')"
                                                        onclick="document.getElementById('addPrescribedItemModal').checked = true">
                                                        {{ $presc_data->dm->drug_concat() }}</td>
                                                    <td class="text-xs">
                                                        @switch($presc_data->order_type)
                                                            @case('g24')
                                                            @case('G24')
                                                                <div class="flex tooltip" data-tip="Good For 24 Hrs"><i
                                                                        class="las la-2g la-hourglass-start"></i>
                                                                    <div class="badge badge-error badge-xs">
                                                                        {{ $presc_data->qty }}</div>
                                                                </div>
                                                            @break

                                                            @case('or')
                                                            @case('Or')

                                                            @case('OR')
                                                                <div class="flex tooltip" data-tip="For Operating Use"><i
                                                                        class="las la-2g la-syringe"></i>
                                                                    <div class="badge badge-secondary badge-xs">
                                                                        {{ $presc_data->qty }}</div>
                                                                </div>
                                                            @break

                                                            @default
                                                                <div class="flex tooltip" data-tip="BASIC"><i
                                                                        class="las la-2g la-prescription"></i>
                                                                    <div class="badge badge-accent badge-xs">
                                                                        {{ $presc_data->qty }}</div>
                                                                </div>
                                                        @endswitch
                                                    </td>
                                                    <td class="text-xs">{{ $presc_data->employee->fullname }}</td>
                                                    <td class="text-xs cursor-pointer"><button
                                                            class="btn btn-xs btn-error"
                                                            wire:click="$set('rx_id', '{{ $presc_data->id }}')"
                                                            onclick="document.getElementById('deactivateRxModal').checked = true"
                                                            wire:loading.attr="disabled"><i
                                                                class="las la-sliders-h"></i></button>
                                                    </td>
                                                </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5"><i class="las la-lg la-ban"></i> No record
                                                            found!</td>
                                                    </tr>
                                                @endforelse
                                                @empty
                                                @endforelse
                                                @foreach ($extra_prescriptions as $extra)
                                                    @forelse($extra->data_active->all() as $extra_data)
                                                        <tr class="hover"
                                                            wire:key="select-rx-item-extra-{{ $loop->parent->iteration }}-{{ $loop->iteration }}">
                                                            <td class="text-xs">
                                                                {{ date('Y-m-d', strtotime($extra_data->updated_at)) }}
                                                                {{ date('h:i A', strtotime($extra_data->updated_at)) }}
                                                            </td>
                                                            <td class="text-xs cursor-pointer"
                                                                wire:click="$set('rx_id', '{{ $extra_data->id }}'); $set('rx_dmdcomb', '{{ $extra_data->dmdcomb }}'); $set('rx_dmdctr', '{{ $extra_data->dmdctr }}'); $set('empid', '{{ $extra->empid }}')"
                                                                onclick="document.getElementById('addPrescribedItemModal').checked = true">
                                                                {{ $extra_data->dm->drug_concat() }}</td>
                                                            <td class="text-xs">
                                                                @switch($extra_data->order_type)
                                                                    @case('g24')
                                                                    @case('G24')
                                                                        <div class="flex tooltip" data-tip="Good For 24 Hrs"><i
                                                                                class="las la-2g la-hourglass-start"></i>
                                                                            <div class="badge badge-error badge-xs">
                                                                                {{ $extra_data->qty }}</div>
                                                                        </div>
                                                                    @break

                                                                    @case('or')
                                                                    @case('Or')

                                                                    @case('OR')
                                                                        <div class="flex tooltip" data-tip="For Operating Use"><i
                                                                                class="las la-2g la-syringe"></i>
                                                                            <div class="badge badge-secondary badge-xs">
                                                                                {{ $extra_data->qty }}</div>
                                                                        </div>
                                                                    @break

                                                                    @default
                                                                        <div class="flex tooltip" data-tip="BASIC"><i
                                                                                class="las la-2g la-prescription"></i>
                                                                            <div class="badge badge-accent badge-xs">
                                                                                {{ $extra_data->qty }}</div>
                                                                        </div>
                                                                @endswitch
                                                            </td>
                                                            <td class="text-xs">{{ $extra_data->employee->fullname() }}</td>
                                                            <td class="text-xs"></td>
                                                        </tr>
                                                        @empty
                                                        @endforelse
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Summary (if available) --}}
                            @if (collect($summaries)->isNotEmpty())
                                <div class="shadow-lg card bg-base-100">
                                    <div class="card-body">
                                        <h2 class="text-sm card-title">Issued Summary</h2>
                                        <div class="space-y-2">
                                            @foreach ($summaries as $summary)
                                                @php
                                                    $parts = explode('_,', $summary->drug_concat);
                                                @endphp
                                                <div class="p-2 text-xs border-b">
                                                    <p class="font-medium">{{ $parts[0] ?? '' }}</p>
                                                    <p class="text-gray-500">Issued: {{ number_format($summary->qty_issued, 2) }}
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Add Item Modal --}}
                <input type="checkbox" id="addItemModal" class="modal-toggle" />
                <div class="modal">
                    <div class="modal-box" style="max-width: 500px; padding: 2rem;">
                        {{-- Title --}}
                        <h3 class="text-lg font-bold text-center mb-6">Add Item from Stock</h3>

                        {{-- Quantity and Unit Price Row --}}
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            {{-- Quantity --}}
                            <div>
                                <label
                                    style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; color: #374151;">Quantity</label>
                                <input type="number" wire:model.live="order_qty"
                                    style="width: 100%; padding: 0.75rem; text-align: center; font-size: 1.5rem; font-weight: 600; border: 1px solid #d1d5db; border-radius: 0.375rem;"
                                    placeholder="0" step="1" min="0" />
                            </div>

                            {{-- Unit Price --}}
                            <div>
                                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; color: #374151;">Unit
                                    Price</label>
                                <input type="text" value="{{ $unit_price ? number_format($unit_price, 2) : '' }}"
                                    style="width: 100%; padding: 0.75rem; text-align: center; font-size: 1.5rem; font-weight: 600; border: 1px solid #d1d5db; border-radius: 0.375rem; background-color: #f3f4f6;"
                                    readonly />
                            </div>
                        </div>

                        {{-- Total Row --}}
                        <div style="margin-bottom: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <label style="font-size: 0.875rem; color: #374151; min-width: 60px;">TOTAL</label>
                                <input type="text"
                                    value="{{ $order_qty && $unit_price ? '₱ ' . number_format($order_qty * $unit_price, 2) : '₱ 0.00' }}"
                                    style="flex: 1; padding: 0.75rem; text-align: center; font-size: 1.5rem; font-weight: 700; border: 1px solid #d1d5db; border-radius: 0.375rem; background-color: #f9fafb;"
                                    readonly />
                            </div>
                        </div>

                        {{-- Remarks --}}
                        <div style="margin-bottom: 1.5rem;">
                            <label
                                style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; color: #374151;">Remarks</label>
                            <textarea wire:model="remarks"
                                style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; resize: vertical;"
                                rows="3" placeholder="Enter remarks (optional)..."></textarea>
                        </div>

                        {{-- Available Stock Info --}}
                        @if ($item_stock_bal)
                            <div style="margin-bottom: 1rem; font-size: 0.75rem; color: #6b7280;">
                                Available Stock: {{ number_format($item_stock_bal) }}
                            </div>
                        @endif

                        {{-- Action Buttons --}}
                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button"
                                style="padding: 0.5rem 1.5rem; border-radius: 0.375rem; font-weight: 500; background-color: transparent; color: #374151; cursor: pointer;"
                                @click="document.getElementById('addItemModal').checked = false">
                                Cancel
                            </button>
                            <button type="button"
                                style="padding: 0.5rem 1.5rem; border-radius: 0.375rem; font-weight: 500; background-color: #6366f1; color: white; cursor: pointer;"
                                @click="document.getElementById('addItemModal').checked = false"
                                wire:click="add_item('{{ $item_dmdcomb }}', '{{ $item_dmdctr }}', '{{ $item_chrgcode }}', '{{ $item_loc_code }}', '{{ $item_dmdprdte }}', '{{ $item_id }}', {{ $item_stock_bal ?? 0 }}, '{{ $item_exp_date }}')">
                                Confirm
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Add Prescribed Item Modal --}}
                <input type="checkbox" id="addPrescribedItemModal" class="modal-toggle" />
                <div class="modal">
                    <div class="modal-box">
                        <h3 class="text-lg font-bold">Add Prescribed Item</h3>
                        <div class="py-4">
                            <div class="mb-3 form-control">
                                <label class="label">
                                    <span class="label-text">Quantity</span>
                                </label>
                                <input type="number" wire:model="order_qty"
                                    class="text-4xl text-center input input-bordered input-lg" placeholder="0" />
                            </div>
                            <div class="mb-3 form-control">
                                <label class="label">
                                    <span class="label-text">Charge Code</span>
                                </label>
                                <select wire:model="rx_charge_code" class="select select-bordered">
                                    <option value="">Select charge code</option>
                                    @foreach ($charges as $charge)
                                        <option value="{{ $charge->chrgcode }}">{{ $charge->chrgdesc }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-action">
                            <label for="addPrescribedItemModal" class="btn btn-ghost">Cancel</label>
                            <button type="button" class="btn btn-success"
                                @click="document.getElementById('addPrescribedItemModal').checked = false"
                                wire:click="add_prescribed_item('{{ $rx_dmdcomb }}', '{{ $rx_dmdctr }}')">
                                <i class="las la-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Update Quantity Modal --}}
                <input type="checkbox" id="updateQtyModal" class="modal-toggle" />
                <div class="modal">
                    <div class="modal-box">
                        <h3 class="text-lg font-bold">Update Quantity</h3>
                        <div class="py-4">
                            <div class="mb-3 form-control">
                                <label class="label">
                                    <span class="label-text">Quantity</span>
                                </label>
                                <input type="number" wire:model="order_qty"
                                    class="text-4xl text-center input input-bordered input-lg" />
                            </div>
                            <div class="mb-3 form-control">
                                <label class="label">
                                    <span class="label-text">Unit Price</span>
                                </label>
                                <input type="number" step="0.01" wire:model="unit_price" class="input input-bordered"
                                    readonly />
                            </div>
                            <div class="mb-3 form-control">
                                <label class="label">
                                    <span class="label-text">Total</span>
                                </label>
                                <input type="number" step="0.01"
                                    value="{{ $order_qty && $unit_price ? $order_qty * $unit_price : 0 }}"
                                    class="input input-bordered" readonly />
                            </div>
                        </div>
                        <div class="modal-action">
                            <label for="updateQtyModal" class="btn btn-ghost">Cancel</label>
                            <button type="button" class="btn btn-primary"
                                @click="document.getElementById('updateQtyModal').checked = false"
                                wire:click="update_qty('{{ $docointkey }}')">
                                <i class="las la-check"></i> Update
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Return Issued Modal --}}
                <input type="checkbox" id="returnModal" class="modal-toggle" />
                <div class="modal">
                    <div class="modal-box">
                        <h3 class="text-lg font-bold">Return Issued Item</h3>
                        <div class="py-4">
                            <div class="mb-3 form-control">
                                <label class="label">
                                    <span class="label-text">Ordered Quantity</span>
                                </label>
                                <input type="number" wire:model="order_qty" class="input input-bordered" readonly />
                            </div>
                            <div class="mb-3 form-control">
                                <label class="label">
                                    <span class="label-text">Return Quantity</span>
                                </label>
                                <input type="number" wire:model="return_qty"
                                    class="text-4xl text-center input input-bordered input-lg" placeholder="0" />
                            </div>
                            <div class="mb-3 form-control">
                                <label class="label">
                                    <span class="label-text">Unit Price</span>
                                </label>
                                <input type="number" step="0.01" wire:model="unit_price" class="input input-bordered"
                                    readonly />
                            </div>
                        </div>
                        <div class="modal-action">
                            <label for="returnModal" class="btn btn-ghost">Cancel</label>
                            <button type="button" class="btn btn-error"
                                @click="document.getElementById('returnModal').checked = false"
                                wire:click="return_issued('{{ $docointkey }}')">
                                <i class="las la-undo"></i> Confirm Return
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Deactivate Prescription Modal --}}
                <input type="checkbox" id="deactivateRxModal" class="modal-toggle" />
                <div class="modal">
                    <div class="modal-box">
                        <h3 class="text-lg font-bold">Deactivate Prescription</h3>
                        <div class="py-4">
                            <div class="mb-3 form-control">
                                <label class="label">
                                    <span class="label-text">Remarks</span>
                                </label>
                                <textarea wire:model="adttl_remarks" class="textarea textarea-bordered"
                                    placeholder="Enter reason for deactivation..." rows="4"></textarea>
                            </div>
                        </div>
                        <div class="modal-action">
                            <label for="deactivateRxModal" class="btn btn-ghost">Cancel</label>
                            <button type="button" class="btn btn-error"
                                @click="document.getElementById('deactivateRxModal').checked = false"
                                wire:click="deactivate_rx('{{ $rx_id }}')">
                                <i class="las la-times"></i> Deactivate
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Summary Modal --}}
                <input type="checkbox" id="summary" class="modal-toggle" />
                <div class="modal">
                    <div class="modal-box">
                        <h3 class="text-lg font-bold">Issued Summary</h3>
                        <div class="py-4">
                            <table class="table table-xs">
                                <thead>
                                    <tr>
                                        <th>Drug</th>
                                        <th class="text-right">Qty Issued</th>
                                        <th>Last Issue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($summaries as $summary)
                                        <tr>
                                            <td>{{ str_replace('_', ' ', $summary->drug_concat) }}</td>
                                            <td class="text-right">{{ $summary->qty_issued }}</td>
                                            <td>{{ \Carbon\Carbon::parse($summary->last_issue)->format('M d, Y h:i A') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center">No issued items</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-action">
                            <label for="summary" class="btn btn-sm">Close</label>
                        </div>
                    </div>
                </div>

                <script>
                    function sortTable(n) {
                        var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
                        table = document.getElementById("table");
                        switching = true;
                        dir = "asc";
                        while (switching) {
                            switching = false;
                            rows = table.rows;
                            for (i = 1; i < (rows.length - 1); i++) {
                                shouldSwitch = false;
                                x = rows[i].getElementsByTagName("TD")[n];
                                y = rows[i + 1].getElementsByTagName("TD")[n];
                                if (dir == "asc") {
                                    if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                                        shouldSwitch = true;
                                        break;
                                    }
                                } else if (dir == "desc") {
                                    if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                                        shouldSwitch = true;
                                        break;
                                    }
                                }
                            }
                            if (shouldSwitch) {
                                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                                switching = true;
                                switchcount++;
                            } else {
                                if (switchcount == 0 && dir == "asc") {
                                    dir = "desc";
                                    switching = true;
                                }
                            }
                        }
                    }

                    // Checkbox selection
                    document.addEventListener('change', function(e) {
                        if (e.target && e.target.name === 'docointkey') {
                            var myArray = [];
                            document.querySelectorAll('input[name="docointkey"]:checked').forEach(function(checkbox) {
                                myArray.push(checkbox.value);
                            });
                            @this.set('selected_items', myArray);
                        }
                    });

                    // Keyboard shortcuts
                    document.addEventListener('keydown', function(e) {
                        if (e.ctrlKey && e.key === 'c') {
                            e.preventDefault();
                            @this.call('charge_items');
                        }
                        if (e.ctrlKey && e.key === 'i') {
                            e.preventDefault();
                            @this.call('issue_order');
                        }
                        if (e.ctrlKey && e.key === 'x') {
                            e.preventDefault();
                            @this.call('delete_item');
                        }
                    });
                </script>
            </div>
