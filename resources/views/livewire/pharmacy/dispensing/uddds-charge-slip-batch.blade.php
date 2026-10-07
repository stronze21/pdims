<div class="container max-w-xl mx-auto mt-5 pos-print-page">
    @include('livewire.pharmacy.dispensing.pos-receipt-styles')
    <script src="{{ asset('js/uddds-batch-print.js') }}?v=3"></script>
    <div x-data="udddsBatchLoader($wire, @js($codes))" x-init="$nextTick(() => load())"
        :class="{ 'batch-incomplete': !ready }" class="batch-incomplete">
        <style>
            [x-cloak] { display: none !important; }
            .batch-loading-modal { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgb(0 0 0 / 50%); }
            .batch-loading-panel { width: 100%; max-width: 420px; padding: 24px; background: #fff; color: #111; border-radius: 8px; }
            .batch-print-warning { display: none; }
            @media print {
                .batch-incomplete #print { display: none !important; }
                .batch-incomplete .batch-print-warning { display: block; }
                .batch-loading-modal { display: none !important; }
            }
        </style>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3 no-print">
            <span role="status" aria-live="polite" x-text="next + ' / ' + codes.length + ' charge slips loaded'"></span>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline" x-show="error" x-cloak @click="load()">Retry Loading</button>
                <button type="button" class="btn btn-sm btn-primary" :disabled="!ready" disabled @click="print()">Print all</button>
            </div>
        </div>
        <div class="batch-loading-modal no-print" x-show="modalOpen" role="dialog" aria-modal="true" aria-labelledby="batch-loading-title">
            <div class="batch-loading-panel">
                <h2 id="batch-loading-title" class="text-lg font-semibold" x-text="error ? 'Charge slip could not load' : 'Loading charge slips'"></h2>
                <p class="mt-2 text-sm" role="status" aria-live="polite" x-text="next + ' of ' + codes.length + ' loaded'"></p>
                <progress class="progress progress-primary mt-4 w-full" :value="next" :max="codes.length" aria-label="Charge slips loaded"></progress>
                <p class="mt-2 text-sm" x-show="!error" x-text="'Loading ' + (codes[next] || '')"></p>
                <p class="mt-2 text-sm text-error" x-show="error" x-text="error" role="alert"></p>
                <div class="flex gap-2 mt-4" x-show="error" x-cloak>
                    <button type="button" class="btn btn-sm btn-primary" @click="load()">Retry</button>
                    <button type="button" class="btn btn-sm btn-outline" @click="modalOpen = false">Close</button>
                </div>
            </div>
        </div>
        <p class="batch-print-warning">Charge slips are still loading or a slip failed to load. Complete loading before printing.</p>
        <div id="print" class="bg-white pos-receipt" x-ref="receipts" wire:ignore></div>
        @if (empty($codes))
            <p class="p-8 text-center">No charge slips to print.</p>
        @endif
    </div>
</div>
