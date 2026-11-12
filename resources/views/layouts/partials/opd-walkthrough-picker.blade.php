<div x-data>
    <button type="button" class="btn btn-outline btn-primary btn-sm touch-target"
        aria-haspopup="dialog" aria-controls="opd-walkthrough-picker" x-on:click="$refs.picker.showModal()">
        Start OPD Walkthrough
    </button>

    <dialog id="opd-walkthrough-picker" class="modal" x-ref="picker"
        aria-labelledby="opd-walkthrough-picker-title" x-on:keydown.escape.stop>
        <div class="modal-box">
            <h2 id="opd-walkthrough-picker-title" class="text-xl font-bold">Where should the walkthrough start?</h2>
            <div class="mt-4 space-y-2">
                <a href="{{ route('prescriptions.opd-walkthrough.emr2') }}"
                    class="block rounded-box border border-base-300 p-3 text-base-content hover:border-primary focus-visible:outline-primary">
                    <span class="block font-semibold">EMR2 + PDIMS</span>
                    <span class="mt-1 block text-sm">Add a prescription in EMR2, save it to the pharmacy queue, then continue in PDIMS.</span>
                </a>
                <a href="{{ route('prescriptions.opd-walkthrough.cdoe') }}"
                    class="block rounded-box border border-base-300 p-3 text-base-content hover:border-primary focus-visible:outline-primary">
                    <span class="block font-semibold">CDOE + PDIMS</span>
                    <span class="mt-1 block text-sm">Create the sample prescription in CDOE, queue it for Pharmacy, then continue in PDIMS.</span>
                </a>
                <a href="{{ route('prescriptions.opd-walkthrough') }}"
                    class="block rounded-box border border-base-300 p-3 text-base-content hover:border-primary focus-visible:outline-primary">
                    <span class="block font-semibold">PDIMS only</span>
                    <span class="mt-1 block text-sm">Start with the sample prescription already waiting in the pharmacy queue.</span>
                </a>
            </div>
            <form method="dialog" class="modal-action">
                <button type="submit" class="btn btn-ghost touch-target">Cancel</button>
            </form>
        </div>
    </dialog>
</div>
