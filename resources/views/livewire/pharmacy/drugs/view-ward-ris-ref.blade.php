<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Ward RIS - Reference" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center space-x-2 px-3 py-1 bg-white rounded-lg shadow-sm border border-gray-200">
                <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                <span class="text-sm font-semibold text-gray-700">{{ auth()->user()->location->description }}</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="Back" icon="o-arrow-left" class="btn-sm btn-ghost"
                link="{{ route('inventory.ward-ris') }}" />
            <x-mary-button label="Print" icon="o-printer" class="btn-sm" onclick="printMe()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Reference Info Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <div>
            <span class="text-xs text-gray-500 uppercase tracking-wide">Reference No</span>
            <p class="text-lg font-bold font-mono text-blue-600">{{ $reference_no }}</p>
        </div>
    </div>

    {{-- Transaction Table --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden" id="print">
        <div class="overflow-x-auto max-h-[calc(100vh-400px)]">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Date Issued</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">From</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">To Ward</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Item</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Issued QTY</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Fund Source</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trans as $tran)
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100"
                            wire:key="ris-ref-{{ $tran->id }}">
                            <td class="py-3 px-4 text-xs text-blue-600 cursor-pointer hover:underline"
                                wire:click="viewByDate('{{ date('Y-m-d', strtotime($tran->created_at)) }}')">
                                {{ $tran->created_at() }}
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600">{{ $tran->location ? $tran->location->description : '' }}</td>
                            <td class="py-3 px-4 text-xs text-gray-600">{{ $tran->ward ? $tran->ward->ward_name : '' }}</td>
                            <td class="py-3 px-4 text-xs font-bold text-gray-900">
                                {{ $tran->drug ? $tran->drug->drug_concat() : '' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($tran->return_qty > 0)
                                    <span class="badge badge-sm badge-error">{{ number_format($tran->return_qty) }} (returned)</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 bg-blue-500 text-white rounded-lg text-xs font-bold">
                                        {{ number_format($tran->issued_qty < 1 ? 0 : $tran->issued_qty) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600">
                                {{ $tran->charge ? $tran->charge->chrgdesc : '' }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($tran->issued_qty > 0 && $tran->return_qty == 0)
                                    <x-mary-button icon="o-x-mark" class="btn-xs btn-error" tooltip="Cancel/Return"
                                        wire:click="cancelIssue({{ $tran->id }})"
                                        wire:confirm="Are you sure you want to cancel this issuance? Items will be returned to stock." spinner />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-400 font-semibold">No records found for this reference!</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@script
<script>
    window.printMe = function() {
        var printContents = document.getElementById('print').innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        window.location.reload();
    }
</script>
@endscript
