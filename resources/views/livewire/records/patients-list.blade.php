<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="text-sm breadcrumbs">
                <ul>
                    <li class="font-bold text-primary">
                        <x-mary-icon name="o-building-office-2" class="inline w-5 h-5" />
                        {{ session('pharm_location_name') }}
                    </li>
                    <li>
                        <x-mary-icon name="o-users" class="inline w-5 h-5" />
                        Patient Records
                    </li>
                </ul>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 lg:-mx-6 lg:-mt-4">
        <header class="-mb-2 border-b border-base-300 pb-3">
            <h1 class="text-2xl font-black tracking-tight">Patient Registry</h1>
            <p class="mt-1 text-sm text-base-content/60">Search and review hospital patient records</p>
        </header>

        <div class="grid gap-5 xl:grid-cols-[minmax(20rem,0.8fr)_minmax(0,1.7fr)]">
            <form wire:submit="searchPatients" class="rounded-xl border border-base-300 bg-base-100 p-5 xl:min-h-96">
                <h2 class="font-bold">Check for an existing patient</h2>
                <p class="mb-4 mt-1 text-xs text-base-content/60">Use the available identity details to avoid creating a duplicate hospital record.</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['searchHpercode', 'Hospital No.', 'text'],
                        ['searchLastName', 'Last Name', 'text'],
                        ['searchFirstName', 'First Name', 'text'],
                        ['searchMiddleName', 'Middle Name', 'text'],
                        ['searchDob', 'Date of Birth', 'date'],
                    ] as [$field, $label, $type])
                        <div class="min-w-0">
                            <label for="registry-{{ $field }}" class="mb-1.5 block text-xs font-semibold">{{ $label }}</label>
                            <input id="registry-{{ $field }}" type="{{ $type }}" wire:model="{{ $field }}"
                                autocomplete="off" class="input input-bordered h-10 w-full" />
                            @error($field)
                                <p class="mt-1 text-xs text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <x-mary-button label="Check Existing Patient" icon="o-magnifying-glass" type="submit"
                        class="btn-primary h-10" spinner="searchPatients" />
                    <button type="button" wire:click="clearSearch" class="btn btn-ghost h-10 border border-base-300">Clear</button>
                </div>
            </form>

            <section class="flex min-h-80 min-w-0 flex-col overflow-hidden rounded-xl border border-base-300 bg-base-100 xl:h-96">
                @if ($selectedPatient)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 bg-primary/5 px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold">{{ $selectedPatient->patlast }}, {{ $selectedPatient->patfirst }} {{ $selectedPatient->patmiddle }}</h2>
                            <span class="badge badge-outline">HN {{ $selectedPatient->hpercode }}</span>
                        </div>
                        <div class="flex gap-2">
                            <x-mary-button label="Walk-In" wire:click="initiateWalkIn" spinner="initiateWalkIn" class="btn-primary btn-sm" />
                            <button type="button" wire:click="closeEncounterPreview" class="btn btn-ghost btn-sm border border-base-300">Close</button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-3" x-data="{ type: '', query: '' }" wire:key="preview-{{ $selectedHpercode }}">
                        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                            <span>Encounter status</span>
                            @php
                                $statusStyles = [
                                    'active' => 'badge-soft badge-success',
                                    'inactive' => 'badge-soft badge-warning',
                                    'discharged' => 'badge-soft badge-info',
                                ];
                            @endphp
                            @foreach ($statusStyles as $status => $style)
                                <span class="badge badge-sm {{ $style }}">{{ ucfirst($status) }}</span>
                            @endforeach
                        </div>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-base-300 p-2">
                            <div class="flex flex-wrap gap-2" aria-label="Encounter type">
                                @foreach (['' => 'All Encounters', 'ER' => 'Emergency', 'ADM' => 'Inpatient', 'OPD' => 'Outpatient'] as $code => $label)
                                    <button type="button" class="btn btn-xs rounded-full" :class="type === @js($code) ? 'btn-primary' : 'btn-ghost border-base-300'"
                                        :aria-pressed="type === @js($code)" @click="type = @js($code)">{{ $label }}</button>
                                @endforeach
                            </div>
                            <input type="search" x-model.trim="query" aria-label="Search history" placeholder="Search history"
                                class="input input-bordered input-sm w-full sm:max-w-xs" />
                        </div>

                        <div class="space-y-2">
                            @forelse ($patientEncounters as $encounter)
                                @php
                                    $diagnosis = trim(strip_tags($encounter['diagtext'] ?? ''));
                                    $date = \Carbon\Carbon::parse($encounter['encdate']);
                                    $typeStyle = match ($encounter['toecode']) {
                                        'ER', 'ERADM' => 'badge-error',
                                        'ADM' => 'badge-primary',
                                        'OPD' => 'badge-secondary',
                                        default => 'badge-neutral',
                                    };
                                @endphp
                                <button type="button" wire:key="enc-{{ $encounter['enccode'] }}"
                                    wire:click="viewEncounter(@js($encounter['enccode']))"
                                    x-show="(!type || type === @js($encounter['toecode'])) && (!query || @js(strtolower($diagnosis.' '.$encounter['toecode'].' '.$date->format('M d, Y'))).includes(query.toLowerCase()))"
                                    class="grid w-full cursor-pointer gap-2 rounded-xl border border-base-300 bg-base-200 p-2.5 text-left hover:border-primary/50 focus-visible:outline-2 focus-visible:outline-primary motion-safe:transition motion-safe:duration-200 motion-safe:ease-out motion-safe:hover:scale-[1.008] lg:grid-cols-[7rem_minmax(0,1fr)_minmax(9rem,0.55fr)] lg:items-start">
                                    <div class="space-y-1 text-xs">
                                        <span class="badge badge-soft badge-sm {{ $typeStyle }}">{{ ['ER' => 'Emergency', 'ERADM' => 'Emergency', 'ADM' => 'Inpatient', 'OPD' => 'Outpatient'][$encounter['toecode']] ?? $encounter['toecode'] }}</span>
                                        <div><span class="badge badge-sm {{ $statusStyles[$encounter['status_badge']] ?? 'badge-ghost' }}">{{ ucfirst($encounter['status_badge']) }}</span></div>
                                        <div class="font-semibold">{{ $date->format('M d, Y') }}</div>
                                        <div class="text-base-content/60">{{ $date->format('h:i A') }}</div>
                                    </div>
                                    <div class="min-w-0 break-words lg:px-3">
                                        <p class="text-sm font-bold leading-snug">{{ $diagnosis ?: 'No diagnosis' }}</p>
                                        <p class="mt-1 text-sm text-base-content/55">{{ $encounter['department'] ?? 'Department unavailable' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-base-content/50">Disposition</p>
                                        <p class="mt-1 text-sm font-medium">{{ $encounter['disposition'] ?? 'Not recorded' }}</p>
                                    </div>
                                </button>
                            @empty
                                <p class="py-8 text-center text-sm text-base-content/60">No encounters</p>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="grid flex-1 place-items-center p-6 text-center">
                        <div>
                            <x-mary-icon name="o-clock" class="mx-auto h-12 w-12 text-base-content/25" />
                            <h2 class="mt-3 font-bold">Encounter preview</h2>
                            <p class="mt-1 text-sm text-base-content/60">Select Encounters from a patient row below.</p>
                        </div>
                    </div>
                @endif
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border border-base-300 bg-base-100">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-5 py-3">
                <div>
                    <h2 class="font-bold">Patient records</h2>
                    <p class="text-xs text-base-content/60">Matching records based on the submitted identity details</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="badge badge-outline min-h-7 border-base-300 px-3 font-semibold">{{ $searchResults->count() }} {{ Str::plural('record', $searchResults->count()) }}</span>
                    <x-mary-button label="Register Patient" type="button" wire:click="openNewPatientModal" class="btn-ghost btn-sm" />
                </div>
            </header>
            <div class="max-h-[60dvh] overflow-auto">
                <table class="table table-zebra w-full min-w-[46rem] table-fixed">
                    <thead class="sticky top-0 bg-base-100 text-base-content/65">
                        <tr>
                            <th scope="col" style="width: 19%">Hospital #</th>
                            <th scope="col" style="width: 17%">First Name</th>
                            <th scope="col" style="width: 15%">Middle Name</th>
                            <th scope="col" style="width: 12.5%">Last Name</th>
                            <th scope="col" style="width: 14.5%">Date of Birth</th>
                            <th scope="col" style="width: 22%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($searchResults as $patient)
                            <tr wire:key="patient-{{ $patient->hpercode }}" class="hover:bg-base-200">
                                <td class="whitespace-nowrap">
                                    <span class="font-mono">{{ $patient->hpercode }}</span>
                                    <button type="button" class="btn btn-ghost btn-xs"
                                        x-data="{ copied: false }" @click="await navigator.clipboard.writeText(@js($patient->hpercode)); copied = true; setTimeout(() => copied = false, 1500)"
                                        x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                                </td>
                                <td>{{ $patient->patfirst }}</td>
                                <td>{{ $patient->patmiddle ?: '—' }}</td>
                                <td>{{ $patient->patlast }}</td>
                                <td class="whitespace-nowrap">{{ $patient->patbdate?->format('F j, Y') ?? '—' }}</td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="selectPatient(@js($patient->hpercode))">Encounters</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-base-content/65">
                                    {{ $hasSearched ? 'No patients match your search.' : 'Enter patient details above to search.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- New Patient Modal --}}
    <x-mary-modal wire:model="showNewPatientModal" title="Create New Patient" class="backdrop-blur">
        <div class="space-y-4">
            <x-mary-input label="First Name *" wire:model.defer="newPatientFirstName" placeholder="Enter first name"
                icon="o-user" />

            <x-mary-input label="Middle Name" wire:model.defer="newPatientMiddleName" placeholder="Enter middle name"
                icon="o-user" />

            <x-mary-input label="Last Name *" wire:model.defer="newPatientLastName" placeholder="Enter last name"
                icon="o-user" />

            <x-mary-radio label="Sex *" wire:model.defer="newPatientSex" :options="[['id' => 'M', 'name' => 'Male'], ['id' => 'F', 'name' => 'Female']]" />
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="closeNewPatientModal" class="btn-ghost" />
            <x-mary-button label="Create & Continue" wire:click="createNewPatient" spinner="createNewPatient"
                class="btn-success" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Keyboard Shortcuts --}}
    @script
        <script>
            document.addEventListener('keydown', (e) => {
                // Check if modal is open
                const modalOpen = document.querySelector('.modal-open') || document.querySelector('[role="dialog"]');

                // Don't trigger if user is typing in a modal or textarea
                if (e.target.tagName === 'TEXTAREA' || modalOpen) {
                    return;
                }

                // Escape to clear
                if (e.key === 'Escape') {
                    e.preventDefault();
                    $wire.call('clearSearch');
                }
            });

            // Focus first input on component mount
            setTimeout(() => {
                const firstInput = document.getElementById('registry-searchHpercode');
                if (firstInput) {
                    firstInput.focus();
                }
            }, 100);
        </script>
    @endscript
</div>
