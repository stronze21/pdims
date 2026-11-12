<div x-data>
    <nav aria-label="CDOE navigation" class="flex flex-wrap items-center gap-1 rounded-md border border-base-300 bg-base-100 px-3 py-1">
        <button type="button" disabled class="btn btn-ghost text-lg font-semibold">
            <x-heroicon-o-user-circle class="size-5" /> CDOE
        </button>
        <button type="button" disabled class="btn btn-ghost btn-sm"><x-heroicon-o-magnifying-glass class="size-4" /> Search</button>
        <button type="button" disabled class="btn btn-ghost btn-sm"><x-heroicon-o-video-camera class="size-4" /> Teleconsult</button>
        <details class="dropdown">
            <summary class="btn btn-ghost btn-sm">More <x-heroicon-o-chevron-down class="size-4" /></summary>
            <ul class="menu dropdown-content z-20 w-48 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                @foreach (['Case Rate', 'Referral List', 'Feedback', 'MMMH&MC Portal'] as $label)
                    <li><button type="button" disabled>{{ $label }}</button></li>
                @endforeach
            </ul>
        </details>
        <details class="dropdown">
            <summary class="btn btn-ghost btn-sm"><x-heroicon-o-user class="size-4" /> Account <x-heroicon-o-chevron-down class="size-4" /></summary>
            <ul class="menu dropdown-content z-20 w-40 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                <li><button type="button" disabled>My Profile</button></li>
                <li><button type="button" disabled>Logout</button></li>
            </ul>
        </details>
    </nav>

    <header class="mt-4 flex flex-wrap items-center gap-2 border-b border-base-300 pb-3">
        <h1 class="me-1 text-2xl font-semibold">TRAINING, Alex <span class="text-base font-normal text-base-content/70">DEMO-001</span></h1>
        <button type="button" class="btn btn-outline btn-primary btn-sm" aria-haspopup="dialog"
            x-on:click="$refs.patientMenu.showModal()">Rx <x-heroicon-o-chevron-down class="size-4" /></button>
        <details class="dropdown">
            <summary class="btn btn-outline btn-sm">Enctr - OPD <x-heroicon-o-chevron-down class="size-4" /></summary>
            <ul class="menu dropdown-content z-20 w-52 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                <li><span aria-current="true">OPD · Family Medicine</span></li>
            </ul>
        </details>
    </header>

    <dialog class="modal" x-ref="patientMenu" aria-labelledby="cdoe-patient-menu" data-training-navigation>
        <div class="modal-box">
            <h2 id="cdoe-patient-menu" class="text-lg font-bold">Main Menu</h2>
            <ul class="menu mt-3 w-full">
                @foreach (['Rx', 'Examination', 'Diagnosis', 'Immunization', 'Search', 'More Info', 'CF4', 'PF', 'Refer', 'Cancer Registry', 'Operation Record', 'Electronic Medical Record'] as $label)
                    <li><button type="button" @disabled($label !== 'Rx')
                        @if ($label === 'Rx') aria-current="page" x-on:click="$refs.patientMenu.close()" @endif>{{ $label }}</button></li>
                @endforeach
            </ul>
            <form method="dialog" class="modal-action">
                <button type="submit" class="btn">Close</button>
            </form>
        </div>
    </dialog>
</div>
