@php
    $total_issued = 0;
    $total_amt = 0;
@endphp
<div class="container max-w-xl mx-auto mt-5">
    <div class="flex justify-between mb-3 align-middle no-print">
        <div class="form-control">
            <label class="cursor-pointer label">
                <span class="text-lg font-bold uppercase label-text">Show returned items</span>
                <input type="checkbox" class="ml-2 checkbox checkbox-primary" wire:model.live="view_returns" />
            </label>
        </div>
        <button class="btn btn-sm btn-primary" onclick="printMe()">Print</button>
    </div>
    <div id="print" class="bg-white text-black w-box-border">
        <div class="p-2">
            <div class="flex flex-col text-xs/4">
                <h5 class="mb-0 text-2xl text-left"><strong class="uppercase">*{{ $pcchrgcod }}*</strong></h5>
                <div class="flex flex-col text-center whitespace-nowrap">
                    <div>MMMHMC-A-PHB-QP-005 Form 1 Rev 0 Charge Slip</div>
                    <div>MARIANO MARCOS MEM HOSP. MED CTR</div>
                    <div>CHARGE SLIP / TRANSACTION SLIP</div>
                    <div class="font-bold">{{ $pcchrgcod }}</div>
                </div>
                <div class="flex flex-col text-left whitespace-nowrap">
                    <div>Dep't./Section: <span class="font-semibold">
                            {{ $rxo_header->prescription_data && $rxo_header->prescription_data->employee && $rxo_header->prescription_data->employee->dept ? $rxo_header->prescription_data->employee->dept->deptname : 'Pharmacy' }}</span>
                    </div>
                    <div>Date/Time: <span
                            class="font-semibold">{{ date('F j, Y h:i A', strtotime($rxo_header->dodate)) }}</span>
                    </div>
                    <div>Patient's Name: <span
                            class="font-semibold">{{ $rxo_header->patient ? $rxo_header->patient->fullname : 'N/A' }}</span>
                    </div>
                    <div>Hosp Number: <span
                            class="font-semibold">{{ $rxo_header->patient ? $rxo_header->patient->hpercode : '' }}</span>
                    </div>
                    <div>Ward:
                        <span class="font-semibold">{{ $wardname ? $wardname->wardname : '' }}</span>
                        <span class="font-semibold">{{ $room_name ? $room_name->rmname : '' }}
                            / {{ $toecode }}</span>
                    </div>
                    <div>Ordering Physician: <span
                            class="font-semibold">{{ $rxo_header->prescription_data && $rxo_header->prescription_data->employee ? 'Dr. ' . $rxo_header->prescription_data->employee->fullname : 'N/A' }}</span>
                    </div>
                    <div>Date/Time Ordered: <span
                            class="font-semibold">{{ $rxo_header->prescription_data ? date('F j, Y h:i A', strtotime($rxo_header->prescription_data->created_at)) : 'N/A' }}</span>
                    </div>
                </div>
            </div>
            <table class="w-full text-xs/4">
                <thead class="border border-black">
                    <tr class="border-b-2 border-b-black">
                        <th class="text-left">ITEM</th>
                        @if ($view_returns)
                            <th class="w-20 text-right">O. QTY</th>
                            <th class="text-right">R. QTY</th>
                        @else
                            <th class="w-20 text-right">QTY</th>
                        @endif
                        <th class="w-20 text-right">UNIT COST</th>
                        <th class="w-20 text-right">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rxo as $item)
                        @php
                            $amount =
                                $item->pcchrgamt + ($view_returns ? $item->pchrgup * $item->returns->sum('qty') : 0);
                            $total_amt += $amount;
                            $concat = implode(',', explode('_,', $item->dm->drug_concat));
                        @endphp
                        <tr class="border-t border-black border-x">
                            <td class="!text-2xs font-semibold text-wrap" colspan="{{ $view_returns ? 5 : 4 }}">
                                {{ $concat }}</td>
                        </tr>
                        <tr class="border-b border-black border-x">
                            @if ($view_returns)
                                <td class="text-right" colspan="2">{{ number_format($item->pchrgqty, 0) }}
                                </td>
                                <td class="text-right">{{ number_format($item->returns->sum('qty'), 0) }}
                                </td>
                            @else
                                <td class="text-right" colspan="2">
                                    {{ number_format($item->qtyissued ?? $item->pchrgqty, 0) }}</td>
                            @endif
                            <td class="text-right">{{ number_format($item->pchrgup, 2) }}</td>
                            <td class="text-right">{{ number_format($amount, 2) }}</td>
                        </tr>
                        @php $total_issued++; @endphp
                    @empty
                        <tr class="border-b border-black border-x">
                            <td colspan="{{ $view_returns ? 5 : 4 }}" class="text-center">No issued items
                                found.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr align="right" class="font-bold border border-t-2 border-black">
                        @if ($view_returns)
                            <td class="text-right" colspan="2">{{ $returned_qty }} Item/s Returned</td>
                        @endif
                        <td colspan="2">{{ number_format($total_issued) }} ITEMS</td>
                        <td colspan="2">TOTAL {{ number_format($total_amt, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
            <div class="flex flex-col py-0 my-0 text-left text-xs/4 whitespace-nowrap">
                <div>Issued by:
                    {{ $rxo_header->employee ? $rxo_header->employee->fullname : ($rxo_header->user ? $rxo_header->user->name : $rxo_header->entryby) }}
                </div>
                <div><span>Time:
                        {{ \Carbon\Carbon::create($rxo_header->dodate)->format('h:i A') }}</span></div>
                <div><span>Verified by @if (str_contains($toecode ?? '', 'ADM'))
                            Nurse/N.A.
                        @endif: _________________________</span></div>
                <div><span>Received by Patient/Watcher: ____________________</span></div>
                <div class="mt-10 italic text-right justify-content-end"><span class="border-t border-black">Signature
                        Over Printed Name</span></div>
                <div class="mt-2 text-right justify-content-end">
                    <span><input type="checkbox" class="mt-1" disabled> Counseled</span>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        function printMe() {
            var printContents = document.getElementById('print').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            window.close();
        }
    </script>
@endpush
