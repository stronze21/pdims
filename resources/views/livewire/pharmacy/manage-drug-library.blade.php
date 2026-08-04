<div class="drug-library-page w-full max-w-none space-y-3 text-[13px]">
    <style>
        .drug-library-page {
            width: 100%;
            max-width: none;
        }

        .drug-library-page .fieldset-legend,
        .drug-library-page .fieldset-label {
            font-size: 0.75rem !important;
            line-height: 1rem !important;
        }

        .drug-library-page :where(.input, .select, .textarea, .btn, .badge),
        .drug-library-page .choices,
        .drug-library-page .choices__inner,
        .drug-library-page .choices__item {
            font-size: 0.75rem !important;
        }

        .drug-library-page :where(.input, .select) {
            min-height: 2rem;
        }

        .drug-library-page .table {
            width: 100%;
            font-size: 0.75rem;
        }

        .drug-library-page .table :where(th, td) {
            padding-block: 0.45rem;
            padding-inline: 0.65rem;
        }

        .drug-library-page .table th {
            font-size: 0.6875rem;
            letter-spacing: 0.025em;
            text-transform: uppercase;
        }

        .drug-library-page .modal-box {
            font-size: 0.8125rem;
        }

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

        .drug-library-page [x-cloak] {
            display: none !important;
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
        <x-slot:actions>
            @if ($tab === 'drugs')
                <a href="{{ route('pharmacy.drug-library.import') }}" wire:navigate class="btn btn-outline btn-sm">
                    <x-mary-icon name="o-arrow-up-tray" class="h-4 w-4" />
                    Import Drugs
                </a>
                <x-mary-button icon="o-plus" wire:click="createDrug" class="btn-primary">Add Drug</x-mary-button>
            @elseif ($tab === 'groups')
                <x-mary-button icon="o-plus" wire:click="createGroup" class="btn-primary">Add PNDF Group</x-mary-button>
            @elseif (in_array($tab, ['generics', 'forms', 'routes', 'strengths', 'packages', 'salts', 'units'], true))
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

    <div class="grid w-full grid-cols-2 gap-1.5 sm:grid-cols-3 md:grid-cols-5 xl:grid-cols-10">
        @foreach ([
            'drugs' => 'Drugs & Medicines',
            'generics' => 'Generics',
            'groups' => 'PNDF Groups',
            'forms' => 'Forms',
            'routes' => 'Routes',
            'strengths' => 'Strengths',
            'packages' => 'Packaging',
            'salts' => 'Salts',
            'units' => 'Units',
            'subs' => 'Sub Records',
        ] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                class="btn btn-sm w-full px-2 {{ $tab === $key ? 'btn-primary' : 'btn-outline' }}">
                <span
                    class="h-2 w-2 rounded-full {{ $tab === $key ? 'bg-primary-content' : 'bg-current opacity-60' }}"></span>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <section x-data="{ filtersOpen: false }"
        class="rounded-xl border border-base-300 bg-base-100 shadow-sm"
        x-bind:class="{ 'overflow-visible': filtersOpen, 'overflow-hidden': !filtersOpen }">
        <div class="flex items-center gap-2 p-3 sm:p-4" x-bind:class="{ 'border-b border-base-300': filtersOpen }">
            <button type="button" x-on:click="filtersOpen = !filtersOpen"
                class="flex min-w-0 flex-1 items-center gap-3 rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                x-bind:aria-expanded="filtersOpen" aria-controls="drug-library-filters">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-primary/10 text-primary">
                    <x-mary-icon name="o-funnel" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="font-semibold">Filter records</h2>
                        @if ($activeFilterCount)
                            <span class="badge badge-primary badge-sm">{{ $activeFilterCount }} active</span>
                        @endif
                    </div>
                    <p class="text-xs text-base-content/60">Narrow the current {{ strtolower(str_replace('-', ' ', $tab)) }} list.</p>
                </div>
                <span class="hidden text-xs font-medium text-base-content/60 sm:inline"
                    x-text="filtersOpen ? 'Hide filters' : 'Show filters'"></span>
                <x-mary-icon name="o-chevron-down"
                    class="h-5 w-5 shrink-0 text-base-content/60 transition-transform duration-200"
                    x-bind:class="{ 'rotate-180': filtersOpen }" />
            </button>

            @if ($activeFilterCount)
                <x-mary-button label="Clear" icon="o-x-mark" wire:click="clearFilters" class="btn-ghost btn-sm shrink-0" />
            @endif
        </div>

        <div id="drug-library-filters" x-cloak x-show="filtersOpen"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="grid w-full gap-3 bg-base-200/30 p-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
                <div class="sm:col-span-2">
                    <x-mary-input label="Search" wire:model.live.debounce.300ms="search"
                        placeholder="Code, description, brand, or barcode..." icon="o-magnifying-glass" clearable />
                </div>

                <x-mary-select label="Status" wire:model.live="statusFilter" :options="[
                    ['id' => 'A', 'name' => 'Active'],
                    ['id' => 'I', 'name' => 'Inactive'],
                ]" option-value="id" option-label="name" placeholder="All statuses" icon="o-adjustments-horizontal"
                    clearable />

                @if ($tab === 'drugs')
                    <x-mary-select label="PNDF" wire:model.live="pndfFilter" :options="[
                        ['id' => 'Y', 'name' => 'PNDF'],
                        ['id' => 'N', 'name' => 'Non-PNDF'],
                    ]" option-value="id" option-label="name" placeholder="All PNDF types" clearable />

                    <x-mary-select label="Classification" wire:model.live="rxotFilter" :options="[
                        ['id' => 'RXX', 'name' => 'With Prescription (RXX)'],
                        ['id' => 'OTC', 'name' => 'Over The Counter (OTC)'],
                    ]" option-value="id" option-label="name" placeholder="All classifications" clearable />

                    <x-mary-select label="Item Type" wire:model.live="brandFilter" :options="[
                        ['id' => 'generic', 'name' => 'Generic master'],
                        ['id' => 'branded', 'name' => 'Brand variant'],
                    ]" option-value="id" option-label="name" placeholder="Generic and branded" clearable />

                    <x-daisy-combobox label="PNDF Group / Generic" wire:model.live="groupFilter"
                        :options="$filterGroups" option-value="id" option-label="name" placeholder="All PNDF groups" single
                        searchable clearable />

                    <x-daisy-combobox label="Strength" wire:model.live="strengthFilter"
                        :options="$filterStrengths" option-value="id" option-label="name" placeholder="All strengths" single
                        searchable clearable />

                    <x-daisy-combobox label="Form" wire:model.live="formFilter" :options="$filterForms"
                        option-value="id" option-label="name" placeholder="All forms" single searchable clearable />

                    <x-daisy-combobox label="Route" wire:model.live="routeFilter" :options="$filterRoutes"
                        option-value="id" option-label="name" placeholder="All routes" single searchable clearable />
                @elseif ($tab === 'groups')
                    <x-daisy-combobox label="Major Classification" wire:model.live="majorClassFilter"
                        :options="$filterMajorClasses" option-value="id" option-label="name" placeholder="All major classes"
                        single searchable clearable />
                @elseif ($tab === 'subs')
                    <x-daisy-combobox label="Fund Source" wire:model.live="fundSourceFilter"
                        :options="$filterChargeCodes" option-value="id" option-label="name" placeholder="All fund sources"
                        single searchable clearable />
                @endif

                <x-mary-select label="Rows per page" wire:model.live="perPage" :options="[
                    ['id' => 15, 'name' => '15 rows'],
                    ['id' => 25, 'name' => '25 rows'],
                    ['id' => 50, 'name' => '50 rows'],
                    ['id' => 100, 'name' => '100 rows'],
                ]" option-value="id" option-label="name" icon="o-list-bullet" />
        </div>
    </section>

    <div class="overflow-x-auto rounded-lg border border-base-300 bg-base-100 shadow-sm">
        @if ($tab === 'drugs')
            <table class="table table-sm table-zebra w-full">
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
                                <div class="flex justify-center gap-1">
                                    <button type="button" class="btn btn-ghost btn-square btn-xs" title="Edit drug"
                                        aria-label="Edit drug"
                                        wire:click="editDrug('{{ $row->dmdcomb }}', '{{ $row->dmdctr }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931ZM19.5 7.125 16.875 4.5M18 13.5V19.125A1.875 1.875 0 0 1 16.125 21H4.875A1.875 1.875 0 0 1 3 19.125V7.875A1.875 1.875 0 0 1 4.875 6H10.5" />
                                        </svg>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-square btn-xs"
                                        title="Add brand variant" aria-label="Add brand variant"
                                        wire:click="createBrandVariant('{{ $row->dmdcomb }}', '{{ $row->dmdctr }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9.568 3.057A2.25 2.25 0 0 1 11.159 2.4h5.216a2.25 2.25 0 0 1 2.25 2.25v5.216a2.25 2.25 0 0 1-.659 1.591l-7.5 7.5a2.25 2.25 0 0 1-3.182 0l-3.966-3.966a2.25 2.25 0 0 1 0-3.182l6.25-6.252Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.375 6.75h.008v.008h-.008V6.75Z" />
                                        </svg>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-square btn-xs"
                                        title="Add fund source" aria-label="Add fund source"
                                        wire:click="createSub('{{ $row->dmdcomb }}', '{{ $row->dmdctr }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </button>
                                </div>
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
            <table class="table table-sm table-zebra w-full">
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
                                <button type="button" class="btn btn-ghost btn-square btn-xs" title="Edit PNDF group"
                                    aria-label="Edit PNDF group" wire:click="editGroup('{{ $row->grpcode }}')">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931ZM19.5 7.125 16.875 4.5M18 13.5V19.125A1.875 1.875 0 0 1 16.125 21H4.875A1.875 1.875 0 0 1 3 19.125V7.875A1.875 1.875 0 0 1 4.875 6H10.5" />
                                    </svg>
                                </button>
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
            <table class="table table-sm table-zebra w-full">
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
                            <td class="text-right">{{ number_format((float) $row->begbal, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->stockbal, 2) }}</td>
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
                                <button type="button" class="btn btn-ghost btn-square btn-xs"
                                    title="Edit fund source" aria-label="Edit fund source"
                                    wire:click="editSub('{{ $row->dmdcomb }}', '{{ $row->dmdctr }}', '{{ $row->dmhdrsub }}')">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931ZM19.5 7.125 16.875 4.5M18 13.5V19.125A1.875 1.875 0 0 1 16.125 21H4.875A1.875 1.875 0 0 1 3 19.125V7.875A1.875 1.875 0 0 1 4.875 6H10.5" />
                                    </svg>
                                </button>
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
            <table class="table table-sm table-zebra w-full">
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
                                <button type="button" class="btn btn-ghost btn-square btn-xs"
                                    title="Edit {{ strtolower($lookupLabel) }}"
                                    aria-label="Edit {{ strtolower($lookupLabel) }}"
                                    wire:click="editLookup('{{ $tab }}', '{{ $row->code }}')">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931ZM19.5 7.125 16.875 4.5M18 13.5V19.125A1.875 1.875 0 0 1 16.125 21H4.875A1.875 1.875 0 0 1 3 19.125V7.875A1.875 1.875 0 0 1 4.875 6H10.5" />
                                    </svg>
                                </button>
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

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-base-content/60">
            @if ($rows->total())
                Showing {{ number_format($rows->firstItem()) }}–{{ number_format($rows->lastItem()) }} of
                {{ number_format($rows->total()) }} records
            @else
                No matching records
            @endif
        </p>
        <div>{{ $rows->links() }}</div>
    </div>

    <x-mary-modal wire:model="drugModal"
        title="{{ $editingDmdcomb ? 'Edit Drug & Medicine' : ($brandSourceDmdcomb ? 'Add Brand Variant' : 'Add Drug & Medicine') }}"
        separator
        box-class="w-[96vw] max-w-[96vw]">
        @php($sharedDefinitionLocked = filled($brandSourceDmdcomb) || (filled($editingDmdcomb) && (int) $editingDmdctr > 1))
        <x-mary-form wire:submit="saveDrug">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                <x-mary-choices-offline label="Drug Classification" wire:model="drugForm.dmdrxot" :options="[
                    ['id' => 'RXX', 'name' => 'With Prescription (RXX)'],
                    ['id' => 'OTC', 'name' => 'Over The Counter (OTC)'],
                ]" option-value="id" option-label="name" placeholder="Select classification" single required
                    :disabled="$sharedDefinitionLocked" />
                <x-mary-choices-offline label="PNDF Group / Generic" wire:model="drugForm.grpcode" :options="$groups"
                    option-value="id" option-label="name" placeholder="Search PNDF group" single searchable clearable required
                    :disabled="$sharedDefinitionLocked" />
                <x-mary-input label="Brand Name" wire:model="drugForm.brandname" maxlength="30"
                    hint="{{ $brandSourceDmdcomb ? 'Required to distinguish this brand variant.' : 'Leave blank for the generic master item.' }}" />
                <div class="grid grid-cols-2 gap-3">
                    <x-mary-input label="Strength Number" type="number" min="0" step="0.01"
                        wire:model="drugForm.dmdnost" required :readonly="$sharedDefinitionLocked" />
                    <x-mary-choices-offline label="Strength Suffix" wire:model="drugForm.dmdnnostp" :options="[
                        ['id' => 'N', 'name' => 'Standard'],
                        ['id' => 'Y', 'name' => 'Percent (%)'],
                    ]" option-value="id" option-label="name" single required :disabled="$sharedDefinitionLocked" />
                </div>
                <x-mary-choices-offline label="Strength" wire:model="drugForm.strecode" :options="$strengths"
                    option-value="id" option-label="name" placeholder="Search strength" single searchable clearable required
                    :disabled="$sharedDefinitionLocked" />
                <x-mary-choices-offline label="Form" wire:model="drugForm.formcode" :options="$forms"
                    option-value="id" option-label="name" placeholder="Search form" single searchable clearable required
                    :disabled="$sharedDefinitionLocked" />
                <x-mary-choices-offline label="Route" wire:model="drugForm.rtecode" :options="$routes"
                    option-value="id" option-label="name" placeholder="Search route" single searchable clearable required
                    :disabled="$sharedDefinitionLocked" />
                <x-mary-choices-offline label="PNDF Flag" wire:model="drugForm.dmdpndf" :options="[
                    ['id' => 'Y', 'name' => 'PNDF'],
                    ['id' => 'N', 'name' => 'Non-PNDF'],
                ]" option-value="id" option-label="name" placeholder="Search PNDF flag" single searchable required
                    :disabled="$sharedDefinitionLocked" />
                <x-mary-choices-offline label="Status" wire:model="drugForm.dmdstat" :options="[
                    ['id' => 'A', 'name' => 'Active'],
                    ['id' => 'I', 'name' => 'Inactive'],
                ]" option-value="id" option-label="name" placeholder="Search status" single searchable required />
                <x-mary-input label="EDPMS ID" wire:model="drugForm.hprodid" maxlength="40" />
                <x-mary-input label="Master Barcode" wire:model="drugForm.barcode" maxlength="30" />
                <x-mary-input label="ATC Code" wire:model="drugForm.atcode" maxlength="5"
                    :readonly="$sharedDefinitionLocked" />
                <x-mary-input label="Claim Number" type="number" min="0" step="0.01"
                    wire:model="drugForm.dmdclaimno" />
                <x-mary-input label="Claim Unit of Measure" wire:model="drugForm.dmdclmuom" maxlength="5"
                    :readonly="$sharedDefinitionLocked" />
                <x-mary-choices-offline label="Essential Drug List" wire:model="drugForm.dmdedl" :options="[
                    ['id' => '', 'name' => 'Not specified'],
                    ['id' => 'Y', 'name' => 'Yes'],
                    ['id' => 'N', 'name' => 'No'],
                ]" option-value="id" option-label="name" single :disabled="$sharedDefinitionLocked" />
                <x-mary-input label="LBS Code" wire:model="drugForm.lbscode" maxlength="5"
                    :readonly="$sharedDefinitionLocked" />
                <x-mary-choices-offline label="Packaging" wire:model="drugForm.packcode" :options="$packages"
                    option-value="id" option-label="name" placeholder="Search packaging" single searchable clearable
                    :disabled="$sharedDefinitionLocked" />
                <x-mary-choices-offline label="Salt" wire:model="drugForm.saltcode" :options="$salts"
                    option-value="id" option-label="name" placeholder="Search salt" single searchable clearable
                    :disabled="$sharedDefinitionLocked" />
                <x-mary-input label="Pack Volume" type="number" min="0" step="0.01"
                    wire:model="drugForm.packvolno" :readonly="$sharedDefinitionLocked" />
                <x-mary-choices-offline label="Pack Volume Unit" wire:model="drugForm.packvolunitcode" :options="$units"
                    option-value="id" option-label="name" placeholder="Search unit" single searchable clearable
                    :disabled="$sharedDefinitionLocked" />
                <x-mary-input label="Beginning Balance" type="number" min="0" step="1"
                    wire:model="drugForm.begbal" />
                <x-mary-input label="Stock Balance" type="number" min="0" step="0.01"
                    wire:model="drugForm.stockbal" />
                <x-mary-textarea label="Technical Specification" wire:model="drugForm.techspec" rows="2"
                    class="sm:col-span-2" :readonly="$sharedDefinitionLocked" />
                <x-mary-textarea label="Standard Code / Description" wire:model="drugForm.dmdstco" rows="2"
                    class="sm:col-span-2" :readonly="$sharedDefinitionLocked" />
                <x-mary-textarea label="Remarks" wire:model="drugForm.dmdrem" rows="2" class="sm:col-span-2"
                    :readonly="$sharedDefinitionLocked" />
            </div>

            <x-mary-alert icon="o-information-circle" class="alert-info">
                Fund sources and their balances are maintained separately with the fund-source action after the
                master item is saved. Prices are created by the stock/delivery workflow.
            </x-mary-alert>

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('drugModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="saveDrug" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    <x-mary-modal wire:model="lookupModal" title="{{ $lookupOriginalCode ? 'Edit' : 'Add' }} {{ $lookupLabel }}" separator>
        <x-mary-form wire:submit="saveLookup">
            <x-mary-input label="Code" wire:model="lookupForm.code" maxlength="5" required
                :readonly="filled($lookupOriginalCode)" />
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
        box-class="w-[94vw] max-w-7xl">
        <x-mary-form wire:submit="saveGroup">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <x-mary-input label="Group Code" wire:model="groupForm.grpcode" maxlength="10" required
                    :readonly="filled($editingGrpcode)" />
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
                <div class="min-w-0">
                    <x-mary-choices-offline label="Sub Class 1" wire:model.live="groupForm.dms1key" :options="$subClass1Options"
                        option-value="id" option-label="name" placeholder="Search sub class 1" single searchable clearable />
                </div>
                <div class="min-w-0">
                    <x-mary-choices-offline label="Sub Class 2" wire:model.live="groupForm.dms2key" :options="$subClass2Options"
                        option-value="id" option-label="name" placeholder="Search sub class 2" single searchable clearable />
                </div>
                <div class="min-w-0">
                    <x-mary-choices-offline label="Sub Class 3" wire:model.live="groupForm.dms3key" :options="$subClass3Options"
                        option-value="id" option-label="name" placeholder="Search sub class 3" single searchable clearable />
                </div>
                <div class="min-w-0">
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

    <x-mary-modal wire:model="subModal" title="{{ $editingSubKey ? 'Edit' : 'Add' }} Drug Fund Source" separator>
        <x-mary-form wire:submit="saveSub">
            <div class="grid gap-4 md:grid-cols-2">
                <x-mary-choices-offline label="Sub Code / Fund Source" wire:model="subForm.dmhdrsub" :options="$chargeCodes"
                    option-value="id" option-label="name" placeholder="Search fund source" single searchable clearable required
                    :disabled="filled($editingSubKey)" />
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
