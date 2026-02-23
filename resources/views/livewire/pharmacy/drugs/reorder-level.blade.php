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
                    <label class="label"><span class="label-text text-xs">Search</span></label>
                    <input type="text" placeholder="Search generic name" class="input input-bordered input-sm"
                        wire:model.live.debounce.300ms="search" />
                </div>
                @if ($current_io > 0)
                    <span class="badge badge-info badge-sm">{{ $current_io }} pending IO request(s)</span>
                @endif
                <x-mary-button label="Bulk Request" icon="o-paper-airplane" class="btn-sm btn-primary"
                    wire:click="bulkRequest" spinner="bulkRequest"
                    wire:confirm="This action will create IO Trans requests for all items below critical level. Continue?" />
            </div>
        </x-slot:actions>
    </x-mary-header>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mt-3">
        <div class="overflow-x-auto max-h-[calc(100vh-300px)]">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Generic Drug Name</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Stock Bal</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">MA (30d WHouse)</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Prev Week Issued</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Cur Week Issued</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Avg Consumed</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Max Level (x2)</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Order QTY</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Reorder Point</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stk)
                        @php
                            $avg = $stk->average ?? 0;
                            $max_level = $avg * 2;
                            $order_qty = $stk->reorder_point ? $stk->reorder_point : max($max_level - $stk->stock_bal, 0);
                            $is_critical = $max_level > $stk->stock_bal && $stk->stock_bal >= 1;
                        @endphp
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100 {{ $is_critical ? 'bg-red-50' : '' }}">
                            <td class="py-3 px-4 text-xs font-bold text-gray-900">{{ $stk->drug_concat }}</td>
                            <td class="py-3 px-4 text-xs text-right">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold {{ $stk->stock_bal < 1 ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ number_format($stk->stock_bal) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-xs text-right text-gray-600">{{ number_format($stk->ma ?? 0) }}</td>
                            <td class="py-3 px-4 text-xs text-right text-gray-600">{{ number_format($stk->prev_average ?? 0) }}</td>
                            <td class="py-3 px-4 text-xs text-right text-gray-600">{{ number_format($stk->cur_average ?? 0) }}</td>
                            <td class="py-3 px-4 text-xs text-right text-gray-600">{{ number_format($avg) }}</td>
                            <td class="py-3 px-4 text-xs text-right text-gray-600">{{ number_format($max_level) }}</td>
                            <td class="py-3 px-4 text-xs text-right">
                                @if ($is_critical)
                                    <span class="badge badge-sm badge-error">{{ number_format($order_qty) }}</span>
                                @else
                                    <span class="text-gray-500">{{ number_format($order_qty) }}</span>
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
