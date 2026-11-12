<div class="space-y-4">
    <button type="button" disabled class="btn btn-ghost btn-sm"><x-heroicon-o-arrow-left class="size-4" /> Back to OPD Dashboard</button>
    <div class="grid gap-4 lg:grid-cols-12">
        <section class="flex items-center gap-4 rounded-lg border border-base-300 bg-base-100 p-4 shadow-sm lg:col-span-8">
            <x-heroicon-o-user-circle class="size-12 text-primary" />
            <div>
                <h1 class="text-xl font-bold">TRAINING, Alex</h1>
                <p class="text-sm text-base-content/70">Hospital No. DEMO-001 · Female · OPD</p>
            </div>
        </section>
        <dl class="rounded-lg border border-base-300 bg-base-100 p-4 shadow-sm lg:col-span-4">
            <dt class="text-xs font-bold uppercase text-base-content/60">Clinic</dt>
            <dd class="text-sm font-bold">Family Medicine</dd>
            <dt class="mt-3 text-xs font-bold uppercase text-base-content/60">Status</dt>
            <dd class="text-sm font-semibold">Active</dd>
        </dl>
    </div>
    <section class="rounded-xl border border-base-300 bg-base-100">
        <nav class="flex gap-1 overflow-x-auto rounded-t-xl border-b border-base-300 bg-base-200/50 px-4 pt-4" aria-label="OPD medical record sections">
            @foreach (['Basic Information', 'Vital Signs and Others', 'Genealogy', 'Diagnosis Hx', 'Examination Hx', 'Prescription Hx', 'Attachment', 'Instructions', 'SOAP', 'Disposition'] as $tab)
                <button type="button" @disabled($tab !== 'Prescription Hx') @if ($tab === 'Prescription Hx') aria-current="page" @endif
                    @class(['shrink-0 rounded-t-lg border-b-2 px-3 py-2 text-sm font-semibold', 'border-primary bg-base-100 text-primary' => $tab === 'Prescription Hx', 'border-transparent text-base-content/65' => $tab !== 'Prescription Hx'])>{{ $tab }}</button>
            @endforeach
        </nav>
        <div class="p-4 sm:p-6" x-show="screen !== 'queued'">
            <div class="w-full space-y-3 lg:w-2/3">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 pb-3">
                    <h2 class="text-xl font-semibold">Prescriptions</h2>
                    <button type="button" class="btn btn-primary" data-opd-action="rx-entry-start" data-opd-title="Add Prescription"
                        aria-haspopup="dialog" x-on:click="startPrescription()">Add Prescription</button>
                </header>
                <button type="button" disabled class="btn btn-outline btn-sm">Print prescriptions</button>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex items-center gap-2">Show <select disabled class="select select-bordered select-sm w-auto" aria-label="History entries per page"><option>10</option></select> entries</label>
                    <label class="flex items-center gap-2">Search: <input type="search" disabled class="input input-bordered input-sm w-44" aria-label="Search order history"></label>
                </div>
                <div class="overflow-x-auto">
                    <table class="table [&_td]:border [&_th]:border [&_td]:border-base-300 [&_th]:border-base-300">
                        <thead class="bg-info/10"><tr><th>Date Ordered</th><th>Encounter</th><th>Item</th><th>Quantity</th><th>Remarks</th></tr></thead>
                        <tbody><tr><td colspan="5" class="text-center text-base-content/65">No prescriptions for this sample encounter.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<dialog class="modal place-items-stretch" aria-labelledby="emr2-training-drawer"
    x-effect="screen === 'form' ? $el.showModal() : $el.close()" x-on:cancel.prevent="screen = 'list'">
    <form class="ms-auto flex h-dvh w-full flex-col overflow-hidden bg-base-100 shadow-2xl lg:w-5/6 xl:w-4/5"
        x-on:submit.prevent="submit()">
        <header class="flex items-start justify-between gap-4 border-b border-base-300 px-4 py-4 md:px-8">
            <div><h2 id="emr2-training-drawer" class="text-lg font-bold">Add Prescription</h2><p class="text-sm text-base-content/70">Complete the details, then select Save</p></div>
            <button type="button" class="btn btn-ghost btn-sm btn-circle" aria-label="Close doctor's order drawer"
                x-on:click="screen = 'list'"><x-heroicon-o-x-mark class="size-5" /></button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 md:px-8">
            <section class="rounded-xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div><h3 class="text-sm font-bold uppercase tracking-wide">Prescription Details</h3><p class="text-xs text-base-content/70">Search for a medicine, select Add, then enter the dosing details.</p></div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" disabled class="checkbox checkbox-sm"> Take home</label>
                        <button type="button" x-show="itemAdded" class="btn btn-outline btn-error btn-sm" x-on:click="removeItem()">Clear</button>
                    </div>
                </div>
                <div class="relative mb-3 max-w-3xl">
                    <label for="emr2-medicine-search" class="mb-1 block text-xs font-semibold uppercase tracking-wide">Search medicine</label>
                    <input id="emr2-medicine-search" type="search" autocomplete="off" autofocus x-ref="search" x-model="query"
                        class="input input-bordered input-sm w-full" placeholder="Type at least 2 characters" data-opd-action="rx-entry-search">
                    <div x-show="hasResult && !itemAdded" class="absolute inset-x-0 top-full z-10 mt-1 flex items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-100 p-3 shadow-lg">
                        <span class="text-sm font-semibold">{{ $medication }}</span>
                        <button type="button" class="btn btn-primary btn-sm" x-on:click="addItem()" data-opd-action="rx-entry-add">Add</button>
                    </div>
                </div>
                <div class="rounded-lg border border-base-300 p-4">
                    <p x-show="!itemAdded" class="py-3 text-center text-sm text-base-content/65">Search for a medicine and select Add to start the prescription.</p>
                    <div x-show="itemAdded">
                        <div class="grid grid-cols-2 gap-3 lg:grid-cols-[2fr_1fr_1fr_1.5fr_1fr_1fr]">
                            <div><p class="text-xs font-medium">Medicine</p><p class="mt-1 text-sm font-semibold">{{ $medication }}</p></div>
                            <label class="text-xs font-medium">Type<select disabled class="select select-sm mt-1 w-full"><option>Basic</option></select></label>
                            <label class="text-xs font-medium">Qty / Dose<input type="number" min="1" max="1" required x-model.number="quantity" x-ref="quantity" data-opd-action="rx-entry-quantity" class="input input-sm mt-1 w-full"></label>
                            <label class="text-xs font-medium">Frequency<select required x-model="frequency" data-opd-action="rx-entry-frequency" class="select select-sm mt-1 w-full"><option value="">Frequency</option><option value="once-daily">Once daily</option></select></label>
                            <label class="text-xs font-medium">Days<input type="number" min="1" max="1" required x-model.number="days" data-opd-action="rx-entry-days" class="input input-sm mt-1 w-full"></label>
                            <div><p class="text-xs font-medium">Total Units</p><output class="mt-1 flex h-8 items-center rounded-lg border border-base-300 bg-base-200/60 px-3 text-sm font-semibold" x-text="quantity === 1 && frequency && days === 1 ? '1' : '–'"></output></div>
                        </div>
                        <div class="mt-3 flex items-end gap-2">
                            <label class="flex-1 text-xs font-medium">Remarks / Special Instructions<input type="text" maxlength="100" x-model="remarks" data-opd-action="rx-entry-remarks" data-opd-description="Type {{ $sampleRemarks }} in the Remarks field." class="input input-sm mt-1 w-full" placeholder="Enter additional instructions or remarks..."></label>
                            <button type="button" class="btn btn-ghost btn-sm" aria-label="Remove prescription row" x-on:click="removeItem()"><x-heroicon-o-trash class="size-4" /></button>
                        </div>
                    </div>
                </div>
                <label class="mt-4 flex items-start gap-3 border-t border-base-300 pt-4">
                    <input type="checkbox" class="checkbox checkbox-primary mt-0.5" x-model="proceedToPharmacy" data-opd-action="rx-entry-queue"
                        data-opd-description="Check this option to add the prescription to the numbered Pharmacy queue when you save it.">
                    <span><span class="block font-semibold">Proceed to Pharmacy?</span><span class="block text-xs text-base-content/70">Add this prescription to the numbered Pharmacy queue after it is saved.</span></span>
                </label>
            </section>
        </div>
        <footer class="flex justify-end gap-2 border-t border-base-300 px-4 py-3 md:px-8">
            <button type="button" class="btn btn-ghost" x-on:click="screen = 'list'">Close</button>
            <button type="submit" class="btn btn-primary" data-opd-action="rx-entry-submit" data-opd-title="Save" x-bind:disabled="stage !== 'rx-entry-submit'">Save</button>
        </footer>
    </form>
</dialog>
