<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Reorder Levels" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center space-x-2 px-3 py-1 bg-white rounded-lg shadow-sm border border-gray-200">
                <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                <span class="text-sm font-semibold text-gray-700">{{ auth()->user()->location->description }}</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <div class="flex items-end space-x-2">
                <div class="form-control">
                    <label class="label"><span class="label-text text-xs">Location</span></label>
                    <select class="select select-bordered select-sm" wire:model.live="location_id">
                        <option value="">All</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->description }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text text-xs">Search</span></label>
                    <input type="text" placeholder="Search generic name" class="input input-bordered input-sm"
                        wire:model.live.debounce.300ms="search" />
                </div>
                @if ($current_io > 0)
                    <span class="badge badge-info badge-sm">{{ $current_io }} pending IO request(s)</span>
                @endif
                @if (!$current_io)
                    <x-mary-button label="Bulk Request" icon="o-paper-airplane" class="btn-sm btn-primary"
                        wire:click="bulkRequest" spinner="bulkRequest"
                        wire:confirm="Bulk request items below reorder-level. Continue?" />
                @endif
            </div>
        </x-slot:actions>
    </x-mary-header>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mt-3">
        <div class="overflow-x-auto max-h-[calc(100vh-300px)]">
            <table class="table table-sm" id="table">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Generic</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Remaining</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right bg-blue-800/40">30-day Moving Average</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right bg-blue-800/40">Critical Level</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Prev. Week Ave.</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Per Week Average</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Max Level</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Stock Order QTY</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Reorder Point</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stk)
                        @php
                            $max_level = $stk->average ? $stk->average * 2 : 0;
                            $critical = $stk->ma ? $stk->ma * 1.5 - $stk->stock_bal : 0;
                            $order_qty = $max_level > $stk->stock_bal
                                ? number_format($max_level - $stk->stock_bal)
                                : ($stk->stock_bal < 1 ? '' : 'over');
                            $weekly_average = $stk->cur_average && $stk->prev_average
                                ? (($stk->cur_average - $stk->prev_average) / $stk->prev_average) * 100
                                : 0;
                        @endphp
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100">
                            <td class="py-3 px-4 text-xs font-bold text-gray-900">{{ $stk->drug_concat }}</td>
                            <td class="py-3 px-4 text-xs text-right">
                                {{ number_format($stk->stock_bal) }}
                                @if ($stk->reorder_point)
                                    @if ($stk->reorder_point <= $stk->stock_bal)
                                        <span class="text-primary" title="Above reorder point">
                                            <x-mary-icon name="o-check-circle" class="w-4 h-4 inline" />
                                        </span>
                                    @else
                                        <span class="text-error" title="Below set reorder point">
                                            <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4 inline" />
                                        </span>
                                    @endif
                                @else
                                    <span class="text-warning" title="No reorder point set">
                                        <x-mary-icon name="o-pause-circle" class="w-4 h-4 inline" />
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-right">{{ $stk->ma ? number_format($stk->ma, 2) : '' }}</td>
                            <td class="py-3 px-4 text-xs text-right">
                                {{ $critical ? ($critical < 1 ? 'over' : number_format($critical, 2)) : '' }}
                            </td>
                            <td class="py-3 px-4 text-xs text-right">{{ $stk->average ? number_format($stk->average, 2) : '' }}</td>
                            <td class="py-3 px-4 text-xs text-right">
                                @if ($weekly_average)
                                    <span class="text-info cursor-help" title="(({{ number_format($stk->cur_average) }} - {{ number_format($stk->prev_average) }}) / {{ number_format($stk->prev_average) }}) * 100">
                                        <x-mary-icon name="o-information-circle" class="w-4 h-4 inline" />
                                    </span>
                                    {{ number_format($weekly_average) }}%
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-right">{{ $stk->average ? number_format($max_level) : '' }}</td>
                            <td class="py-3 px-4 text-xs text-right">
                                {{ $order_qty }}
                                @if ($order_qty > 0 && $order_qty != 'over' && $current_io)
                                    <span class="badge badge-xs badge-info">[requested]</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center" x-data="{ val: '{{ $stk->reorder_point ?? '' }}' }">
                                <input type="number" class="input input-xs input-bordered w-20 text-center"
                                    x-model="val"
                                    @change="$wire.updateReorder('{{ $stk->dmdcomb }}', '{{ $stk->dmdctr }}', val)" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-400 font-semibold">No record found!</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
