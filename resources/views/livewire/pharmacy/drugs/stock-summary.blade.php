<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Stock Inventory Summary" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center space-x-2 px-3 py-1 bg-white rounded-lg shadow-sm border border-gray-200">
                <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                <span class="text-sm font-semibold text-gray-700">{{ auth()->user()->location->description }}</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <div class="flex items-end space-x-2">
                @can('filter-stocks-location')
                    <div class="form-control">
                        <label class="label"><span class="label-text text-xs">Location</span></label>
                        <select class="select select-bordered select-sm" wire:model.live="location_id">
                            <option value="">All</option>
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->description }}</option>
                            @endforeach
                        </select>
                    </div>
                @endcan
                <div class="form-control">
                    <label class="label"><span class="label-text text-xs">Fund Source</span></label>
                    <select class="select select-bordered select-sm" wire:model.live="selected_fund">
                        <option value="">All</option>
                        @foreach ($charges as $charge)
                            <option value="{{ $charge->chrgcode }},{{ $charge->chrgdesc }}">
                                {{ $charge->chrgdesc }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text text-xs">Search</span></label>
                    <input type="text" placeholder="Search generic name" class="input input-bordered input-sm"
                        wire:model.live.debounce.300ms="search" />
                </div>
                <x-mary-button label="Export" icon="o-arrow-down-tray" class="btn-sm btn-info"
                    onclick="ExportToExcel('xlsx')" />
            </div>
        </x-slot:actions>
    </x-mary-header>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mt-3">
        <div class="overflow-x-auto max-h-[calc(100vh-300px)]">
            <table class="table table-sm" id="table">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Source of Fund</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Generic</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Lot No</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Expiration Date</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-end">Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stk)
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100">
                            <td class="py-3 px-4 font-semibold text-xs">{{ $stk->chrgdesc }}</td>
                            <td class="py-3 px-4 font-bold text-xs text-gray-900">{{ $stk->drug_concat }}</td>
                            <td class="py-3 px-4 text-xs text-gray-600">{{ $stk->lot_no }}</td>
                            <td class="py-3 px-4 text-xs text-gray-600">{{ $stk->exp_date }}</td>
                            <td class="py-3 px-4 text-end">
                                <span class="inline-flex items-center px-3 py-1 bg-blue-500 text-white rounded-lg text-xs font-bold">
                                    {{ number_format($stk->stock_bal, 0) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-gray-400 font-semibold">No record found!</td>
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
            var wb = XLSX.utils.table_to_book(elt, { sheet: "sheet1" });
            return dl ?
                XLSX.write(wb, { bookType: type, bookSST: true, type: 'base64' }) :
                XLSX.writeFile(wb, fn || ('Stocks_summary.' + (type || 'xlsx')));
        }
    </script>
@endpush
