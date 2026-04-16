<div>
    <x-mary-header title="To Be Reordered Tomorrow" separator />

    <div class="flex justify-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('rx.ward') }}" class="btn btn-ghost btn-sm" wire:navigate>
            <x-mary-icon name="o-building-office-2" class="w-5 h-5" />
            Wards
        </a>
        <a href="{{ route('rx.opd') }}" class="btn btn-ghost btn-sm" wire:navigate>
            <x-mary-icon name="o-user-group" class="w-5 h-5" />
            Out Patient Department
        </a>
        <a href="{{ route('rx.er') }}" class="btn btn-ghost btn-sm" wire:navigate>
            <x-mary-icon name="o-heart" class="w-5 h-5" />
            Emergency Room
        </a>
        <a href="{{ route('rx.reactivated-today') }}" class="btn btn-ghost btn-sm" wire:navigate>
            <x-mary-icon name="o-check-circle" class="w-5 h-5" />
            Reordered Today
        </a>
        <a href="{{ route('rx.reactivates-tomorrow') }}" class="btn btn-primary btn-sm" wire:navigate>
            <x-mary-icon name="o-clock" class="w-5 h-5" />
            Reorders Tomorrow
        </a>
    </div>

    <x-mary-card>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
                <label class="label"><span class="label-text">Search</span></label>
                <x-mary-input wire:model.live.debounce.300ms="search" icon="o-magnifying-glass"
                    placeholder="Search patient, drug, encounter type..." class="input-sm" />
            </div>
            <div>
                <label class="label"><span class="label-text">Reference Date</span></label>
                <x-mary-input type="date" wire:model.live="reference_date" :max="date('Y-m-d')" class="input-sm" />
            </div>
            <div>
                <label class="label"><span class="label-text">Issued Items</span></label>
                <select class="select select-bordered select-sm w-full" wire:model.live="issued_filter">
                    <option value="all">All</option>
                    <option value="with_issued">With Issued Qty</option>
                    <option value="without_issued">No Issued Qty</option>
                </select>
            </div>
            <div>
                <label class="label"><span class="label-text">Balance</span></label>
                <div class="flex gap-2">
                    <select class="select select-bordered select-sm w-full" wire:model.live="balance_filter">
                        <option value="all">All</option>
                        <option value="with_remaining">With Remaining</option>
                        <option value="fully_issued">Fully Issued</option>
                    </select>
                    <select class="select select-bordered select-sm w-24" wire:model.live="perPage">
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Encounter</th>
                        <th>Drug</th>
                        <th>Total Qty</th>
                        <th>Issued</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Course</th>
                        <th>Source</th>
                        <th>Scheduled</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                <div class="font-medium">{{ $item->patient_name ?? 'N/A' }}</div>
                                <div class="text-xs opacity-60">{{ $item->hpercode ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <div>{{ $item->encounter_type_label ?? 'N/A' }}</div>
                                <div class="text-xs opacity-60">
                                    {{ $item->encounter_date ? \Carbon\Carbon::parse($item->encounter_date)->format('M d, Y') : 'N/A' }}
                                </div>
                            </td>
                            <td>
                                <div>{{ str_replace('_,', ' ', $item->drug_concat ?? $item->dmdcomb) }}</div>
                                <div class="text-xs opacity-60">{{ $item->schedule_text ?? 'N/A' }} | {{ $item->days_to_cover ?? 'N/A' }} day(s)</div>
                            </td>
                            <td>{{ $item->computed_total_qty ?? 'N/A' }}</td>
                            <td>{{ $item->qty_issued ?? 0 }}</td>
                            <td>{{ $item->computed_remaining_qty ?? 'N/A' }}</td>
                            <td>
                                <span class="badge badge-sm {{ ($item->stat ?? 'I') === 'A' ? 'badge-success' : 'badge-ghost' }}">
                                    {{ $item->stat ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                @if ((int) ($item->archive ?? 0) === 1)
                                    <span class="badge badge-sm badge-error">Discontinued</span>
                                @else
                                    <span class="badge badge-sm badge-success">Ongoing</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-sm badge-outline">
                                    {{ $item->reorder_source_label ?? 'Unknown' }}
                                </span>
                            </td>
                            <td class="text-xs">{{ $item->scheduled_reactivation_at ?? 'N/A' }}</td>
                            <td>
                                <div class="flex gap-1">
                                    <button
                                        wire:click="togglePrescriptionStatus({{ $item->id }})"
                                        wire:mary-confirm="Warning: change prescription item #{{ $item->id }} status from {{ $item->stat ?? 'N/A' }}?"
                                        class="btn btn-xs {{ ($item->stat ?? 'I') === 'A' ? 'btn-warning' : 'btn-success' }}">
                                        {{ ($item->stat ?? 'I') === 'A' ? 'Set Inactive' : 'Set Active' }}
                                    </button>
                                    @if ((int) ($item->archive ?? 0) !== 1)
                                        <button
                                            wire:click="discontinuePrescription({{ $item->id }})"
                                        wire:mary-confirm="Warning: discontinue prescription item #{{ $item->id }}? It will appear as Discontinued and be set inactive."
                                        class="btn btn-xs btn-error">
                                            Discontinue
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-8 text-base-content/50">No items scheduled for reorder tomorrow</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $items->links() }}
        </div>
    </x-mary-card>
</div>
