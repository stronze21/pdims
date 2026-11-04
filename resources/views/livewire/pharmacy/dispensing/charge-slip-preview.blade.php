<dialog wire:ignore x-ref="chargeSlipPreview" class="modal backdrop-blur" aria-labelledby="charge-slip-preview-title"
    x-on:close="chargePreviewUrl = ''; chargePreviewState = 'loading'">
    <div class="modal-box flex h-[94dvh] max-h-[94dvh] w-[96vw] max-w-6xl flex-col gap-3">
        <h2 id="charge-slip-preview-title" class="text-xl font-bold" tabindex="-1" autofocus>Charge Slip Print Preview</h2>
        <p x-show="chargePreviewState === 'loading'" role="status" class="flex items-center gap-2 text-sm">
            <span class="loading loading-spinner loading-sm" aria-hidden="true"></span>
            Loading charge slip preview…
        </p>
        <p x-show="chargePreviewState === 'error'" x-cloak role="alert" class="text-sm text-error">
            The preview could not be loaded. Retry in the preview if available, or open the charge slip in a new tab.
        </p>
        <template x-if="chargePreviewUrl">
            <iframe x-ref="chargeSlipFrame" x-bind:src="chargePreviewUrl" title="PDIMS charge slip print preview"
                x-bind:aria-busy="chargePreviewState === 'loading'" referrerpolicy="no-referrer"
                x-on:load="checkChargeSlipPreview($event.target)"
                x-on:error="chargePreviewState = 'error'"
                x-on:message.window="if ($event.origin === window.location.origin && $event.source === $el.contentWindow && $event.data?.type === 'pdims-charge-slip-state') checkChargeSlipPreview($el)"
                class="min-h-0 w-full flex-1 rounded-lg border border-base-300 bg-base-100"></iframe>
        </template>
        <div class="flex flex-wrap justify-end gap-2">
            <a x-bind:href="chargePreviewUrl" target="_blank" rel="noopener noreferrer" class="btn btn-outline">Open in new tab</a>
            <button type="button" class="btn btn-ghost" x-on:click="$refs.chargeSlipPreview.close()">Close</button>
            <button type="button" class="btn btn-primary" disabled x-bind:disabled="chargePreviewState !== 'ready'"
                x-on:click="printChargeSlipPreview()">Print / Save as PDF</button>
        </div>
    </div>
</dialog>
