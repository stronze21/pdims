            @php
                $total_issued = 0;
                $total_amt = 0;
                $pcchrgcod = $slip['pcchrgcod'];
                $rxo = $slip['rxo'];
                $rxo_header = $slip['rxo_header'];
                $toecode = $slip['toecode'];
                $encounter_suffix = $slip['encounter_suffix'];
                $wardname = $slip['wardname'];
                $room_name = $slip['room_name'];
            @endphp
            <div class="p-2 uddds-slip">
                <div class="text-xs/4 font-semibold">
                    @if ($slip['patient_number']) Patient #{{ $slip['patient_number'] }} · @endif
                    Slip {{ $slip['batch_index'] }} of {{ $slip['batch_total'] }}
                    @if ($slip['batch_index'] === $slip['batch_total']) · LAST SLIP @endif
                </div>
                <div class="flex flex-col text-xs/4">
                    <h5 class="mb-0 text-2xl text-left"><strong class="uppercase">*{{ $pcchrgcod }}*</strong></h5>
                    <div class="flex flex-col text-center receipt-wrap">
                        <div>MMMHMC-A-PHB-QP-005 Form 1 Rev 0 Charge Slip</div>
                        <div>MARIANO MARCOS MEM HOSP. MED CTR</div>
                        <div>CHARGE SLIP / TRANSACTION SLIP</div>
                        <div class="font-bold">{{ $pcchrgcod }}</div>
                    </div>
                    <div class="flex flex-col text-left receipt-wrap">
                        <div>Dep't./Section: <span class="font-semibold">Pharmacy</span></div>
                        <div>Date/Time: <span
                                class="font-semibold">{{ date('F j, Y h:i A', strtotime($rxo_header->dodate)) }}</span>
                        </div>
                        <div>Patient's Name: <span class="font-semibold">{{ $rxo_header->patient ? $rxo_header->patient->fullname : '' }}</span></div>
                        <div>Hosp Number: <span class="font-semibold">{{ $rxo_header->patient ? $rxo_header->patient->hpercode : '' }}</span></div>
                        <div>Ward:
                            <span class="font-semibold">{{ $wardname ? $wardname->wardname : '' }}</span>
                            <span class="font-semibold">{{ $room_name ? $room_name->rmname : '' }}
                                / {{ $toecode }}{{ $encounter_suffix ? ' / ' . $encounter_suffix : '' }}</span>
                        </div>
                        <div>Ordering Physician: <span
                                class="font-semibold">{{ $rxo_header->prescription_data && $rxo_header->prescription_data->employee ? 'Dr. ' . $rxo_header->prescription_data->employee->fullname : 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                <table class="w-full text-xs/4">
                    <colgroup>
                        <col style="width: 31%">
                        <col style="width: 15%">
                        <col style="width: 27%">
                        <col style="width: 27%">
                    </colgroup>
                    <thead class="border border-black">
                        <tr class="border-b-2 border-b-black">
                            <th class="text-left">ITEM</th>
                            <th class="text-right">QTY</th>
                            <th class="text-right">UNIT COST</th>
                            <th class="text-right">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rxo as $item)
                            @php
                                $amount = $item->pcchrgamt;
                                $total_amt += $amount;
                                $concat = $item->dm ? implode(',', explode('_,', $item->dm->drug_concat)) : '';
                            @endphp
                            <tr class="border-t border-black border-x">
                                <td class="!text-2xs font-semibold text-wrap" colspan="4">{{ $concat }}</td>
                            </tr>
                            <tr class="border-b border-black border-x receipt-numbers">
                                <td class="text-right" colspan="2">
                                    {{ number_format($item->qtyissued ?? $item->pchrgqty, 0) }}</td>
                                <td class="text-right">{{ number_format($item->pchrgup, 2) }}</td>
                                <td class="text-right">{{ number_format($amount, 2) }}</td>
                            </tr>
                            @php $total_issued++; @endphp
                        @empty
                            <tr class="border-b border-black border-x">
                                <td colspan="4" class="text-center">No issued items found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-bold border border-t-2 border-black">
                            <td colspan="2" class="text-right">{{ number_format($total_issued) }} ITEMS</td>
                            <td colspan="2" class="text-right">TOTAL {{ number_format($total_amt, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
                <div class="flex flex-col py-0 my-0 text-left text-xs/4 receipt-wrap">
                    <div>Issued by:
                        {{ $rxo_header->employee ? $rxo_header->employee->fullname : ($rxo_header->user ? $rxo_header->user->name : $rxo_header->entryby) }}
                    </div>
                    <div>Time: {{ \Carbon\Carbon::parse($rxo_header->dodate)->format('h:i A') }}</div>
                    <div>Verified by @if (str_contains($toecode ?? '', 'ADM')) Nurse/N.A. @endif: _________________________</div>
                    <div>Received by Patient/Watcher: ____________________</div>
                    <div class="mt-10 italic text-right"><span class="border-t border-black">Signature Over Printed Name</span></div>
                    <div class="mt-2 text-right"><label><input type="checkbox" disabled> Counseled</label></div>
                </div>
            </div>
