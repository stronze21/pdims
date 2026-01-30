<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="text-sm breadcrumbs">
                <ul>
                    <li class="font-bold">
                        <i class="mr-1 las la-hospital la-lg"></i>
                        {{ session('pharm_location_name') }}
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

    <div class=" px-4 py-4 mx-auto">

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-6">
            {{-- Orders Table - 3 columns --}}
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
                {{-- Cart Section (NEW) --}}
                {{-- @if (count($cartItems) > 0)
                    <div class="mb-4 shadow-lg card bg-base-100">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="flex items-center gap-2 card-title">
                                    <i class="las la-shopping-cart"></i>
                                    Cart ({{ count($cartItems) }})
                                </h2>
                                <div class="flex gap-2">
                                    <button class="btn btn-sm btn-ghost" wire:click="clearCart">
                                        <i class="las la-trash"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success" wire:click="saveAndChargeAll"
                                        wire:loading.attr="disabled">
                                        <i class="las la-save"></i> Charge All (Ctrl+S)
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="table table-xs">
                                    <thead>
                                        <tr>
                                            <th>Drug</th>
                                            <th class="text-right">Qty</th>
                                            <th class="text-right">Price</th>
                                            <th class="text-right">Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($cartItems as $index => $item)
                                            <tr>
                                                <td>
                                                    <p class="text-sm font-medium">
                                                        {{ str_replace('_,', ' ', $item['drug_concat']) }}</p>
                                                    <p class="text-xs text-gray-500">{{ $item['charge_desc'] }}</p>
                                                </td>
                                                <td class="text-right">
                                                    <input type="number" class="w-16 input input-xs input-bordered"
                                                        value="{{ $item['qty'] }}"
                                                        wire:change="updateCartQty({{ $index }}, $event.target.value)"
                                                        min="1" step="0.01">
                                                </td>
                                                <td class="text-right text-xs">
                                                    ₱{{ number_format($item['unit_price'], 2) }}
                                                </td>
                                                <td class="text-right font-bold text-sm">
                                                    ₱{{ number_format($item['total'], 2) }}</td>
                                                <td>
                                                    <button class="btn btn-ghost btn-xs"
                                                        wire:click="removeFromCart({{ $index }})">
                                                        <i class="text-error las la-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-bold">
                                            <td colspan="3" class="text-right">TOTAL:</td>
                                            <td class="text-right">₱{{ number_format($cartTotal, 2) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif --}}

                {{-- Orders Table --}}
                <div class="shadow-lg card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="card-title">Drug Orders</h2>
                            @if (count($selected_items) > 0)
                                <div class="flex gap-2">
                                    <button class="btn btn-sm btn-ghost" wire:click="$set('selected_items', [])">
                                        <i class="las la-trash"></i> Clear Selection
                                    </button>
                                    <button class="btn btn-sm btn-success" wire:click="openIssueModal">
                                        <i class="las la-hand-holding-medical"></i> Issue
                                        (Ctrl+I)
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="overflow-x-auto max-h-[calc(100vh-21rem)] ">
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
                                                            href="" target="_blank">{{ $order->pcchrgcod }}</a>
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
                                                    <div class="ml-10 text-xs text-slate-800">
                                                        {{ $concat[1] }}</div>
                                                </div>
                                            </td>
                                            <td class="w-20 text-xs text-right whitespace-nowrap">
                                                @if (!$order->pcchrgcod)
                                                    <span class="cursor-pointer tooltip" data-tip="Update"
                                                        onclick="update_qty('{{ $order->docointkey }}', {{ $order->pchrgqty }}, {{ $order->pchrgup }}, {{ $order->pcchrgamt }}, `{{ $concat[0] }} <br>{{ $concat[1] }}`)">
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
                                                        onclick="return_issued('{{ $order->docointkey }}', `{{ $concat[0] }} <br>{{ $concat[1] }}`, {{ $order->pchrgup }}, {{ $order->qtyissued }})">
                                                        <i class="text-red-600 las la-lg la-undo-alt"></i>
                                                        {{ number_format($order->qtyissued) }}
                                                    </span>
                                                @else
                                                    {{ number_format($order->qtyissued) }}
                                                @endif
                                            </td>
                                            <td class="text-xs text-right w-min">
                                                {{ number_format($order->pchrgup, 2) }}
                                            </td>
                                            <td class="text-xs text-right w-min total">
                                                {{ number_format($order->pcchrgamt, 2) }}
                                            </td>
                                            <td class="text-xs ">
                                                <div class="join">
                                                    @if ($selected_remarks == $order->docointkey)
                                                        <div>
                                                            <div>
                                                                <textarea class="join-item textarea textarea-bordered textarea-xs min-w-xs" wire:model.lazy="new_remarks"
                                                                    wire:key="rem-input-{{ $order->docointkey }}">{{ $order->remarks }}</textarea>
                                                            </div>
                                                        </div>
                                                        <button class="join-item btn-primary btn"
                                                            wire:click="update_remarks()"
                                                            wire:key="update-rem-{{ $order->docointkey }}">
                                                            <x-mary-icon name="o-pencil-square" class="las la-lg" />
                                                        </button>
                                                    @else
                                                        <div>
                                                            <div>
                                                                <textarea class="join-item textarea textarea-bordered textarea-xs min-w-xs"
                                                                    wire:key="rem-input-dis-{{ $order->docointkey }}" disabled>{{ $order->remarks }}</textarea>
                                                            </div>
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
                        <h2 class="card-title text-sm">Search Drug</h2>
                        <input type="text" placeholder="Search drug name..."
                            wire:model.live.debounce.300ms="generic" class="w-full input input-sm input-bordered">
                    </div>
                </div>

                {{-- Available Stocks --}}
                <div class="shadow-lg card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title text-sm">Available Stocks (FEFO)</h2>

                        <div class="overflow-y-auto max-h-96">
                            <div class="space-y-2">
                                @forelse($stocks as $stock)
                                    @php
                                        $parts = explode('_,', $stock->drug_concat);
                                        $expDate = \Carbon\Carbon::parse($stock->exp_date);
                                        $daysToExpiry = $stock->days_to_expiry;
                                        $consignmentBadge = '';

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
                                        wire:click="selectStock({{ json_encode($stock) }})"
                                        @if ($expDate->isPast() || $stock->stock_bal <= 0) style="pointer-events: none;" @endif>
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <span class="badge badge-xs {{ $consignmentBadge }}">
                                                    {{ $stock->chrgdesc }}
                                                </span>
                                                <p class="text-sm font-medium">{{ $parts[0] ?? '' }}</p>
                                                <p class="text-xs text-gray-600">{{ $parts[1] ?? '' }}</p>
                                                <span class="badge badge-xs {{ $expiryBadge }}">
                                                    Exp: {{ $expDate->format('M Y') }}
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <p
                                                    class="text-lg font-bold {{ $stock->stock_bal <= 10 ? 'text-error' : '' }}">
                                                    {{ number_format($stock->stock_bal) }}
                                                </p>
                                                <p class="text-xs text-primary">
                                                    ₱{{ number_format($stock->dmselprice, 2) }}
                                                </p>
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

                {{-- Summary (if available) --}}
                @if (collect($summaries)->isNotEmpty())
                    <div class="shadow-lg card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title text-sm">Issued Summary</h2>
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
    <input type="checkbox" id="addItemModal" class="modal-toggle" wire:model="showAddItemModal" />
    <div class="modal">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Add Item to Cart</h3>

            @if ($selectedStock ?? false)
                <div class="py-4 space-y-4">
                    {{-- Drug Info --}}
                    <div class="p-3 rounded-lg bg-base-200">
                        <p class="text-sm font-bold">{{ str_replace('_,', ' ', $selectedStock->drug_concat) }}</p>
                        <p class="text-xs text-gray-600">{{ $selectedStock->chrgdesc }}</p>
                        <div class="flex justify-between mt-2">
                            <span class="text-xs">Available:</span>
                            <span class="font-bold">{{ number_format($selectedStock->stock_bal, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs">Expiry:</span>
                            <span
                                class="text-xs">{{ \Carbon\Carbon::parse($selectedStock->exp_date)->format('M d, Y') }}</span>
                        </div>
                    </div>

                    {{-- Quantity --}}
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Quantity</span>
                        </label>
                        <input type="number" wire:model="order_qty" class="input input-bordered" min="1"
                            step="0.01" max="{{ $selectedStock->stock_bal }}">
                    </div>

                    {{-- Unit Price --}}
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Unit Price</span>
                        </label>
                        <input type="number" wire:model="unit_price" class="input input-bordered" min="0"
                            step="0.01">
                    </div>

                    {{-- Remarks --}}
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Remarks (Optional)</span>
                        </label>
                        <textarea wire:model="remarks" class="textarea textarea-bordered" rows="2"
                            placeholder="Enter optional remarks..."></textarea>
                    </div>

                    {{-- Total --}}
                    @if ($order_qty && $unit_price)
                        <div class="p-3 rounded-lg bg-primary text-primary-content">
                            <div class="flex justify-between">
                                <span class="font-semibold">Total:</span>
                                <span
                                    class="text-xl font-bold">₱{{ number_format($order_qty * $unit_price, 2) }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="modal-action">
                <label for="addItemModal" class="btn">Cancel</label>
                <button class="btn btn-primary" wire:click="addToCart" wire:loading.attr="disabled">Add to
                    Cart</button>
            </div>
        </div>
    </div>

    {{-- Issue Modal --}}
    <input type="checkbox" id="issueModal" class="modal-toggle" wire:model="showIssueModal" />
    <div class="modal">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Issue Items</h3>

            <div class="py-4 space-y-4">
                @php
                    if (in_array($toecode, ['ADM', 'OPDAD', 'ERADM'])) {
                        $issueTypes = [['id' => 'service', 'name' => 'Basic'], ['id' => 'pay', 'name' => 'Non-Basic']];
                    } else {
                        $issueTypes = [
                            ['id' => 'pay', 'name' => 'Pay'],
                            ['id' => 'ems', 'name' => 'EMS'],
                            ['id' => 'maip', 'name' => 'MAIP'],
                            ['id' => 'caf', 'name' => 'CAF'],
                            ['id' => 'konsulta', 'name' => 'Konsulta Package'],
                            ['id' => 'pcso', 'name' => 'PCSO'],
                            ['id' => 'phic', 'name' => 'PHIC'],
                            ['id' => 'wholesale', 'name' => 'Wholesale'],
                            ['id' => 'doh_free', 'name' => 'DOH Free'],
                        ];
                    }
                @endphp

                {{-- Issue Type Selection --}}
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-bold">Issue Type</span>
                    </label>
                    @if (in_array($toecode, ['ADM', 'OPDAD', 'ERADM']))
                        <div class="space-y-2">
                            @foreach ($issueTypes as $type)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" class="radio radio-sm"
                                        wire:model.live="{{ $type['id'] }}" value="true" />
                                    <span>{{ $type['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($issueTypes as $type)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" class="checkbox checkbox-sm"
                                        wire:model.live="{{ $type['id'] }}" />
                                    <span class="text-sm">{{ $type['name'] }}</span>
                                </label>
                            @endforeach
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="is_ris" />
                                <span class="text-sm">RIS</span>
                            </label>
                        </div>
                    @endif
                </div>

                {{-- Department --}}
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Department (Optional)</span>
                    </label>
                    <select wire:model="deptcode" class="select select-bordered select-sm">
                        <option value="">Select department</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->deptcode }}">{{ $dept->deptname }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="alert alert-warning">
                    <i class="las la-exclamation-triangle"></i>
                    <span class="text-xs">
                        Issuing {{ count($selectedChargedItems ?? []) }} item(s). This will update stock logs and
                        remove
                        temporary allocations.
                    </span>
                </div>
            </div>

            <div class="modal-action">
                <label for="issueModal" class="btn">Cancel</label>
                <button class="btn btn-success" wire:click="issueSelected" wire:loading.attr="disabled">
                    <i class="las la-check"></i> Issue Items
                </button>
            </div>
        </div>
    </div>

    {{-- Keyboard Shortcuts Hint --}}
    <div class="fixed shadow-lg bottom-4 right-4 card bg-base-100">
        <div class="card-body compact">
            <p class="text-xs font-bold">Shortcuts:</p>
            <p class="text-xs"><kbd class="kbd kbd-xs">Ctrl+S</kbd> Charge All</p>
            <p class="text-xs"><kbd class="kbd kbd-xs">Ctrl+I</kbd> Issue Selected</p>
        </div>
    </div>
    <script>
        function sortTable(n) {
            var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
            table = document.getElementById("table");
            switching = true;
            // Set the sorting direction to ascending:
            dir = "asc";
            /* Make a loop that will continue until
            no switching has been done: */
            while (switching) {
                // Start by saying: no switching is done:
                switching = false;
                rows = table.rows;
                /* Loop through all table rows (except the
                first, which contains table headers): */
                for (i = 1; i < (rows.length - 1); i++) {
                    // Start by saying there should be no switching:
                    shouldSwitch = false;
                    /* Get the two elements you want to compare,
                    one from current row and one from the next: */
                    x = rows[i].getElementsByTagName("TD")[n];
                    y = rows[i + 1].getElementsByTagName("TD")[n];
                    /* Check if the two rows should switch place,
                    based on the direction, asc or desc: */
                    if (dir == "asc") {
                        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                            // If so, mark as a switch and break the loop:
                            shouldSwitch = true;
                            break;
                        }
                    } else if (dir == "desc") {
                        if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                            // If so, mark as a switch and break the loop:
                            shouldSwitch = true;
                            break;
                        }
                    }
                }
                if (shouldSwitch) {
                    /* If a switch has been marked, make the switch
                    and mark that a switch has been done: */
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    // Each time a switch is done, increase this count by 1:
                    switchcount++;
                } else {
                    /* If no switching has been done AND the direction is "asc",
                    set the direction to "desc" and run the while loop again. */
                    if (switchcount == 0 && dir == "asc") {
                        dir = "desc";
                        switching = true;
                    }
                }
            }
        }
    </script>
</div>

@script
    <script>
        $('input[name="docointkey"]').change(function() {
            if ($(this).is(':checked')) {
                $('.' + this.className).prop('checked', true);
                var myArray = []
                var value = ''
                $('input[name="docointkey"]:checked').each(function() {
                    value = $(this).val();
                    myArray.push(value)
                })
                $wire.set('selected_items', myArray);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                @this.call('saveAndChargeAll');
            }
            if (e.ctrlKey && e.key === 'i') {
                e.preventDefault();
                @this.call('issueSelected');
            }
        });

        // Open charge slip
        $wire.on('open-charge-slip', (event) => {
            const chargeCode = event.chargeCode || event[0]?.chargeCode;
            if (chargeCode) {
                window.open(`/charge-slip/${chargeCode}`, '_blank', 'width=800,height=600');
            }
        });
    </script>
@endscript
