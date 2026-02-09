@php
    $line_items = 0;
    $total_amt = 0;
    $total_returned = 0;
@endphp
<div class="container max-w-xl mx-auto mt-5">
    <div class="flex justify-between mb-3 align-middle no-print">
        <button class="btn btn-sm btn-primary" onclick="printMe()">Print</button>
    </div>
    <div id="print" class="bg-white w-box-border">
        <div class="p-2">
            <div class="flex flex-col text-xs/4">
                <div class="flex flex-col text-center whitespace-nowrap">
                    <div>MARIANO MARCOS MEM HOSP. MED CTR</div>
                    <div>ISSUED WITH RETURN</div>
                </div>
                <div class="flex flex-col text-left whitespace-nowrap">
                    <div>
                        <span>Dep't./Section: </span>
                        <span class="font-semibold">Pharmacy</span>
                    </div>
                    @if ($patient)
                        <div>Patient's Name: <span
                                class="font-semibold">{{ $patient->patlast . ', ' . $patient->patfirst . ' ' . ($patient->patsuffix ?? '') . ' ' . $patient->patmiddle }}</span>
                        </div>
                        <div>Hosp Number: <span class="font-semibold">{{ $patient->hpercode }}</span></div>
                    @endif
                </div>
            </div>
            <table class="w-full text-xs/4">
                <thead class="border border-black">
                    <tr class="border-b-2 border-b-black">
                        <th class="w-2/6 text-left">
                            <div class="flex flex-col text-3xs">
                                <span class="text-3xs">ISSUED DATE</span>
                                <span class="text-3xs">CHARGE SLIP #</span>
                                <span class="text-3xs">ITEM</span>
                            </div>
                        </th>
                        <th class="w-1/6 text-right text-3xs">QTY</th>
                        <th class="w-1/6 text-right text-3xs">RTN. QTY</th>
                        <th class="w-1/6 text-right text-3xs">UNIT COST</th>
                        <th class="w-1/6 text-right text-3xs">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php
                            $concat = implode(',', explode('_,', $item->drug_concat));
                        @endphp
                        <tr class="border-t border-black border-x">
                            <td class="text-3xs text-wrap">
                                {{ date('F j, Y h:i A', strtotime($item->issuedte)) }}
                            </td>
                            <td class="text-right text-3xs">{{ number_format($item->total_issued, 0) }}</td>
                            <td class="text-right text-3xs">{{ number_format($item->total_returns, 0) }}</td>
                            <td class="text-right text-3xs">{{ number_format($item->pchrgup, 2) }}</td>
                            <td class="text-right text-3xs">{{ number_format($item->pchrgamt, 2) }}</td>
                        </tr>
                        <tr class="border-b border-black border-x">
                            <td class="text-3xs text-wrap" colspan="5">
                                <div class="flex flex-col text-3xs">
                                    <span class="text-3xs">{{ $item->pcchrgcod }}</span>
                                    <span class="text-3xs">{{ $concat }}</span>
                                </div>
                            </td>
                        </tr>
                        @php
                            $line_items++;
                            $total_amt += $item->pchrgamt;
                            $total_returned += $item->total_returns ? 1 : 0;
                        @endphp
                    @endforeach
                </tbody>
                <tfoot>
                    <tr align="right" class="font-bold border border-t-2 border-black">
                        <td class="text-3xs">{{ number_format($line_items) }} ITEM/S</td>
                        <td class="text-right text-3xs" colspan="2">{{ $total_returned }} RTN ITEM/S</td>
                        <td colspan="2" class="text-3xs">TOTAL {{ number_format($total_amt, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
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
            document.body.innerHTML = originalContents;
            history.go(-1);
        }
    </script>
@endpush
