<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            @if ($report !== '')
                <a class="link link-primary text-sm" href="{{ route('reports.index') }}" wire:navigate>All reports</a>
            @endif
            <h1 class="mt-2 text-2xl font-semibold text-base-content">{{ $titles[$report] ?? 'Pharmacy reports' }}</h1>
            <p class="mt-2 text-sm text-base-content/80">{{ $report === '' ? 'Review pharmacy transactions, reconcile stock, and prepare reports for your location.' : 'Apply filters to view results, then print or download the complete report.' }}</p>
        </div>
        @if ($report !== '' && ! $loadError)
            <div class="flex flex-wrap gap-2">
                <a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="{{ route('reports.print', ['report' => $report] + $filters) }}">Print report</a>
                <a class="btn btn-primary btn-sm" href="{{ route('reports.export', ['report' => $report] + $filters) }}">Download Excel</a>
            </div>
        @endif
    </header>

    @if ($report === '')
        <div class="overflow-hidden rounded-xl border border-base-300 bg-base-100">
            @foreach ($titles as $key => $title)
                <a href="{{ route('reports.show', ['report' => $key]) }}" wire:navigate
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 px-5 py-5 last:border-b-0 hover:bg-base-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                    <div>
                        <h2 class="font-semibold">{{ $title }}</h2>
                        <p class="mt-1 max-w-3xl text-sm text-base-content/80">{{ match ($key) {
                            'issuance' => 'Issued medicines by transaction date, patient, fund, and pharmacy location.',
                            'returns' => 'Returned medicines and their original order references.',
                            'reconciliation' => 'Opening balances, stock movements, closing balances, and unexplained differences.',
                            'deliveries' => 'Delivery items and received costs, with pending and reverted deliveries clearly identified.',
                            'expiry' => 'Current expired, near-expiry, and undated stock by medicine and lot.',
                        } }}</p>
                    </div>
                    <span class="text-sm font-medium text-primary">Open report <span aria-hidden="true">&rarr;</span></span>
                </a>
            @endforeach
        </div>
    @elseif ($loadError)
        <div class="rounded-xl border border-error bg-base-100 p-5" role="alert">
            <h2 class="font-semibold">Report unavailable</h2>
            <p class="mt-2 max-w-3xl text-sm">{{ $loadError }}</p>
            <button type="button" wire:click="$refresh" wire:loading.attr="disabled" class="btn btn-outline btn-sm mt-4">Retry report</button>
        </div>
    @else
        <form wire:submit="apply" class="rounded-xl border border-base-300 bg-base-100 p-5">
            @error('filters') <p class="mb-4 text-sm text-error" role="alert">{{ $message }}</p> @enderror
            <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @if ($report !== 'expiry')
                    <div>
                        <label for="report-from" class="mb-1 block text-sm font-medium">From date</label>
                        <input id="report-from" type="date" class="input input-bordered input-sm w-full" wire:model="filters.from" required>
                        @error('filters.from') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="report-to" class="mb-1 block text-sm font-medium">Through date</label>
                        <input id="report-to" type="date" max="{{ today()->toDateString() }}" class="input input-bordered input-sm w-full" wire:model="filters.to" required>
                        @error('filters.to') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label for="report-expiry" class="mb-1 block text-sm font-medium">Expiry status</label>
                        <select id="report-expiry" class="select select-bordered select-sm w-full" wire:model="filters.expiry">
                            <option value="near">Near expiry</option><option value="expired">Expired</option>
                            <option value="unknown">Unknown expiry</option><option value="all">All positive stock</option>
                        </select>
                        @error('filters.expiry') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="report-days" class="mb-1 block text-sm font-medium">Near-expiry horizon (days)</label>
                        <input id="report-days" type="number" min="1" max="730" class="input input-bordered input-sm w-full" wire:model="filters.days" required>
                        @error('filters.days') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="report-location" class="mb-1 block text-sm font-medium">Pharmacy location</label>
                    <select id="report-location" class="select select-bordered select-sm w-full" wire:model="filters.location" required>
                        <option value="">Select location</option>
                        @foreach ($options['locations'] as $location)
                            <option value="{{ $location->id }}">{{ $location->description }}</option>
                        @endforeach
                    </select>
                    @error('filters.location') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="report-fund" class="mb-1 block text-sm font-medium">Fund source</label>
                    <select id="report-fund" class="select select-bordered select-sm w-full" wire:model="filters.fund">
                        <option value="">All permitted funds</option>
                        @foreach ($options['funds'] as $fund)
                            <option value="{{ $fund->chrgcode }}">{{ $fund->chrgdesc }}</option>
                        @endforeach
                    </select>
                    @error('filters.fund') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="report-group-equivalents" class="mb-1 block text-sm font-medium">Equivalent drug grouping</label>
                    <select id="report-group-equivalents" class="select select-bordered select-sm w-full" wire:model="filters.group_equivalents" aria-describedby="report-grouping-help">
                        <option value="0">Separate medicines</option>
                        <option value="1">Group equivalents per fund source</option>
                    </select>
                    <p id="report-grouping-help" class="mt-1 text-xs text-base-content/80">Grouped summaries keep funds separate. Turn off for transaction and batch details.</p>
                    @error('filters.group_equivalents') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="report-medicine" class="mb-1 block text-sm font-medium">Medicine</label>
                    <input id="report-medicine" type="search" maxlength="150" placeholder="Search medicine name" class="input input-bordered input-sm w-full" wire:model="filters.medicine">
                    @error('filters.medicine') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="apply">
                    <span wire:loading.remove wire:target="apply">Apply filters</span>
                    <span wire:loading wire:target="apply">Preparing report…</span>
                </button>
            </div>
        </form>

        <section aria-label="Applied report filters" class="space-y-2">
            <p class="text-sm"><strong>{{ $metadata['location'] }}</strong> &middot; {{ $metadata['fund'] }} &middot; {{ $metadata['period'] }} &middot; {{ $metadata['medicine'] }}</p>
            <p class="text-sm font-medium">{{ $metadata['grouping'] ?? 'Separate medicines' }}</p>
            <p class="text-xs text-base-content/80">Generated {{ $metadata['generated'] }}. Downloads and print use these applied filters.</p>
        </section>

        @if ($report === 'reconciliation')
            <div class="rounded-xl border border-warning bg-warning/10 p-4" role="note">
                <h2 class="font-semibold">Review historical balances before using this report</h2>
                <p class="mt-1 max-w-3xl text-sm">Legacy records can have missing snapshots and revised transaction dates. Blank balances mean unavailable data. Review unexplained differences and the source notes below.</p>
            </div>
        @endif

        <section aria-label="Report results" class="overflow-hidden rounded-xl border border-base-300 bg-base-100">
            <div class="flex items-center justify-between gap-3 border-b border-base-300 px-4 py-3">
                <h2 class="font-semibold">{{ number_format($records->total()) }} {{ $records->total() === 1 ? 'record' : 'records' }}</h2>
                <span class="text-xs text-base-content/80">50 rows per page</span>
            </div>
            <div class="overflow-x-auto" tabindex="0" aria-label="Scrollable report table">
                @include('pharmacy.reports.table', ['rows' => $records])
            </div>
            <div class="border-t border-base-300 p-4">{{ $records->links() }}</div>
        </section>
        <section aria-label="Report notes" class="space-y-2 text-sm text-base-content/80">
            <h2 class="font-semibold text-base-content">Source notes</h2>
            @foreach ($notes as $note)
                <p class="max-w-4xl">{{ $note }}</p>
            @endforeach
        </section>
    @endif
</div>
