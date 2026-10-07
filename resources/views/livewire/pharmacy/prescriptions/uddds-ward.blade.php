<div class="uddds-queue flex flex-col px-4 py-6 mx-auto max-w-screen-2xl sm:px-6"
    wire:init="loadQueue"
    wire:key="uddds-ward-queue"
    >

    <nav class="flex flex-wrap justify-center gap-2 mb-4" aria-label="Rx and orders">
        <a href="{{ route('rx.ward') }}" class="btn btn-ghost btn-sm" wire:navigate>Wards</a>
        <a href="{{ route('rx.uddds') }}" class="btn btn-primary btn-sm" aria-current="page">UDDDS</a>
        <a href="{{ route('rx.opd') }}" class="btn btn-ghost btn-sm" wire:navigate>Out Patient Department</a>
        <a href="{{ route('rx.er') }}" class="btn btn-ghost btn-sm" wire:navigate>Emergency Room</a>
    </nav>
    <style>
        .uddds-queue .uddds-patient-header { background: var(--color-base-200, #f1f5f9); color: inherit; }
        .uddds-queue .btn:disabled { opacity: 0.5; }
        .uddds-queue .uddds-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; padding-bottom: 12px; }
        .uddds-queue .uddds-filters { display: grid; grid-template-columns: minmax(0, 1fr); gap: 8px; width: 100%; }
        .uddds-queue .uddds-filters > label { min-width: 0; }
        .uddds-queue .uddds-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        @media (min-width: 640px) {
            .uddds-queue .uddds-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (min-width: 1024px) {
            .uddds-queue .uddds-filters { grid-template-columns: 170px 150px 170px 180px; width: auto; }
            .uddds-queue .uddds-actions { margin-left: auto; }
        }
        .uddds-queue .uddds-loading-modal { position: fixed; inset: 0; z-index: 900; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, 0.5); }
        .uddds-queue .uddds-loading-panel { width: 100%; max-width: 360px; padding: 24px; border-radius: 8px; background: #fff; color: #0f172a; text-align: center; box-shadow: 0 12px 36px rgba(0, 0, 0, 0.2); }
        .uddds-queue .uddds-loading-spinner { display: inline-block; width: 32px; height: 32px; margin-bottom: 12px; border: 3px solid #cbd5e1; border-top-color: #047857; border-radius: 50%; animation: uddds-loading-spin 0.8s linear infinite; }
        @keyframes uddds-loading-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .uddds-queue .uddds-loading-spinner { animation: none; } }
        .uddds-queue .uddds-fund-modal { position: fixed; inset: 0; z-index: 850; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(15,23,42,0.5); }
        .uddds-queue .uddds-fund-panel { width: 100%; max-width: 680px; padding: 24px; background: #fff; color: #0f172a; border-radius: 8px; }
        .uddds-queue .uddds-fund-list { max-height: 60vh; overflow-y: auto; margin-top: 12px; }
    </style>
    @if ($fundModalOpen)
        <div class="uddds-fund-modal" role="dialog" aria-modal="true" aria-labelledby="uddds-fund-title" wire:key="uddds-fund-modal">
            <div class="uddds-fund-panel">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="uddds-fund-title" class="text-lg font-semibold">Fund sources for selected items</h2>
                    <button type="button" class="btn btn-sm btn-outline btn-primary" wire:click="closeFundModal">Close</button>
                </div>
                @if ($processingProblem)
                    <p class="mt-3 text-sm text-error" role="alert">{{ $processingProblem }}</p>
                @endif
                <div class="uddds-fund-list">
                    @forelse ($fundGroups as $fundGroup)
                        @php $item = $fundGroup['item']; @endphp
                        <section class="py-3 border-b border-base-300">
                            <h3 class="font-semibold">{{ str_replace('_', '', $item->drug_concat) }}</h3>
                            <p class="text-sm">{{ $item->chrgdesc }} · {{ count($fundGroup['patients']) }} patients · {{ $fundGroup['qty'] }} needed · {{ $item->current_available }} available</p>
                            @if ($item->current_available >= $fundGroup['qty'])
                                <p class="mt-2 text-sm">Current fund has sufficient stock for this selection.</p>
                            @elseif (!empty($item->alternate_funds))
                                    @php
                                        $fundChoices = array_values((array) ($fallback_sources[$item->fallback_key] ?? []));
                                        $fundNeed = $fundGroup['qty'];
                                        $fundCoverage = $item->current_available;
                                        $usedFunds = [];
                                    @endphp
                                    @for ($fundIndex = 0; $fundIndex < count($item->alternate_funds); $fundIndex++)
                                        @if ($fundIndex > 0 && ($fundCoverage >= $fundNeed || empty($fundChoices[$fundIndex - 1])))
                                            @break
                                        @endif
                                        <label class="block mt-2">
                                            <span class="block mb-1 font-medium text-base-content/80">{{ $fundIndex === 0 ? 'Alternate fund for shortage' : 'Next fund for remaining shortage' }}</span>
                                            <select class="w-full select select-bordered select-sm" wire:model.live="fallback_sources.{{ $item->fallback_key }}.{{ $fundIndex }}" wire:loading.attr="disabled">
                                                <option value="">Choose one fund source</option>
                                                @foreach ($item->alternate_funds as $fund)
                                                    @if (!in_array($fund['code'], $usedFunds, true))
                                                        <option value="{{ $fund['code'] }}">{{ $fund['name'] }} — {{ $fund['available'] }} available</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </label>
                                        @php
                                            $chosenFund = $fundChoices[$fundIndex] ?? '';
                                            foreach ($item->alternate_funds as $option) {
                                                if ($option['code'] === $chosenFund && !in_array($chosenFund, $usedFunds, true)) {
                                                    $fundCoverage += $option['available'];
                                                    $usedFunds[] = $chosenFund;
                                                }
                                            }
                                        @endphp
                                    @endfor
                                    @if (!empty($usedFunds))
                                        <p class="mt-1 {{ $fundCoverage < $fundNeed ? 'text-error' : 'text-base-content/80' }}">Combined available: {{ $fundCoverage }} / needed: {{ $fundNeed }}.</p>
                                    @endif

                            @else
                                <p class="mt-2 text-sm text-error">No alternate fund available, or this item already has a charge that must be adjusted separately.</p>
                            @endif
                        </section>
                    @empty
                        <p class="py-4">Select pending items first.</p>
                    @endforelse
                </div>
                <button type="button" class="btn btn-sm btn-success mt-4" wire:click="continueCharge" wire:loading.attr="disabled">Continue to Charge &amp; Issue</button>
            </div>
        </div>
    @endif
    <div class="uddds-loading-modal" wire:loading.flex style="display: {{ $queueLoaded ? 'none' : 'flex' }};"
        role="dialog" aria-modal="true" aria-labelledby="uddds-loading-title" wire:key="uddds-loading-modal">
        <div class="uddds-loading-panel" role="status" aria-live="polite">
            <span class="uddds-loading-spinner" aria-hidden="true"></span>
            <h2 id="uddds-loading-title" class="text-lg font-semibold">{{ $queueLoaded ? 'Updating UDDDS queue' : 'Loading UDDDS queue' }}</h2>
            <p class="mt-2 text-sm">Please wait while the request completes.</p>
            <p class="mt-2 text-sm" wire:loading wire:target="processSelected,readyToBill">Charging and issuing selected items…</p>
            <p class="mt-2 text-sm" wire:loading wire:target="wardcode,selected_date,queue_view,status_filter,showToday,prepareCharge,continueCharge">Applying filters and checking stock…</p>
            <p class="mt-2 text-sm" wire:loading wire:target="selectPending,toggleSelectAll,selected_items,fallback_sources,selected_print_patients,togglePrintPatients">Updating item selection…</p>
            <p class="mt-2 text-sm" wire:loading wire:target="view_enctr">Opening patient encounter…</p>
        </div>
    </div>
    <div class="flex flex-col gap-3 mb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-base-content">UDDDS ward queue</h1>
            <p class="mt-1 text-sm text-base-content/70">{{ $queue_view === 'processed' ? 'Existing UDDDS charge slips' : 'Unit-dose orders and eligible enrollments' }} for {{ $displayDate }}.</p>
        </div>
        <div class="flex items-center gap-3 text-xs text-base-content/70" aria-label="Queue summary">
            <span><strong class="font-semibold text-base-content">{{ $eligibleCount }}</strong> eligible</span>
            <span class="w-px h-4 bg-base-300" aria-hidden="true"></span>
            <span><strong class="font-semibold text-base-content">{{ $billableCount }}</strong> ready to bill</span>
            <span class="w-px h-4 bg-base-300" aria-hidden="true"></span>
            <span><strong class="font-semibold text-base-content">{{ $issuedCount }}</strong> issued</span>
        </div>
    </div>

    <div class="uddds-toolbar border-b border-base-300">
        <div class="uddds-filters">
            <label class="form-control">
                <span class="pb-1 text-xs font-medium label-text">View</span>
                <select wire:model.live="queue_view" class="w-full select select-bordered select-sm">
                    <option value="active">Active queue</option>
                    <option value="processed">Processed / Charge Slips</option>
                </select>
            </label>
            <label class="form-control">
                <span class="pb-1 text-xs font-medium label-text">Status</span>
                <select wire:model.live="status_filter" class="w-full select select-bordered select-sm">
                    <option value="all">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="charged">Charged / Unissued</option>
                    <option value="issued">Issued</option>
                    <option value="eligible">Eligible</option>
                </select>
            </label>
            <label class="form-control">
                <span class="pb-1 text-xs font-medium label-text">Service date</span>
                <div class="flex gap-2">
                    <input type="date" wire:model.live="selected_date" class="w-full input input-bordered input-sm"
                        aria-label="Service date" />
                    @if (! $isToday)
                        <button type="button" class="btn btn-sm btn-outline btn-primary" wire:click="showToday">
                            Today
                        </button>
                    @endif
                </div>
            </label>
            <label class="form-control">
                <span class="pb-1 text-xs font-medium label-text">Ward</span>
                <select wire:model.live="wardcode" class="w-full select select-bordered select-sm">
                    <option value="">All wards</option>
                    @foreach ($wards as $ward)
                        <option value="{{ $ward->wardcode }}">{{ $ward->wardname }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="uddds-actions">
            <button type="button" class="btn btn-sm btn-outline btn-primary" wire:click="openPrintModal" wire:loading.attr="disabled">Print Patients</button>
            <button type="button" class="btn btn-sm btn-success"
                wire:click="prepareCharge"
                @if (empty($selected_items)) disabled @endif wire:loading.attr="disabled">
                Charge &amp; Issue Selected
            </button>
            <button type="button" class="btn btn-sm btn-outline btn-primary"
                wire:click="selectPending" wire:loading.attr="disabled"
                @if (!$hasActionableItems) disabled @endif>Select Pending / Unissued</button>
            <button type="button" class="btn btn-sm btn-outline"
                wire:click="toggleSelectAll"
                wire:loading.attr="disabled"
                @if (! $hasActionableItems) disabled @endif>
                <span>{{ $allSelected ? 'Clear selection' : 'Select all' }}</span>
            </button>
        </div>
    </div>

    @if ($processingProblem)
        <div class="mt-4 alert alert-error" role="alert">{{ $processingProblem }}</div>
    @endif
    @if ($printModalOpen)
        <div class="uddds-fund-modal" role="dialog" aria-modal="true" aria-labelledby="uddds-print-title" wire:key="uddds-print-modal">
            <div class="uddds-fund-panel">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="uddds-print-title" class="text-lg font-semibold">Print patients</h2>
                    <button type="button" class="btn btn-sm btn-outline btn-primary" wire:click="closePrintModal">Close</button>
                </div>
    <div class="flex flex-wrap items-center gap-2 mt-3 mb-3" aria-label="Patient batch printing">
        <label class="text-sm">Patient numbers
            <input type="text" wire:model="print_range" placeholder="4-10 or 4,7,10" class="input input-bordered input-sm" aria-label="Patient numbers for batch printing">
        </label>
        <button type="button" class="btn btn-sm btn-outline btn-primary" wire:click="applyPrintRange" wire:loading.attr="disabled">Select numbers</button>
        <span class="text-sm font-medium">Patient printing: {{ $selectedPrintCount }} selected</span>
        <button type="button" class="btn btn-sm btn-outline btn-primary" wire:click="togglePrintPatients" wire:loading.attr="disabled" @if (!$hasPrintablePatients) disabled @endif>
            {{ $allPrintPatientsSelected ? 'Clear print selection' : 'Select all patients' }}
        </button>
        @if ($selectedPrintUrl)
            <a href="{{ $selectedPrintUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline btn-primary">Print selected patients</a>
        @else
            <button type="button" class="btn btn-sm btn-outline btn-primary" disabled>Print selected patients</button>
        @endif
    </div>


                @if ($printSelectionProblem)
                    <p class="mt-2 text-sm text-error" role="alert">{{ $printSelectionProblem }}</p>
                @endif
                <div class="uddds-fund-list">
                    @forelse ($patients as $printPatient)
                        <label class="flex items-center gap-3 py-3 border-b border-base-300">
                            <input type="checkbox" wire:model.live="selected_print_patients" value="{{ $printPatient['enccode'] }}"
                                class="h-4 w-4 accent-emerald-700" @if (!$printPatient['reprint_url']) disabled @endif wire:loading.attr="disabled">
                            <span><strong>#{{ $printPatient['number'] }} · {{ $printPatient['name'] }}</strong>
                                <span class="block text-xs text-base-content/70">{{ $printPatient['hpercode'] }} · {{ $printPatient['wardname'] }} {{ $printPatient['rmname'] }}{{ $printPatient['reprint_url'] ? '' : ' · No charge slip' }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="py-4">No patients found.</p>
                    @endforelse
                </div>
                @if ($lastBatchPrintUrl)
                    <a href="{{ $lastBatchPrintUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline btn-primary mt-3">Open Last Batch</a>
                @endif
            </div>
        </div>
    @endif

    @if (! $udddsReady)
        <div class="mt-4 alert alert-warning">
            <span>{{ $udddsMessage }}</span>
        </div>
    @endif

    @if (!$queueLoaded)
        <div class="p-10 mt-6 text-center text-base-content/60" role="status">
            <span class="loading loading-spinner loading-sm motion-reduce:animate-none" aria-hidden="true"></span> Loading UDDDS orders...
        </div>
    @else
    @forelse ($patients as $patient)
        <section class="mt-4 overflow-hidden border border-base-300 bg-base-100" wire:key="uddds-{{ md5($patient['enccode']) }}">
            <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 uddds-patient-header">
                <div>
                    <button type="button" class="font-semibold text-left link link-primary" wire:click="view_enctr('{{ $patient['enccode'] }}')">
                        #{{ $patient['number'] }} · {{ $patient['name'] }}
                    </button>
                    <div class="text-xs text-base-content/60">
                        {{ $patient['hpercode'] }} · {{ $patient['wardname'] }} {{ $patient['rmname'] }}
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($patient['reprint_url'])
                        <a href="{{ $patient['reprint_url'] }}" target="_blank" rel="noopener"
                            class="btn btn-xs btn-outline btn-primary">
                            <x-heroicon-o-printer class="w-4 h-4" aria-hidden="true" /> Reprint Charge Slips
                        </a>
                    @else
                        <button type="button" class="btn btn-xs btn-outline btn-primary" disabled>
                            <x-heroicon-o-printer class="w-4 h-4" aria-hidden="true" /> Reprint Charge Slips
                        </button>
                    @endif
                <button type="button" class="btn btn-xs btn-success"
                    wire:click="prepareCharge('{{ $patient['enccode'] }}')"
                    @if (empty($patient['keys'])) disabled @endif wire:loading.attr="disabled">
                    Charge &amp; Issue
                </button>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="bg-base-200 text-xs font-semibold uppercase tracking-wide text-base-content/80">
                    <tr class="border-b border-base-300">
                        <th scope="col" class="w-12 px-3 py-3"><span class="sr-only">Select</span></th>
                        <th scope="col" class="px-3 py-3">Item</th>
                        <th scope="col" class="px-3 py-3">Fund source</th>
                        <th scope="col" class="px-3 py-3 text-right">Qty</th>
                        <th scope="col" class="px-3 py-3">Frequency</th>
                        <th scope="col" class="px-3 py-3">Start / End</th>
                        <th scope="col" class="px-3 py-3">Status</th>
                        <th scope="col" class="px-3 py-3">Charge Slip</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($patient['items'] as $item)
                        <tr class="border-b border-base-300 bg-base-100 transition-colors last:border-b-0 hover:bg-base-200"
                            wire:key="uddds-item-{{ $item->docointkey }}">
                            <td class="px-3 py-3">
                                <input type="checkbox"
                                    class="h-4 w-4 cursor-pointer rounded border-slate-300 text-emerald-600 accent-emerald-600 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                    data-uddds-selectable aria-label="Select {{ implode('', explode('_', $item->drug_concat)) }}" wire:model.live="selected_items"
                                    value="{{ $item->docointkey }}" @if (! $item->is_actionable) disabled @endif />
                            </td>
                            <td class="px-3 py-3 text-xs font-medium text-base-content">{{ implode('', explode('_', $item->drug_concat)) }}</td>
                            <td class="px-3 py-3 text-xs text-base-content/70">
                                {{ $item->chrgdesc }}
                                @if (!empty($fallback_sources[\App\Services\Pharmacy\UdddsStockAllocator::groupKey($item)]))
                                    <p class="mt-1 font-medium">Alternate funds configured for this item.</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right text-xs tabular-nums text-base-content/80">{{ number_format($item->pchrgqty, 0) }}</td>
                            <td class="px-3 py-3 text-xs text-base-content/70">{{ $item->frequency ?: '—' }}</td>
                            <td class="px-3 py-3 text-xs tabular-nums text-base-content/70 whitespace-nowrap">
                                {{ $item->uddds_start_date ? date('m/d/Y', strtotime($item->uddds_start_date)) : '' }}
                                –
                                {{ $item->uddds_end_date ? date('m/d/Y', strtotime($item->uddds_end_date)) : '' }}
                            </td>
                            <td class="px-3 py-3 text-xs">
                                @if ($item->queue_status === 'issued')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Issued</span>
                                @elseif ($item->queue_status === 'eligible')
                                    <span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800">Eligible</span>
                                @elseif ($item->queue_status === 'pending')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Pending</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-base-content/80">Charged</span>
                                @endif
                                @if (!empty($item->pending_reason))
                                    <div class="mt-1 text-xs {{ $item->stock_problem ? 'text-error' : 'text-base-content/70' }}">{{ $item->pending_reason }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs">
                                @if ($item->pcchrgcod)
                                    <a class="link link-primary underline" href="{{ route('dispensing.uddds.chargeslips', ['codes' => $item->pcchrgcod, 'numbers' => $patient['number']]) }}" target="_blank" rel="noopener">{{ $item->pcchrgcod }}</a>
                                @else
                                    <span>—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </section>
    @empty
        <div class="p-10 mt-6 text-center border rounded-lg border-base-300 text-base-content/60">
            <div class="font-medium text-base-content">No UDDDS orders match these filters</div>
            <div class="mt-1 text-sm">{{ $queue_view === 'processed' ? 'No existing UDDDS charge slips' : 'No eligible or generated Basic orders' }} for {{ $displayDate }} in this ward.</div>
        </div>
    @endforelse
    @endif
</div>


@script
<script>
    $wire.on('uddds-confirm-issue', ({ count }) => {
        const label = count === 1 ? 'item' : 'items';
        if (window.confirm('Charge and issue ' + count + ' ' + label + '? Current stock is used first; any remainder uses the selected alternate funds and their prices.')) {
            $wire.processSelected();
        }
    });
    $wire.on('uddds-print', ({ url }) => {
        window.open(url, '_blank', 'noopener,noreferrer');
    });
</script>
@endscript
