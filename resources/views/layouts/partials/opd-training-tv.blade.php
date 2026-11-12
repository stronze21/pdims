<dialog class="modal opd-training-tv" aria-labelledby="opd-training-tv-title" wire:ignore.self
    x-data x-init="if (!document.querySelector('[data-opd-training-processing][open]')) $el.showModal()"
    x-on:opd-training-processing-closed.window="if (!$el.open) $el.showModal()"
    x-on:cancel.prevent="$wire.closeTrainingDisplay()">
    <div class="opd-training-tv-panel">
        <header class="opd-training-tv-toolbar">
            <div class="flex items-center gap-3">
                <x-heroicon-o-tv class="size-6 shrink-0 text-primary" />
                <div>
                    <h2 id="opd-training-tv-title" class="font-semibold">Pharmacy TV display</h2>
                    <p class="text-sm">Training preview · RX-001</p>
                </div>
            </div>
            <button type="button" class="btn btn-primary" data-opd-action="continue-tv"
                wire:click="closeTrainingDisplay" wire:loading.attr="disabled" wire:target="closeTrainingDisplay">
                {{ $screen === 'queue' ? 'Continue to Queue Controller' : 'Continue to Dispensing' }}
            </button>
        </header>
        <div class="opd-training-tv-content">
            @include('livewire.pharmacy.prescriptions.queueing.prescription-queue-display', $trainingDisplay)
        </div>
    </div>
</dialog>
