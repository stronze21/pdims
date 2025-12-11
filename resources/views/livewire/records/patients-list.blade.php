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

    <div class="container px-4 py-4 mx-auto space-y-4">

        {{-- Compact Search Panel --}}
        <x-mary-card shadow separator class="bg-base-100">
            <div class="flex items-end gap-3">
                <div class="flex-1 grid grid-cols-1 gap-3 md:grid-cols-5">
                    <x-mary-input label="Hospital #" wire:model.defer="searchHpercode" placeholder="Hospital #"
                        icon="o-hashtag" inline />

                    <x-mary-input label="First Name" wire:model.defer="searchFirstName" placeholder="First name"
                        icon="o-user" inline />

                    <x-mary-input label="Middle" wire:model.defer="searchMiddleName" placeholder="Middle" icon="o-user"
                        inline />

                    <x-mary-input label="Last Name" wire:model.defer="searchLastName" placeholder="Last name"
                        icon="o-user" inline />

                    <x-mary-input label="Birth Date" wire:model.defer="searchDob" type="date" icon="o-cake"
                        inline />
                </div>

                <div class="flex gap-2">
                    <x-mary-button icon="o-x-mark" wire:click="clearSearch" class="btn-ghost btn-sm"
                        tooltip="Clear (Esc)" spinner />
                    <x-mary-button label="Search" icon="o-magnifying-glass" wire:click="searchPatients"
                        class="btn-primary btn-sm" tooltip="Search (Enter)" spinner="searchPatients" />
                    <x-mary-button icon="o-user-plus" wire:click="openNewPatientModal" class="btn-success btn-sm"
                        tooltip="New Patient" />
                </div>
            </div>
        </x-mary-card>

        {{-- Two Column Layout: Results | Patient Info + Encounters --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">

            {{-- Search Results - 3 columns --}}
            <div class="lg:col-span-3">
                <x-mary-card shadow class="bg-base-100 h-[calc(100vh-280px)]">
                    <x-slot:title>
                        <div class="flex items-center justify-between">
                            <span class="text-base font-bold">
                                Search Results
                                @if ($searchResults->count() > 0)
                                    <x-mary-badge value="{{ $searchResults->count() }}"
                                        class="ml-2 badge-primary badge-sm" />
                                @endif
                            </span>
                        </div>
                    </x-slot:title>

                    <div class="overflow-y-auto h-[calc(100vh-360px)]">
                        @if ($hasSearched && $searchResults->count() > 0)
                            <table class="table w-full table-zebra table-xs">
                                <thead class="sticky top-0 z-10 bg-base-200">
                                    <tr class="text-xs uppercase">
                                        <th class="w-24">Hospital #</th>
                                        <th>Patient Name</th>
                                        <th class="w-16">Sex</th>
                                        <th class="w-24">Birth Date</th>
                                        <th class="w-20">Age</th>
                                        <th class="w-20">Status</th>
                                        <th class="w-16"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($searchResults as $patient)
                                        <tr class="transition-colors hover:bg-base-200 cursor-pointer {{ $selectedHpercode === $patient->hpercode ? 'bg-primary/20 font-medium' : '' }}"
                                            wire:key="patient-{{ $patient->hpercode }}"
                                            wire:click="selectPatient('{{ $patient->hpercode }}')">
                                            <td class="font-mono text-xs">{{ $patient->hpercode }}</td>
                                            <td>
                                                <div class="font-medium text-sm">{{ $patient->fullname() }}</div>
                                                @if ($patient->patbplace)
                                                    <div class="text-xs opacity-60">
                                                        {{ Str::limit($patient->patbplace, 25) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <x-mary-badge :value="$patient->patsex"
                                                    class="{{ $patient->patsex === 'M' ? 'badge-info' : 'badge-secondary' }} badge-xs" />
                                            </td>
                                            <td class="text-xs">{{ $patient->bdate_format1() }}</td>
                                            <td class="text-xs">{{ $patient->ageInYears() }}y</td>
                                            <td>
                                                <x-mary-badge :value="$patient->csstat()" class="badge-ghost badge-xs" />
                                            </td>
                                            <td>
                                                <x-mary-icon name="o-chevron-right" class="w-4 h-4" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @elseif($hasSearched)
                            <div class="flex flex-col items-center justify-center h-full py-12">
                                <x-mary-icon name="o-magnifying-glass-circle" class="w-16 h-16 text-gray-300" />
                                <p class="mt-3 font-medium text-gray-500">No patients found</p>
                                <p class="text-sm text-gray-400">Try different search criteria</p>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center h-full py-12">
                                <x-mary-icon name="o-user-group" class="w-16 h-16 text-gray-300" />
                                <p class="mt-3 font-medium text-gray-500">Start searching</p>
                                <p class="text-sm text-gray-400">Press Enter to search</p>
                            </div>
                        @endif
                    </div>
                </x-mary-card>
            </div>

            {{-- Patient Details & Encounters - 2 columns --}}
            <div class="lg:col-span-2 space-y-4">
                @if ($selectedPatient)
                    {{-- Compact Patient Card --}}
                    <x-mary-card shadow class="bg-gradient-to-br from-primary/10 to-secondary/10">
                        <div class="space-y-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase">Hospital #</p>
                                    <p class="text-lg font-bold font-mono text-primary">
                                        {{ $selectedPatient->hpercode }}</p>
                                </div>
                                <x-mary-button label="Walk-In" icon="o-arrow-right-circle" wire:click="initiateWalkIn"
                                    spinner="initiateWalkIn" class="btn-error btn-sm" />
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase">Patient Name</p>
                                <p class="text-base font-bold">{{ $selectedPatient->fullname() }}</p>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <p class="font-semibold text-gray-500 uppercase">Sex</p>
                                    <x-mary-badge :value="$selectedPatient->gender()"
                                        class="{{ $selectedPatient->patsex === 'M' ? 'badge-info' : 'badge-secondary' }} badge-xs" />
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-500 uppercase">Age</p>
                                    <p class="font-medium">{{ $selectedPatient->ageInYears() }} years</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-500 uppercase">Status</p>
                                    <x-mary-badge :value="$selectedPatient->csstat()" class="badge-ghost badge-xs" />
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase">Birth Date</p>
                                <p class="text-sm font-medium">{{ $selectedPatient->bdate_format1() }}</p>
                            </div>
                        </div>
                    </x-mary-card>

                    {{-- Encounters Card --}}
                    <x-mary-card shadow class="bg-base-100">
                        <x-slot:title>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <x-mary-icon name="o-clipboard-document-list" class="w-4 h-4" />
                                    <span class="text-sm font-bold">Encounters</span>
                                </div>
                                @if (count($patientEncounters) > 0)
                                    <x-mary-badge value="{{ count($patientEncounters) }}"
                                        class="badge-neutral badge-sm" />
                                @endif
                            </div>
                        </x-slot:title>

                        <div class="space-y-2 overflow-y-auto max-h-[calc(100vh-520px)]">
                            @forelse($patientEncounters as $encounter)
                                <div wire:click="viewEncounter('{{ $encounter['enccode'] }}')"
                                    class="p-3 transition-all border rounded-lg cursor-pointer hover:shadow-md
                                        {{ $encounter['status_badge'] === 'active' ? 'bg-success/5 border-success/30 hover:border-success' : 'bg-base-200 border-base-300 hover:border-base-400' }}"
                                    wire:key="enc-{{ $encounter['enccode'] }}">
                                    <div class="flex items-start justify-between mb-1">
                                        <div class="flex gap-1">
                                            <x-mary-badge :value="$encounter['toecode']"
                                                class="badge-xs font-bold {{ $encounter['status_badge'] === 'active' ? 'badge-success' : 'badge-neutral' }}" />
                                            <x-mary-badge :value="ucfirst($encounter['status_badge'])" class="badge-xs badge-outline" />
                                        </div>
                                        <x-mary-icon name="o-chevron-right" class="w-3 h-3 opacity-50" />
                                    </div>

                                    @if ($encounter['diagtext'])
                                        <p class="text-xs text-gray-700 line-clamp-2 mb-1">
                                            {!! $encounter['diagtext'] !!}
                                        </p>
                                    @else
                                        <p class="text-xs italic text-gray-400 mb-1">No diagnosis</p>
                                    @endif

                                    <div class="flex items-center gap-1 text-xs text-gray-500">
                                        <x-mary-icon name="o-calendar" class="w-3 h-3" />
                                        {{ \Carbon\Carbon::parse($encounter['encdate'])->format('M d, Y h:i A') }}
                                    </div>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center py-8 text-center">
                                    <x-mary-icon name="o-clipboard-document-list" class="w-12 h-12 text-gray-300" />
                                    <p class="mt-2 text-sm text-gray-500">No encounters</p>
                                </div>
                            @endforelse
                        </div>
                    </x-mary-card>
                @else
                    <x-mary-card shadow class="bg-base-100 h-[calc(100vh-280px)]">
                        <div class="flex flex-col items-center justify-center h-full text-center">
                            <x-mary-icon name="o-user-circle" class="w-20 h-20 text-gray-300" />
                            <p class="mt-3 font-medium text-gray-500">No Patient Selected</p>
                            <p class="text-sm text-gray-400">Select a patient to view details</p>
                        </div>
                    </x-mary-card>
                @endif
            </div>
        </div>
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

                // Enter to search (allow in input fields)
                if (e.key === 'Enter' && !e.ctrlKey && !e.shiftKey) {
                    e.preventDefault();
                    $wire.call('searchPatients');
                }

                // Escape to clear
                if (e.key === 'Escape') {
                    e.preventDefault();
                    $wire.call('clearSearch');
                }
            });

            // Focus first input on component mount
            setTimeout(() => {
                const firstInput = document.querySelector('input[wire\\:model\\.defer="searchHpercode"]');
                if (firstInput) {
                    firstInput.focus();
                }
            }, 100);
        </script>
    @endscript
</div>
