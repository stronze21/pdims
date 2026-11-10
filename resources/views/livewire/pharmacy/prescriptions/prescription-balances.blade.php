<div>
    <x-mary-header title="Prescription Balances"
        subtitle="Prescription items with quantity remaining to issue" separator />

    <x-mary-card>
        <div class="mb-4 flex flex-wrap items-end gap-4">
            <label class="form-control w-full sm:max-w-md">
                <span class="label label-text">Search</span>
                <input type="search" class="input input-bordered w-full"
                    placeholder="Patient name, hospital number, encounter, or drug"
                    wire:model.live.debounce.400ms="search" />
            </label>

            <label class="form-control w-full sm:w-48">
                <span class="label label-text">Item status</span>
                <select class="select select-bordered w-full" wire:model.live="status">
                    <option value="all">Active and inactive</option>
                    <option value="A">Active</option>
                    <option value="I">Inactive</option>
                </select>
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th scope="col">Patient</th>
                        <th scope="col">Encounter</th>
                        <th scope="col">Prescription</th>
                        <th scope="col">Drug</th>
                        <th scope="col">Instructions</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Ordered</th>
                        <th scope="col" class="text-right">Issued</th>
                        <th scope="col" class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($prescriptions as $item)
                        <tr wire:key="prescription-balance-{{ $item->id }}">
                            <td>
                                <div>{{ trim($item->patlast.' '.$item->patfirst) ?: '—' }}</div>
                                <div class="text-xs text-base-content/70">{{ $item->hpercode }}</div>
                            </td>
                            <td>
                                <div>{{ $item->toecode }}</div>
                                <div class="text-xs text-base-content/70">{{ $item->enccode }}</div>
                            </td>
                            <td>
                                <div>#{{ $item->presc_id }}</div>
                                <div class="whitespace-nowrap text-xs text-base-content/70">
                                    {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('M j, Y') : '—' }}
                                </div>
                            </td>
                            <td>{{ $item->drug_concat ?: $item->dmdcomb.' / '.$item->dmdctr }}</td>
                            <td>
                                <div>
                                    @if ($item->instruction_qty !== null)
                                        {{ round($item->instruction_qty, 6) }} per intake
                                    @else
                                        <span class="text-base-content/70">Qty per intake unavailable</span>
                                    @endif
                                    @if (filled($item->remark))
                                        · {{ $item->remark }}
                                    @endif
                                    @if ($item->instruction_days !== null)
                                        · {{ $item->instruction_days }} {{ \Illuminate\Support\Str::plural('day', $item->instruction_days) }}
                                    @endif
                                </div>
                                @if (filled($item->addtl_remarks))
                                    <div class="text-xs text-base-content/70">{{ $item->addtl_remarks }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-sm {{ $item->stat === 'A' ? 'badge-success' : 'badge-ghost' }}">
                                    {{ $item->stat === 'A' ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right tabular-nums">{{ $item->ordered_qty + 0 }}</td>
                            <td class="text-right tabular-nums">{{ $item->issued_qty + 0 }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ $item->balance + 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center">No prescription items with a balance match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $prescriptions->links() }}</div>
    </x-mary-card>
</div>
