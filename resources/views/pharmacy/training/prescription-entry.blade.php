<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ strtoupper($source) }} Prescription Walkthrough</title>
    @include('layouts.partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="pdims-theme min-h-screen bg-base-200 font-sans antialiased" data-opd-training-page>
    @php
        $medication = \App\Livewire\Pharmacy\Training\OpdWalkthrough::MEDICATION;
        $sampleRemarks = \App\Livewire\Pharmacy\Training\OpdWalkthrough::REMARKS;
        $referralNote = 'For referred patients, leave this unchecked at each earlier clinic. Only the last clinic should check it.';
        $isEmr2 = $source === 'emr2';
        $startLabel = $isEmr2 ? 'Add Prescription' : '+ Rx';
        $quantityLabel = $isEmr2 ? 'Qty / Dose' : 'Quantity';
        $remarksLabel = $isEmr2 ? 'Remarks / Special Instructions' : 'Remarks';
        $submitLabel = $isEmr2 ? 'Save' : 'Submit';
    @endphp
    <main @class(['mx-auto space-y-5 px-4 py-5 sm:px-6', 'max-w-6xl' => !$isEmr2, 'max-w-screen-2xl' => $isEmr2])
        data-medication="{{ $medication }}" data-source="{{ $source }}" data-sample-remarks="{{ $sampleRemarks }}"
        x-data="prescriptionEntryTraining($el.dataset.medication, $el.dataset.source, $el.dataset.sampleRemarks)">
        <aside aria-label="Prescription training guidance" data-opd-stage="rx-entry-start" x-bind:data-opd-stage="stage"
            class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-primary/30 bg-primary/10 px-4 py-3">
            <div>
                <p class="font-semibold">OPD Walkthrough · {{ strtoupper($source) }} practice</p>
                <p class="text-sm">Synthetic patient record</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('prescriptions.opd-walkthrough.'.$source) }}" class="btn btn-ghost btn-sm">Restart</a>
                @auth
                    <a href="{{ route('prescriptions.queue.controller2') }}" class="btn btn-outline btn-sm">Exit walkthrough</a>
                @endauth
            </div>
        </aside>

        @if (!$isEmr2)
            @include('pharmacy.training.cdoe-navigation')
        @else
            @include('pharmacy.training.emr2-prescription')
        @endif

        @if (!$isEmr2)
            <nav x-show="screen === 'form'" x-cloak aria-label="Prescription form navigation" class="flex flex-wrap items-center gap-2">
                <button type="button" aria-label="Back to prescriptions" class="btn btn-outline btn-sm"
                    x-on:click="screen = 'list'"><x-heroicon-o-chevron-left class="size-4" /></button>
                <button type="button" aria-current="page" class="btn btn-active btn-sm">Basic</button>
                <button type="button" disabled class="btn btn-outline btn-sm">Others (Non-PNF)</button>
            </nav>
        @endif

        @unless($isEmr2)
        <section x-show="screen === 'list'" class="rounded-box border border-base-300 bg-base-100 p-4 sm:p-5">
            <button type="button" class="btn btn-success min-h-11" data-opd-action="rx-entry-start"
                data-opd-title="{{ $startLabel }}" x-on:click="startPrescription()">{{ $startLabel }}</button>
            <p class="mt-4 border-t border-base-300 pt-4 text-sm">No prescriptions for this sample encounter.</p>
        </section>

        <section x-show="screen === 'form'" x-cloak aria-label="Basic prescription form"
            class="grid items-start gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <div class="rounded-box border border-base-300 bg-base-100 p-4 sm:p-5">
                <label for="rx-entry-medication-search" class="block font-semibold">Search medicine</label>
                <input id="rx-entry-medication-search" type="search" class="input mt-2 w-full"
                    placeholder="Search medicine here" autocomplete="off" x-model="query" x-ref="search"
                    data-opd-action="rx-entry-search">
                <p class="mt-2 text-sm" x-show="!hasResult">Search for {{ $medication }}.</p>
                <table class="table table-sm mt-3" x-show="hasResult">
                    <thead>
                        <tr><th scope="col">Name</th><th scope="col">Quantity</th><th scope="col">Action</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $medication }}</td>
                            <td>12</td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm min-h-11" data-opd-action="rx-entry-add"
                                    x-show="!itemAdded" x-on:click="addItem()">Add</button>
                                <span x-show="itemAdded">Added</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form class="rounded-box border border-base-300 bg-base-100 p-4 sm:p-5"
                x-show="itemAdded" x-on:submit.prevent="submit()" aria-label="Sample prescription">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold">{{ $medication }}</h2>
                        <p class="mt-1 text-sm">Type: Basic · Priority: Routine</p>
                    </div>
                    <button type="button" class="btn btn-outline btn-error btn-sm min-h-11"
                        x-on:click="removeItem()">Remove</button>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <label class="block text-sm font-semibold">
                        {{ $quantityLabel }}
                        <input type="number" min="1" max="1" step="1" required placeholder="1"
                            class="input mt-1 w-full" x-model.number="quantity" x-ref="quantity" data-opd-action="rx-entry-quantity">
                    </label>
                    <label class="block text-sm font-semibold">
                        Frequency
                        <select required class="select mt-1 w-full" x-model="frequency" data-opd-action="rx-entry-frequency">
                            <option value="">Frequency</option>
                            <option value="once-daily">Once daily</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">
                        Days
                        <input type="number" min="1" max="1" step="1" required placeholder="1"
                            class="input mt-1 w-full" x-model.number="days" data-opd-action="rx-entry-days">
                    </label>
                </div>

                <label class="mt-3 block text-sm font-semibold">
                    {{ $remarksLabel }}
                    <textarea required rows="2" maxlength="80" class="textarea mt-1 w-full" placeholder="{{ $sampleRemarks }}"
                        data-opd-description="Type {{ $sampleRemarks }} in the Remarks field."
                        x-model="remarks" data-opd-action="rx-entry-remarks"></textarea>
                </label>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4">
                    <div>
                        <label class="flex min-h-11 items-center gap-2 font-semibold">
                            <input type="checkbox" class="checkbox checkbox-primary" x-model="proceedToPharmacy" data-opd-action="rx-entry-queue"
                                aria-describedby="cdoe-pharmacy-referral-note"
                                data-opd-description="Check this option to send the prescription to the pharmacy queue. {{ $referralNote }}">
                            Proceed to Pharmacy?
                        </label>
                        <p id="cdoe-pharmacy-referral-note" class="text-sm text-base-content/75">{{ $referralNote }}</p>
                    </div>
                    <button type="submit" class="btn btn-primary min-h-11" data-opd-action="rx-entry-submit"
                        data-opd-title="{{ $submitLabel }}" x-bind:disabled="stage !== 'rx-entry-submit'">{{ $submitLabel }}</button>
                </div>
            </form>
        </section>
        @endunless

        @if ($isEmr2)
            <dialog class="modal" x-effect="screen === 'care' ? $el.showModal() : $el.close()"
                x-on:cancel.prevent="finish()" aria-labelledby="care-orders-title">
                <div class="modal-box">
                    <h2 id="care-orders-title" class="text-lg font-bold">Create Clinical / Care orders?</h2>
                    <p class="mt-2 text-sm">EMR2 offers to create a separate care order from the saved special instructions.</p>
                    <div class="modal-action">
                        <button type="button" class="btn btn-primary min-h-11" data-opd-action="rx-entry-care"
                            x-on:click="finish()">No, thanks</button>
                    </div>
                </div>
            </dialog>
        @endif

        <section x-show="screen === 'queued'" x-cloak class="rounded-box border border-base-300 bg-base-100 p-4 sm:p-5">
            @if (!$isEmr2)
                <div class="mb-4 flex flex-wrap gap-2 border-b border-base-300 pb-3" aria-label="Prescription list controls">
                    <button type="button" disabled class="btn btn-success btn-sm">+ Rx</button>
                    <button type="button" aria-label="Refresh" class="btn btn-outline btn-sm"
                        x-on:click="filter = 'All'"><x-heroicon-o-arrow-path class="size-4" /></button>
                    @foreach (['All' => 1, 'Active' => 1, 'Inactive' => 0, 'Archive' => 0] as $label => $count)
                        <button type="button" class="btn btn-outline btn-sm" x-bind:aria-pressed="filter === @js($label)"
                            x-on:click="filter = @js($label)">{{ $label }} ({{ $count }})</button>
                    @endforeach
                </div>
            @endif
            <p role="status" class="font-semibold text-success">
                {{ $isEmr2 ? 'OPD orders posted. Pharmacy queue number: RX-001.' : 'Prescription added to queue successfully.' }}
            </p>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-4 border-t border-base-300 pt-4">
                <div>
                    <p class="text-sm">Queue Number</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">RX-001</p>
                    <p class="mt-1 text-sm" x-show="filter === 'All' || filter === 'Active'">{{ $medication }} · Waiting at Pharmacy</p>
                    <p class="mt-1 text-sm" x-show="filter === 'Inactive' || filter === 'Archive'">No prescriptions in this view.</p>
                </div>
                <a href="{{ route('prescriptions.opd-walkthrough', ['source' => $source]) }}"
                    class="btn btn-primary min-h-11" x-ref="handoff" data-opd-action="rx-entry-handoff">Continue to PDIMS</a>
            </div>
        </section>
    </main>
    @livewireScripts
</body>
</html>
