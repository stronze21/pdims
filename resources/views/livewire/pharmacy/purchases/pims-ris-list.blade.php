<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Requisition and Issue Slips - Pharmacy (PIMS)" separator progress-indicator>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <select wire:model.live="perPage" class="select select-bordered select-sm">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </x-slot:actions>
    </x-mary-header>

    {{-- Filters --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <x-mary-input icon="o-magnifying-glass" wire:model.live.debounce.300ms="search"
            placeholder="Search by RIS#, purpose, name..." size="sm" clearable />
        <x-mary-select wire:model.live="statusFilter"
            :options="[
                ['id' => 'all', 'name' => 'All Status'],
                ['id' => 'approved', 'name' => 'Approved'],
                ['id' => 'pending', 'name' => 'Pending'],
                ['id' => 'issued', 'name' => 'Issued'],
                ['id' => 'transferred', 'name' => 'Transferred to Delivery'],
                ['id' => 'not-transferred', 'name' => 'Not Transferred'],
            ]"
            option-value="id" option-label="name" size="sm" />
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm table-zebra">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                    <tr>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">
                            <a wire:click.prevent="sortBy('tbl_ris.risno')" href="#" class="hover:text-blue-200 cursor-pointer">
                                RIS No.
                                @if ($sortField === 'tbl_ris.risno')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">
                            <a wire:click.prevent="sortBy('tbl_ris.risdate')" href="#" class="hover:text-blue-200 cursor-pointer">
                                RIS Date
                                @if ($sortField === 'tbl_ris.risdate')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Purpose</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Items / Amount</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Requested By</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Status</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4">Delivery Status</th>
                        <th class="text-white text-xs font-bold uppercase tracking-wide py-4 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($risItems as $item)
                        <tr class="hover:bg-blue-50/50 transition-colors">
                            <td class="py-3 px-4 text-xs font-medium">{{ $item->risno }}</td>
                            <td class="py-3 px-4 text-xs whitespace-nowrap">{{ $item->formatted_risdate }}</td>
                            <td class="py-3 px-4 text-xs max-w-xs truncate">{{ $item->purpose }}</td>
                            <td class="py-3 px-4 text-xs">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ $item->item_count ?? 0 }} Item(s)</span>
                                    <span class="text-xs text-green-700 font-bold">
                                        {{ number_format($item->total_amount ?? 0, 2) }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-xs">{{ $item->requested_by }}</td>
                            <td class="py-3 px-4 text-xs">
                                @if ($item->apprvstat === 'A' && $item->issuedstat === 'I')
                                    <span class="badge badge-success badge-sm">Issued</span>
                                @elseif($item->apprvstat === 'A')
                                    <span class="badge badge-info badge-sm">Approved</span>
                                @elseif($item->apprvstat === 'P')
                                    <span class="badge badge-warning badge-sm">Pending</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">Draft</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs">
                                <span class="badge badge-sm {{ $this->getDeliveryStatusClass($item) }}">
                                    {{ $this->getDeliveryStatus($item) }}
                                </span>
                                @if ($item->transferred_to_pdims)
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($item->transferred_at)->format('M d, Y H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-center">
                                <div class="flex justify-center gap-1">
                                    <a href="{{ route('purchases.ris-show', $item->risid) }}"
                                        class="btn btn-xs btn-primary">View</a>
                                    @if ($item->transferred_to_pdims)
                                        <a href="{{ route('purchases.delivery-view', $item->transferred_to_pdims) }}"
                                            class="btn btn-xs btn-secondary">Delivery</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="flex flex-col items-center justify-center py-12 opacity-40">
                                    <x-mary-icon name="o-document-text" class="w-12 h-12 mb-2" />
                                    <p class="text-sm">No RIS records found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $risItems->links() }}
        </div>
    </div>
</div>
