<div class="drug-library-page space-y-4">
    <style>
        .drug-library-page .choices,
        .drug-library-page .choices__inner,
        .drug-library-page .choices__list--single {
            min-width: 0 !important;
            max-width: 100% !important;
        }

        .drug-library-page .choices__inner {
            overflow: hidden !important;
        }

        .drug-library-page .choices__list--single .choices__item,
        .drug-library-page .choices__list--multiple .choices__item {
            display: block !important;
            max-width: 100% !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }

        .drug-library-page .choices__list--dropdown .choices__item {
            white-space: normal;
        }

        .drug-library-page .choices__list--multiple {
            display: flex !important;
            min-width: 0 !important;
            overflow: hidden !important;
            padding-right: 2rem !important;
        }

        .drug-library-page .choices__list--multiple .choices__item,
        .drug-library-page .choices__list--multiple .choices__item[data-item] {
            flex: 0 1 auto !important;
            min-width: 0 !important;
            max-width: 100% !important;
            padding-right: 1.75rem !important;
            position: relative !important;
        }

        .drug-library-page .choices__list--multiple .choices__button {
            position: absolute !important;
            right: 0.4rem !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            margin: 0 !important;
        }

        .drug-library-page .modal-box {
            overflow-x: hidden !important;
        }
    </style>

    <script>
        (() => {
            if (window.drugLibraryChoicesCloserLoaded) {
                return;
            }

            window.drugLibraryChoicesCloserLoaded = true;

            const closeChoices = (event) => {
                const page = document.querySelector('.drug-library-page');

                if (
                    !page ||
                    event.target.closest('.drug-library-page label.select') ||
                    event.target.closest('.drug-library-page .absolute.z-10')
                ) {
                    return;
                }

                page.querySelectorAll('label.select').forEach((select) => {
                    select.dispatchEvent(new KeyboardEvent('keyup', {
                        key: 'Escape',
                        bubbles: true,
                    }));
                });
            };

            document.addEventListener('mousedown', closeChoices, true);
            document.addEventListener('focusin', closeChoices, true);
        })();
    </script>

    <x-mary-header title="Drug & Medicine Library" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input wire:model.live.debounce.300ms="search" placeholder="Search {{ str_replace('-', ' ', $tab) }}..."
                icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            @if ($tab === 'drugs')
                <x-mary-button icon="o-plus" wire:click="createDrug" class="btn-primary">Add Drug</x-mary-button>
            @elseif ($tab === 'groups')
                <x-mary-button icon="o-plus" wire:click="createGroup" class="btn-primary">Add PNDF Group</x-mary-button>
            @elseif (in_array($tab, ['generics', 'forms', 'routes', 'strengths'], true))
                <x-mary-button icon="o-plus" wire:click="createLookup('{{ $tab }}')" class="btn-primary">
                    Add {{ $lookupLabel }}
                </x-mary-button>
            @endif
        </x-slot:actions>
    </x-mary-header>

    @if (session('success'))
        <x-mary-alert icon="o-check-circle" class="alert-success">{{ session('success') }}</x-mary-alert>
    @endif

    @if (session('error'))
        <x-mary-alert icon="o-x-circle" class="alert-error">{{ session('error') }}</x-mary-alert>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach ([
            'drugs' => ['Drugs & Medicines', 'o-beaker'],
            'generics' => ['Generics', 'o-book-open'],
            'groups' => ['PNDF Groups', 'o-rectangle-stack'],
            'forms' => ['Forms', 'o-archive-box'],
            'routes' => ['Routes', 'o-map'],
            'strengths' => ['Strengths', 'o-scale'],
            'subs' => ['Sub Records', 'o-squares-2x2'],
        ] as $key => [$label, $icon])
            <button wire:click="$set('tab', '{{ $key }}')"
                class="btn btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-outline' }}">
                <x-mary-icon name="{{ $icon }}" class="h-4 w-4" />
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-lg border border-base-300 bg-base-100 shadow-sm">
        @if ($tab === 'drugs')
            <table class="table table-sm table-zebra">
                <thead class="bg-base-200">
                    <tr>
                        <th>Code</th>
                        <th>Generic / Description</th>
                        <th>Strength</th>
                        <th>Form</th>
                        <th>Route</th>
                        <th>PNDF</th>
                        <th>Status</th>
                        <th>Subs</th>
                        <th class="w-20 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="drug-{{ $row->dmdcomb }}-{{ $row->dmdctr }}">
                            <td class="font-mono text-xs">{{ $row->dmdcomb }}-{{ $row->dmdctr }}</td>
                            <td>
                                <div class="font-semibold">{{ $row->gendesc ?: $row->drug_concat }}</div>
                                <div class="text-xs text-base-content/60">
                                    {{ $row->dmdnost }}
                                    @if ($row->brandname)
                                        <span class="badge badge-ghost badge-xs ml-1">{{ $row->brandname }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $row->stredesc ?: '-' }}</td>
                            <td>{{ $row->formdesc ?: '-' }}</td>
                            <td>{{ $row->rtedesc ?: '-' }}</td>
                            <td>
                                <span class="badge badge-sm {{ $row->dmdpndf === 'Y' ? 'badge-info' : 'badge-ghost' }}">
                                    {{ $row->dmdpndf === 'Y' ? 'PNDF' : 'Non-PNDF' }}
                                </span>
                            </td>
                            <td>
                                <button type="button"
                                    wire:click="toggleDrugStatus('{{ $row->dmdcomb }}', '{{ $row->dmdctr }}', '{{ $row->dmdstat }}')"
                                    wire:mary-confirm="Change this drug item to {{ $row->dmdstat === 'A' ? 'Inactive' : 'Active' }}?"
                                    class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="toggle toggle-success toggle-sm pointer-events-none"
                                        @checked($row->dmdstat === 'A') tabindex="-1" />
                                    <span class="text-xs font-semibold">{{ $row->dmdstat === 'A' ? 'Active' : 'Inactive' }}</span>
                                </button>
                            </td>
                            <td>{{ $row->sub_count }}</td>
                            <td class="text-center">
                                <x-mary-button icon="o-pencil-square" class="btn-ghost btn-xs"
                                    wire:click="editDrug('{{ $row->dmdcomb }}', '{{ $row->dmdctr }}')" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-base-content/60">No drug items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @elseif ($tab === 'groups')
            <table class="table table-sm table-zebra">
                <thead class="bg-base-200">
                    <tr>
                        <th>Group Code</th>
                        <th>Generic</th>
                        <th>Major Class</th>
                        <th>Sub Classes</th>
                        <th>Status</th>
                        <th class="w-20 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="group-{{ $row->grpcode }}">
                            <td class="font-mono text-xs">{{ $row->grpcode }}</td>
                            <td>{{ $row->gendesc ?: $row->gencode }}</td>
                            <td>
                                <div class="font-mono text-xs">{{ $row->dmcode }}</div>
                                <div class="text-xs text-base-content/70">{{ $row->dmdesc ?: '-' }}</div>
                            </td>
                            <td class="min-w-72 text-xs">
                                @forelse ([
                                    [$row->dms1key, $row->dms1desc],
                                    [$row->dms2key, $row->dms2desc],
                                    [$row->dms3key, $row->dms3desc],
                                    [$row->dms4key, $row->dms4desc],
                                ] as [$key, $description])
                                    @if ($key)
                                        <div>
                                            <span class="font-mono">{{ $key }}</span>
                                            <span class="text-base-content/70">- {{ $description ?: 'No description' }}</span>
                                        </div>
                                    @endif
                                @empty
                                    -
                                @endforelse
                                @if (! collect([$row->dms1key, $row->dms2key, $row->dms3key, $row->dms4key])->filter()->count())
                                    -
                                @endif
                            </td>
                            <td>
                                <button type="button"
                                    wire:click="toggleGroupStatus('{{ $row->grpcode }}', '{{ $row->grpstat }}')"
                                    wire:mary-confirm="Change this PNDF group to {{ $row->grpstat === 'A' ? 'Inactive' : 'Active' }}?"
                                    class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="toggle toggle-success toggle-sm pointer-events-none"
                                        @checked($row->grpstat === 'A') tabindex="-1" />
                                    <span class="text-xs font-semibold">{{ $row->grpstat === 'A' ? 'Active' : 'Inactive' }}</span>
                                </button>
                            </td>
                            <td class="text-center">
                                <x-mary-button icon="o-pencil-square" class="btn-ghost btn-xs"
                                    wire:click="editGroup('{{ $row->grpcode }}')" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-base-content/60">No PNDF groups found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @elseif ($tab === 'subs')
            <table class="table table-sm table-zebra">
                <thead class="bg-base-200">
                    <tr>
                        <th>Drug</th>
                        <th>Sub Code / Description</th>
                        <th>Barcode</th>
                        <th class="text-right">Beg Bal</th>
                        <th class="text-right">Stock Bal</th>
                        <th class="text-right">Reorder</th>
                        <th>Status</th>
                        <th class="w-20 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="sub-{{ $row->dmdcomb }}-{{ $row->dmdctr }}-{{ $row->dmhdrsub }}">
                            <td>
                                <div class="font-semibold">{{ $row->drug_concat }}</div>
                                <div class="font-mono text-xs text-base-content/60">{{ $row->dmdcomb }}-{{ $row->dmdctr }}</div>
                            </td>
                            <td>
                                <div class="font-mono text-xs">{{ $row->dmhdrsub }}</div>
                                <div class="text-xs text-base-content/70">{{ $row->chrgdesc ?: 'No hcharge description' }}</div>
                            </td>
                            <td>{{ $row->barcode ?: '-' }}</td>
                            <td class="text-right">{{ number_format((int) $row->begbal) }}</td>
                            <td class="text-right">{{ number_format((int) $row->stockbal) }}</td>
                            <td class="text-right">{{ number_format((int) $row->reorder_level) }}</td>
                            <td>
                                <button type="button"
                                    wire:click="toggleSubStatus('{{ $row->dmdcomb }}', '{{ $row->dmdctr }}', '{{ $row->dmhdrsub }}', '{{ $row->statusMed }}')"
                                    wire:mary-confirm="Change this drug sub-record to {{ $row->statusMed === 'A' ? 'Inactive' : 'Active' }}?"
                                    class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="toggle toggle-success toggle-sm pointer-events-none"
                                        @checked($row->statusMed === 'A') tabindex="-1" />
                                    <span class="text-xs font-semibold">{{ $row->statusMed === 'A' ? 'Active' : 'Inactive' }}</span>
                                </button>
                            </td>
                            <td class="text-center">
                                <x-mary-button icon="o-pencil-square" class="btn-ghost btn-xs"
                                    wire:click="editSub('{{ $row->dmdcomb }}', '{{ $row->dmdctr }}', '{{ $row->dmhdrsub }}')" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-base-content/60">No drug sub-records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="table table-sm table-zebra">
                <thead class="bg-base-200">
                    <tr>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="w-20 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="lookup-{{ $tab }}-{{ $row->code }}">
                            <td class="font-mono text-xs">{{ $row->code }}</td>
                            <td class="font-semibold">{{ $row->description }}</td>
                            <td>
                                <button type="button"
                                    wire:click="toggleLookupStatus('{{ $tab }}', '{{ $row->code }}', '{{ $row->status }}')"
                                    wire:mary-confirm="Change this {{ strtolower($lookupLabel) }} to {{ $row->status === 'A' ? 'Inactive' : 'Active' }}?"
                                    class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="toggle toggle-success toggle-sm pointer-events-none"
                                        @checked($row->status === 'A') tabindex="-1" />
                                    <span class="text-xs font-semibold">{{ $row->status === 'A' ? 'Active' : 'Inactive' }}</span>
                                </button>
                            </td>
                            <td class="text-center">
                                <x-mary-button icon="o-pencil-square" class="btn-ghost btn-xs"
                                    wire:click="editLookup('{{ $tab }}', '{{ $row->code }}')" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-base-content/60">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <div>{{ $rows->links() }}</div>

    <x-mary-modal wire:model="drugModal" title="{{ $editingDmdcomb ? 'Edit' : 'Add' }} Drug & Medicine" separator
        box-class="w-11/12 max-w-5xl">
        <x-mary-form wire:submit="saveDrug">
            <div class="grid gap-4 md:grid-cols-2">
                <x-mary-choices-offline label="PNDF Group / Generic" wire:model="drugForm.grpcode" :options="$groups"
                    option-value="id" option-label="name" placeholder="Search PNDF group" single searchable clearable required />
                <x-mary-input label="Drug Description / Name" wire:model="drugForm.dmdnost" required />
                <x-mary-choices-offline label="Strength" wire:model="drugForm.strecode" :options="$strengths"
                    option-value="id" option-label="name" placeholder="Search strength" single searchable clearable required />
                <x-mary-choices-offline label="Form" wire:model="drugForm.formcode" :options="$forms"
                    option-value="id" option-label="name" placeholder="Search form" single searchable clearable required />
                <x-mary-choices-offline label="Route" wire:model="drugForm.rtecode" :options="$routes"
                    option-value="id" option-label="name" placeholder="Search route" single searchable clearable />
                <x-mary-input label="Brand Name" wire:model="drugForm.brandname" />
                <x-mary-choices-offline label="PNDF Flag" wire:model="drugForm.dmdpndf" :options="[
                    ['id' => 'Y', 'name' => 'PNDF'],
                    ['id' => 'N', 'name' => 'Non-PNDF'],
                ]" option-value="id" option-label="name" placeholder="Search PNDF flag" single searchable required />
                <x-mary-choices-offline label="Status" wire:model="drugForm.dmdstat" :options="[
                    ['id' => 'A', 'name' => 'Active'],
                    ['id' => 'I', 'name' => 'Inactive'],
                ]" option-value="id" option-label="name" placeholder="Search status" single searchable required />
                <x-mary-input label="Rx / OTC Flag" wire:model="drugForm.dmdrxot" maxlength="1" />
                <x-mary-input label="Master Barcode" wire:model="drugForm.barcode" />
                <x-mary-textarea label="Remarks" wire:model="drugForm.dmdrem" rows="2" class="md:col-span-2" />
            </div>

            <div class="divider">hdmhdrsub</div>

            <div class="grid gap-4 md:grid-cols-3">
                <x-mary-choices-offline label="Sub Code / Fund Source" wire:model="drugForm.dmhdrsub" :options="$chargeCodes"
                    option-value="id" option-label="name" placeholder="Search fund source" single searchable clearable required />
                <x-mary-input label="Sub Barcode" wire:model="drugForm.sub_barcode" />
                <x-mary-input label="Beginning Balance" type="number" min="0" wire:model="drugForm.begbal" />
                <x-mary-input label="Stock Balance" type="number" min="0" wire:model="drugForm.stockbal" />
                <x-mary-input label="Reorder Level" type="number" min="0" wire:model="drugForm.reorder_level" />
                <x-mary-input label="Reorder Point" type="number" min="0" wire:model="drugForm.rpoint" />
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('drugModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="saveDrug" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    <x-mary-modal wire:model="lookupModal" title="{{ $lookupOriginalCode ? 'Edit' : 'Add' }} {{ $lookupLabel }}" separator>
        <x-mary-form wire:submit="saveLookup">
            <x-mary-input label="Code" wire:model="lookupForm.code" maxlength="5" required />
            <x-mary-input label="Description" wire:model="lookupForm.description" required />
            <x-mary-choices-offline label="Status" wire:model="lookupForm.status" :options="[
                ['id' => 'A', 'name' => 'Active'],
                ['id' => 'I', 'name' => 'Inactive'],
            ]" option-value="id" option-label="name" placeholder="Search status" single searchable required />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('lookupModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="saveLookup" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    <x-mary-modal wire:model="groupModal" title="{{ $editingGrpcode ? 'Edit' : 'Add' }} PNDF Group" separator
        box-class="w-11/12 max-w-4xl">
        <x-mary-form wire:submit="saveGroup">
            <div class="grid gap-4 md:grid-cols-2">
                <x-mary-input label="Group Code" wire:model="groupForm.grpcode" maxlength="10" required />
                <x-mary-choices-offline label="Status" wire:model="groupForm.grpstat" :options="[
                    ['id' => 'A', 'name' => 'Active'],
                    ['id' => 'I', 'name' => 'Inactive'],
                ]" option-value="id" option-label="name" placeholder="Search status" single searchable required />
                <div class="min-w-0 md:col-span-2">
                    <x-mary-choices-offline label="Generic" wire:model="groupForm.gencode" :options="$generics"
                        option-value="id" option-label="name" placeholder="Search generic" single searchable clearable required />
                </div>
                <div class="min-w-0 md:col-span-2">
                    <x-mary-choices-offline label="Major Class" wire:model.live="groupForm.dmcode" :options="$majorClasses"
                        option-value="id" option-label="name" placeholder="Search major class" single searchable clearable required />
                </div>
                <div class="min-w-0 md:col-span-2">
                    <x-mary-choices-offline label="Sub Class 1" wire:model.live="groupForm.dms1key" :options="$subClass1Options"
                        option-value="id" option-label="name" placeholder="Search sub class 1" single searchable clearable />
                </div>
                <div class="min-w-0 md:col-span-2">
                    <x-mary-choices-offline label="Sub Class 2" wire:model.live="groupForm.dms2key" :options="$subClass2Options"
                        option-value="id" option-label="name" placeholder="Search sub class 2" single searchable clearable />
                </div>
                <div class="min-w-0 md:col-span-2">
                    <x-mary-choices-offline label="Sub Class 3" wire:model.live="groupForm.dms3key" :options="$subClass3Options"
                        option-value="id" option-label="name" placeholder="Search sub class 3" single searchable clearable />
                </div>
                <div class="min-w-0 md:col-span-2">
                    <x-mary-choices-offline label="Sub Class 4" wire:model="groupForm.dms4key" :options="$subClass4Options"
                        option-value="id" option-label="name" placeholder="Search sub class 4" single searchable clearable />
                </div>
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('groupModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="saveGroup" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    <x-mary-modal wire:model="subModal" title="Edit Drug Sub Record" separator>
        <x-mary-form wire:submit="saveSub">
            <div class="grid gap-4 md:grid-cols-2">
                <x-mary-choices-offline label="Sub Code / Fund Source" wire:model="subForm.dmhdrsub" :options="$chargeCodes"
                    option-value="id" option-label="name" placeholder="Search fund source" single searchable clearable required />
                <x-mary-input label="Barcode" wire:model="subForm.barcode" />
                <x-mary-input label="Beginning Balance" type="number" min="0" wire:model="subForm.begbal" />
                <x-mary-input label="Stock Balance" type="number" min="0" wire:model="subForm.stockbal" />
                <x-mary-input label="Reorder Level" type="number" min="0" wire:model="subForm.reorder_level" />
                <x-mary-input label="Reorder Point" type="number" min="0" wire:model="subForm.rpoint" />
                <x-mary-choices-offline label="Status" wire:model="subForm.statusMed" :options="[
                    ['id' => 'A', 'name' => 'Active'],
                    ['id' => 'I', 'name' => 'Inactive'],
                ]" option-value="id" option-label="name" placeholder="Search status" single searchable required />
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('subModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="saveSub" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
