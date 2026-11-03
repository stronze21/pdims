<dialog x-ref="processingDialog" wire:ignore class="modal" aria-labelledby="dispensing-processing-title"
    x-on:cancel="if (processingBusy) $event.preventDefault()">
    <div class="modal-box max-w-lg max-h-[90dvh] overflow-y-auto">
        <h2 id="dispensing-processing-title" tabindex="-1" autofocus class="text-lg font-semibold" x-text="processingTitle"></h2>
        <p class="mt-1 text-sm text-base-content/65"><span x-text="processingCount"></span> selected items · <span x-text="processingElapsed"></span>s elapsed</p>
        <div class="mt-6" x-ref="processingSteps" wire:stream="dispensing-progress-{{ $this->getId() }}"
            role="status" aria-live="polite" aria-atomic="true" x-bind:aria-busy="processingBusy"></div>
        <p class="mt-5 text-sm text-base-content/70" x-show="processingBusy &amp;&amp; processingElapsed >= 10" x-cloak>
            This is taking longer than usual. Please keep this page open.
        </p>
        <template x-if="processingResult">
            <div class="mt-5 border-t border-base-300 pt-4" role="status" aria-live="polite">
                <p class="font-semibold" x-text="({success: 'Completed', warning: 'Completed with a warning', error: 'Could not complete', uncertain: 'Result needs checking'})[processingResult.status]"></p>
                <p class="mt-1 text-sm" x-text="processingResult.message"></p>
            </div>
        </template>
        <div class="modal-action">
            <button type="button" class="btn btn-primary" x-bind:disabled="processingBusy"
                x-on:click="$refs.processingDialog.close()" x-text="processingBusy ? 'Processing…' : 'Done'"></button>
        </div>
    </div>
</dialog>
