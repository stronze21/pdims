<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Reorder Levels (Computed)" separator progress-indicator>
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
                @if ($current_io > 0)
                    <span class="badge badge-info badge-sm">{{ $current_io }} pending IO request(s)</span>
                @endif
            </div>
        </x-slot:actions>
    </x-mary-header>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mt-3">
        <div class="overflow-x-auto max-h-[calc(100vh-300px)]">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4">Generic</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Stock Balance</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">30-Day Moving Average</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-right">Reorder Level</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stk)
                        @php
                            $row_class = '';
                            if ($stk->status == 'CRITICAL') {
                                $row_class = 'bg-red-50';
                            } elseif ($stk->status == 'NEAR CRITICAL') {
                                $row_class = 'bg-yellow-50';
                            }
                        @endphp
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100 {{ $row_class }}">
                            <td class="py-3 px-4 text-xs font-bold text-gray-900">{{ $stk->drug_concat }}</td>
                            <td class="py-3 px-4 text-xs text-right">
                                {{ number_format($stk->stock_bal) }}
                            </td>
                            <td class="py-3 px-4 text-xs text-right">
                                {{ $stk->max_level && $stk->max_level > 0 ? number_format($stk->max_level, 2) : '' }}
                            </td>
                            <td class="py-3 px-4 text-xs text-right">
                                {{ $stk->critical && $stk->critical > 0 ? number_format($stk->critical, 2) : '' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($stk->status == 'CRITICAL')
                                    <span class="badge badge-sm badge-error">CRITICAL</span>
                                @elseif ($stk->status == 'NEAR CRITICAL')
                                    <span class="badge badge-sm badge-warning">NEAR CRITICAL</span>
                                @else
                                    <span class="badge badge-sm badge-success">NORMAL</span>
                                @endif
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
