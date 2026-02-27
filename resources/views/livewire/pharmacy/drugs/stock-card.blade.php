<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Stock Card" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-end h-full">
                <div class="flex items-center gap-2 px-3 py-1
                    bg-white rounded-lg shadow-sm border">
                    <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                    <span class="text-sm font-semibold">
                        {{ auth()->user()->location->description }}
                    </span>
                </div>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <div class="flex items-end space-x-2">
                <x-mary-button icon="o-arrow-down-tray" class="btn-sm btn-info" onclick="ExportToExcel('xlsx')"
                    tooltip="Export" />
                <x-mary-button icon="o-printer" class="btn-sm btn-primary" onclick="printMe()" tooltip="Print" />
                @can('admin')
                    <x-mary-button icon="o-arrow-path" class="btn-sm btn-warning" wire:click="initCard" spinner="initCard"
                        tooltip="Init Card" />
                @endcan
            </div>
        </x-slot:actions>
    </x-mary-header>

    <div class="flex flex-wrap items-end gap-2 mb-4">
        @can('filter-stocks-location')
            <div class="form-control">
                <label class="label"><span class="label-text text-xs">Location</span></label>
                <select class="select select-bordered select-sm" wire:model.live="location_id">
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->description }}</option>
                    @endforeach
                </select>
            </div>
        @endcan
        <div class="form-control">
            <label class="label"><span class="label-text text-xs">Fund Source</span></label>
            <select class="select select-bordered select-sm" wire:model.live="selected_fund">
                <option value="">N/A</option>
                @foreach ($fund_sources as $fund)
                    <option value="{{ $fund->chrgcode }},{{ $fund->chrgdesc }}">{{ $fund->chrgdesc }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-control">
            <label class="label"><span class="label-text text-xs">Drug</span></label>
            <select class="select select-bordered select-sm max-w-xs" wire:model.live="selected_drug">
                <option value="">N/A</option>
                @foreach ($drugs as $stock_item)
                    <option value="{{ $stock_item->dmdcomb }},{{ $stock_item->dmdctr }}">
                        {{ $stock_item->drug_concat() }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-control">
            <label class="label"><span class="label-text text-xs">From</span></label>
            <input type="date" class="input input-sm input-bordered" wire:model.live.debounce.300ms="date_from" />
        </div>
        <div class="form-control">
            <label class="label"><span class="label-text text-xs">To</span></label>
            <input type="date" class="input input-sm input-bordered" wire:model.live.debounce.300ms="date_to" />
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div id="print" class="w-full">
            <table class="table table-sm w-full" id="table">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                    @if ($chrgdesc)
                        <tr>
                            <td colspan="8" class="text-white text-center font-bold py-2">{{ $chrgdesc }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Drug</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Date</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Reference</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Beginning Balance
                        </th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Receipt</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Issued</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Pullout</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cards as $card)
                        @php
                            $ref = $card->reference;
                            $receipt = $card->rec;
                            $issued = $card->iss;
                            $pullout_qty = $card->pullout_qty;
                            $total = $ref + $receipt - ($issued + $pullout_qty);
                        @endphp
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100">
                            <td class="py-3 px-4 text-xs">
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-900">{{ $card->drug_concat }}</span>
                                    <span class="text-gray-500">{{ $card->charge->chrgdesc ?? '' }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-xs text-right text-gray-600">{{ $card->stock_date }}</td>
                            <td class="py-3 px-4 text-xs text-right text-gray-600">{{ $card->io_trans_ref_no }}</td>
                            <td class="py-3 px-4 text-xs text-right font-semibold">
                                {{ number_format($card->reference) }}</td>
                            <td class="py-3 px-4 text-xs text-right text-green-600">{{ number_format($card->rec) }}
                            </td>
                            <td class="py-3 px-4 text-xs text-right text-red-600">{{ number_format($card->iss) }}</td>
                            <td class="py-3 px-4 text-xs text-right text-orange-600">
                                {{ number_format($card->pullout_qty) }}</td>
                            <td class="py-3 px-4 text-right">
                                <span
                                    class="inline-flex items-center px-3 py-1 bg-blue-500 text-white rounded-lg text-xs font-bold">
                                    {{ number_format($total) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400 font-semibold">No record found!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
    <script src="https://unpkg.com/xlsx@0.15.1/dist/xlsx.full.min.js"></script>
    <script>
        function ExportToExcel(type, fn, dl) {
            var elt = document.getElementById('table');
            var wb = XLSX.utils.table_to_book(elt, {
                sheet: "sheet1"
            });
            return dl ?
                XLSX.write(wb, {
                    bookType: type,
                    bookSST: true,
                    type: 'base64'
                }) :
                XLSX.writeFile(wb, fn || ('Stock_Card.' + (type || 'xlsx')));
        }

        function printMe() {
            var printContents = document.getElementById('print').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
        }
    </script>
@endpush
