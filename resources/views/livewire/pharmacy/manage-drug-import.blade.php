<div class="w-full space-y-4 text-sm">
    <x-mary-header title="Import Drugs & Medicines" subtitle="Stage, map, review, and safely add records to the hospital drug master" separator progress-indicator>
        <x-slot:actions>
            <a href="{{ route('pharmacy.drug-library') }}" wire:navigate class="btn btn-ghost btn-sm">Back to library</a>
            <button type="button" wire:click="openImportHistory" class="btn btn-outline btn-sm">Past imports</button>
            <button type="button" wire:click="downloadTemplate" class="btn btn-outline btn-sm">Download template</button>
            @if ($batch)
                <button type="button" wire:click="exportResults" class="btn btn-outline btn-sm">Export results</button>
            @endif
        </x-slot:actions>
    </x-mary-header>

    @if (session('success')) <div class="alert alert-success py-2">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-error py-2">{{ session('error') }}</div> @endif

    <ol class="grid grid-cols-2 gap-2 sm:grid-cols-5">
        @foreach (['Upload', 'Validate', 'Map', 'Review', 'Results'] as $index => $step)
            @php
                $current = !$batch ? 0 : ($batch->status === 'completed' ? 4 : ($batch->issue_count > 0 ? 2 : 3));
            @endphp
            <li class="rounded-lg border px-3 py-2 {{ $index <= $current ? 'border-primary bg-primary/10 text-primary' : 'border-base-300 text-base-content/50' }}">
                <span class="mr-1 font-bold">{{ $index + 1 }}.</span>{{ $step }}
            </li>
        @endforeach
    </ol>

    <section class="rounded-xl border border-base-300 bg-base-100 p-4 shadow-sm">
        <form wire:submit="uploadWorkbook" class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <label class="form-control min-w-0 flex-1">
                <span class="label pb-1"><span class="label-text text-xs font-semibold">Excel workbook (.xlsx, maximum 10 MB)</span></span>
                <input type="file" wire:model="upload" accept=".xlsx" class="file-input file-input-bordered file-input-sm w-full" />
            </label>
            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="upload,uploadWorkbook">
                <span wire:loading.remove wire:target="uploadWorkbook">Stage workbook</span>
                <span wire:loading wire:target="uploadWorkbook" class="loading loading-spinner loading-sm"></span>
            </button>
        </form>
        @error('upload') <p class="mt-2 text-xs text-error">{{ $message }}</p> @enderror
        <p class="mt-2 text-xs text-base-content/60">Uploading only creates a private review batch. It does not change PowerBuilder or hospital drug records.</p>
    </section>

    @if ($batch)
        <section class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
            @foreach ([
                ['Total', $batch->total_count, 'text-base-content'], ['Ready', $batch->ready_count, 'text-success'],
                ['Needs mapping', $batch->issue_count, 'text-warning'], ['Duplicates', $batch->duplicate_count, 'text-info'],
                ['Excluded', $batch->excluded_count, 'text-base-content/60'], ['Imported', $batch->imported_count, 'text-primary'],
            ] as [$label, $value, $color])
                <div class="stat rounded-lg border border-base-300 bg-base-100 px-4 py-3">
                    <div class="stat-title text-xs">{{ $label }}</div>
                    <div class="stat-value text-xl {{ $color }}">{{ $value }}</div>
                </div>
            @endforeach
        </section>

        <section x-data="{ open: false }" class="overflow-hidden rounded-xl border border-base-300 bg-base-100 shadow-sm">
            <div class="flex items-center gap-2 p-3">
                <button type="button" x-on:click="open = !open" class="flex flex-1 items-center justify-between text-left" :aria-expanded="open">
                    <span class="font-semibold">Review filters</span>
                    <span class="text-xs" x-text="open ? 'Hide filters' : 'Show filters'"></span>
                </button>
                <button type="button" wire:click="clearFilters" class="btn btn-ghost btn-xs">Clear</button>
            </div>
            <div x-cloak x-show="open" class="grid gap-3 border-t border-base-300 bg-base-200/30 p-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="form-control"><span class="label-text text-xs">Status</span><select wire:model.live="statusFilter" class="select select-bordered select-sm"><option value="">All</option>@foreach (['ready'=>'Ready','needs_mapping'=>'Needs mapping','duplicate'=>'Duplicate','excluded'=>'Excluded','imported'=>'Imported'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="form-control"><span class="label-text text-xs">Sheet</span><select wire:model.live="sheetFilter" class="select select-bordered select-sm"><option value="">All</option>@foreach ($sheets as $sheet)<option value="{{ $sheet }}">{{ $sheet }}</option>@endforeach</select></label>
                <label class="form-control"><span class="label-text text-xs">Issue</span><select wire:model.live="issueFilter" class="select select-bordered select-sm"><option value="">All</option>@foreach ($issues as $issue)<option value="{{ $issue }}">{{ str($issue)->headline() }}</option>@endforeach</select></label>
                <label class="form-control"><span class="label-text text-xs">Generic</span><input wire:model.live.debounce.300ms="genericFilter" class="input input-bordered input-sm" /></label>
                <label class="form-control"><span class="label-text text-xs">Strength</span><input wire:model.live.debounce.300ms="strengthFilter" class="input input-bordered input-sm" /></label>
                <label class="form-control"><span class="label-text text-xs">Form</span><input wire:model.live.debounce.300ms="formFilter" class="input input-bordered input-sm" /></label>
                <label class="form-control"><span class="label-text text-xs">Route</span><input wire:model.live.debounce.300ms="routeFilter" class="input input-bordered input-sm" /></label>
                <label class="form-control"><span class="label-text text-xs">Rows</span><select wire:model.live="perPage" class="select select-bordered select-sm"><option>25</option><option>50</option><option>100</option></select></label>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="revalidateBatch" wire:loading.attr="disabled" class="btn btn-outline btn-sm">Revalidate pending</button>
                <button type="button" wire:click="excludeUnresolved" wire:confirm="Exclude every unresolved row from this import?" class="btn btn-ghost btn-sm">Exclude unresolved</button>
            </div>
            <button type="button" wire:click="commitReady" wire:confirm="Import {{ $batch->ready_count }} ready records into the hospital drug master? This cannot be automatically rolled back." wire:loading.attr="disabled" class="btn btn-primary btn-sm" @disabled($batch->ready_count === 0)>
                Import {{ $batch->ready_count }} ready records
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-base-300 bg-base-100">
            <table class="table table-sm table-zebra w-full">
                <thead class="bg-base-200"><tr><th>Source</th><th>Generic</th><th>Strength</th><th>Form</th><th>Route</th><th>Status / issue</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="drug-import-row-{{ $row->id }}">
                            <td class="whitespace-nowrap text-xs">{{ $row->source_sheet }} #{{ $row->source_row }}</td>
                            <td><div class="max-w-64 truncate font-medium" title="{{ $row->generic_name }}">{{ $row->generic_name ?: '—' }}</div><div class="text-[11px] text-base-content/50">{{ $row->grpcode ?: 'No group' }}</div></td>
                            <td><div>{{ $row->strength_text ?: '—' }}</div><div class="text-[11px] text-base-content/50">{{ $row->dmdnost }} {{ $row->strecode }}</div></td>
                            <td>{{ $row->form_text ?: '—' }}<div class="text-[11px] text-base-content/50">{{ $row->formcode }}</div></td>
                            <td>{{ $row->route_text ?: '—' }}<div class="text-[11px] text-base-content/50">{{ $row->rtecode }}</div></td>
                            <td>
                                <span class="badge badge-sm {{ match($row->row_status){'ready'=>'badge-success','needs_mapping'=>'badge-warning','duplicate'=>'badge-info','imported'=>'badge-primary',default=>'badge-ghost'} }}">{{ str($row->row_status)->headline() }}</span>
                                @if ($row->issues_json)<div class="mt-1 max-w-72 text-[11px] text-warning">{{ collect($row->issues_json)->pluck('message')->implode(' ') }}</div>@endif
                                @if ($row->existing_dmdcomb)<div class="text-[11px] text-base-content/50">Existing: {{ $row->existing_dmdcomb }}/{{ $row->existing_dmdctr }}</div>@endif
                            </td>
                            <td><div class="flex justify-end gap-1">
                                @if (!in_array($row->row_status, ['imported','duplicate'], true))<button type="button" wire:click="editMapping({{ $row->id }})" class="btn btn-outline btn-xs">Map</button>@endif
                                @if ($row->row_status !== 'imported')<button type="button" wire:click="toggleExcluded({{ $row->id }})" class="btn btn-ghost btn-xs">{{ $row->row_status === 'excluded' ? 'Restore' : 'Exclude' }}</button>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-base-content/60">No rows match the current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $rows->links() }}</div>
    @endif

    @if ($mappingModal)
        <div wire:key="drug-import-mapping-modal-{{ $mappingRowId }}"
            class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
            role="dialog" aria-modal="true" aria-labelledby="drug-import-mapping-title">
            <section class="relative max-h-[calc(100vh-2rem)] w-full max-w-5xl overflow-y-auto rounded-xl bg-base-100 p-6 shadow-2xl lg:p-7">
                <button type="button" wire:click="$set('mappingModal', false)"
                    class="btn btn-ghost btn-circle btn-sm absolute right-3 top-3" aria-label="Close mapping dialog">
                    <span aria-hidden="true">✕</span>
                </button>

                <h2 id="drug-import-mapping-title" class="pr-10 text-lg font-bold">Review row mapping</h2>

                <div class="my-4 rounded-lg bg-base-200 p-3 text-xs">
                    <div class="font-semibold">{{ $mappingContext['source'] ?? '' }} — {{ $mappingContext['generic'] ?? '' }}</div>
                    <div class="mt-1 text-base-content/60">{{ $mappingContext['strength'] ?? 'No strength' }} · {{ $mappingContext['form'] ?? 'No form' }} · {{ $mappingContext['route'] ?? 'No route' }}</div>
                </div>

                @if ($autoDetectedFields)
                    <div class="alert alert-success mb-4 py-2 text-xs">
                        <span>Automatically detected from the workbook: {{ implode(', ', $autoDetectedFields) }}. Please verify before saving.</span>
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <label class="form-control xl:col-span-2">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">PNDF Group <span class="text-error">*</span></span></span>
                        @if ($groupSuggestions)
                            <div class="mb-2 rounded-lg border border-primary/20 bg-primary/5 p-2">
                                <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-primary">Suggested matches</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($groupSuggestions as $index => $suggestion)
                                        <button type="button" wire:click="selectSuggestedGroup({{ $index }})"
                                            class="btn btn-xs h-auto min-h-7 max-w-full justify-start whitespace-normal text-left {{ ($mappingForm['grpcode'] ?? '') === $suggestion['id'] ? 'btn-primary' : 'btn-outline' }}"
                                            title="Similarity: {{ $suggestion['score'] }}%">
                                            {{ $suggestion['name'] }}
                                            <span class="opacity-60">{{ round($suggestion['score']) }}%</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if ($newGroupRecommendation && (!$groupSuggestions || ($groupSuggestions[0]['score'] ?? 0) < 70))
                            <div class="mb-2 rounded-lg border border-warning/30 bg-warning/10 p-3 text-xs">
                                <div class="font-semibold">Recommended new PNDF group</div>
                                <div class="mt-1">Generic: {{ $newGroupRecommendation['generic']['name'] ?? $newGroupRecommendation['generic_name'] }}</div>
                                <div class="text-base-content/65">Classification: {{ $newGroupRecommendation['classification'] ?: 'No reliable classification match' }}</div>
                                @if ($newGroupRecommendation['classification_source'])
                                    <div class="text-base-content/55">Based on {{ $newGroupRecommendation['classification_source'] }}.</div>
                                @endif
                                <div class="mt-2">
                                    @if ($newGroupRecommendation['can_create_group'])
                                        <button type="button" wire:click="openRecommendedGroup" class="btn btn-warning btn-xs">Review prefilled group</button>
                                    @elseif (!$newGroupRecommendation['generic'])
                                        <a target="_blank" class="btn btn-warning btn-xs"
                                            href="{{ route('pharmacy.drug-library', ['tab' => 'generics', 'create' => 'generic', 'generic' => $newGroupRecommendation['generic_name']]) }}">Add prefilled generic first</a>
                                    @else
                                        <span class="text-warning">The workbook classification could not be matched reliably. Review the classification tables first.</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <select wire:model="mappingForm.grpcode" class="select select-bordered select-sm w-full" required>
                            <option value="">Select a PNDF group</option>
                            @foreach ($groups as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @error('mappingForm.grpcode') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Dosage number <span class="text-error">*</span></span></span>
                        <input wire:model="mappingForm.dmdnost" type="number" min="0" step="0.01"
                            class="input input-bordered input-sm w-full" required />
                        @error('mappingForm.dmdnost') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Strength <span class="text-error">*</span></span></span>
                        <select wire:model="mappingForm.strecode" class="select select-bordered select-sm w-full" required>
                            <option value="">Select a strength</option>
                            @foreach ($strengths as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @error('mappingForm.strecode') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Dosage form <span class="text-error">*</span></span></span>
                        <select wire:model="mappingForm.formcode" class="select select-bordered select-sm w-full" required>
                            <option value="">Select a dosage form</option>
                            @foreach ($forms as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @error('mappingForm.formcode') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Route <span class="text-error">*</span></span></span>
                        <select wire:model="mappingForm.rtecode" class="select select-bordered select-sm w-full" required>
                            <option value="">Select a route</option>
                            @foreach ($routes as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @error('mappingForm.rtecode') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">PNDF</span></span>
                        <select wire:model="mappingForm.pndf" class="select select-bordered select-sm w-full">
                            <option value="Y">PNDF</option>
                            <option value="N">Non-PNDF</option>
                        </select>
                    </label>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">RX/OTC</span></span>
                        <select wire:model="mappingForm.rxot" class="select select-bordered select-sm w-full">
                            <option value="RXX">Prescription</option>
                            <option value="OTC">Over the counter</option>
                        </select>
                    </label>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Status</span></span>
                        <select wire:model="mappingForm.record_status" class="select select-bordered select-sm w-full">
                            <option value="A">Active</option>
                            <option value="I">Inactive</option>
                        </select>
                    </label>
                </div>

                <p class="mt-3 text-xs text-base-content/60">
                    Missing a reference? Open the
                    <a href="{{ route('pharmacy.drug-library', ['tab' => 'groups']) }}" target="_blank" class="link link-primary">PNDF Groups</a>,
                    <a href="{{ route('pharmacy.drug-library', ['tab' => 'strengths']) }}" target="_blank" class="link link-primary">Strengths</a>,
                    <a href="{{ route('pharmacy.drug-library', ['tab' => 'forms']) }}" target="_blank" class="link link-primary">Forms</a>, or
                    <a href="{{ route('pharmacy.drug-library', ['tab' => 'routes']) }}" target="_blank" class="link link-primary">Routes</a> tab, then return and revalidate.
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="$set('mappingModal', false)" class="btn btn-ghost btn-sm">Cancel</button>
                    <button type="button" wire:click="saveMapping" wire:loading.attr="disabled" wire:target="saveMapping"
                        class="btn btn-primary btn-sm">
                        <span wire:loading.remove wire:target="saveMapping">Save and revalidate</span>
                        <span wire:loading wire:target="saveMapping" class="loading loading-spinner loading-xs"></span>
                    </button>
                </div>
            </section>
        </div>
    @endif

    @if ($groupDrawer)
        <div wire:key="recommended-group-drawer" class="fixed inset-0 z-[1100]" role="dialog" aria-modal="true" aria-labelledby="recommended-group-title">
            <button type="button" wire:click="$set('groupDrawer', false)" class="absolute inset-0 bg-black/35" aria-label="Close PNDF group drawer"></button>
            <aside class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col border-l border-base-300 bg-base-100 shadow-2xl">
                <header class="flex items-start justify-between gap-3 border-b border-base-300 px-5 py-4">
                    <div>
                        <h2 id="recommended-group-title" class="text-base font-bold">Review new PNDF group</h2>
                        <p class="mt-1 text-xs text-base-content/60">Review the PowerBuilder-compatible reference values before creating this shared group.</p>
                    </div>
                    <button type="button" wire:click="$set('groupDrawer', false)" class="btn btn-ghost btn-circle btn-sm" aria-label="Close drawer">✕</button>
                </header>

                <div class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-xs">
                        <div class="font-semibold">Suggested from {{ $newGroupRecommendation['classification_source'] ?? 'the workbook' }}</div>
                        <div class="mt-1 text-base-content/65">{{ $newGroupRecommendation['classification'] ?? '' }}</div>
                    </div>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Group code <span class="text-error">*</span></span></span>
                        <input wire:model="newGroupForm.grpcode" maxlength="10" class="input input-bordered input-sm w-full" required />
                        @error('newGroupForm.grpcode') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Generic</span></span>
                        <input value="{{ $newGroupForm['generic_name'] ?? '' }}" class="input input-bordered input-sm w-full bg-base-200" readonly />
                    </label>

                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Major class <span class="text-error">*</span></span></span>
                        <select wire:model.live="newGroupForm.dmcode" class="select select-bordered select-sm w-full" required>
                            <option value="">Select major class</option>
                            @foreach ($majorClasses as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @error('newGroupForm.dmcode') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    @foreach ([
                        ['field' => 'dms1key', 'label' => 'Sub class 1', 'options' => $subClass1],
                        ['field' => 'dms2key', 'label' => 'Sub class 2', 'options' => $subClass2],
                        ['field' => 'dms3key', 'label' => 'Sub class 3', 'options' => $subClass3],
                        ['field' => 'dms4key', 'label' => 'Sub class 4', 'options' => $subClass4],
                    ] as $level)
                        <label class="form-control">
                            <span class="label pb-1"><span class="label-text text-xs font-semibold">{{ $level['label'] }}</span></span>
                            <select wire:model.live="newGroupForm.{{ $level['field'] }}" class="select select-bordered select-sm w-full" @disabled($level['options']->isEmpty())>
                                <option value="">{{ $level['options']->isEmpty() ? 'No available subclass' : 'None' }}</option>
                                @foreach ($level['options'] as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                @endforeach
                            </select>
                            @error('newGroupForm.'.$level['field']) <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                </div>

                <footer class="flex justify-end gap-2 border-t border-base-300 p-4">
                    <button type="button" wire:click="$set('groupDrawer', false)" class="btn btn-ghost btn-sm">Cancel</button>
                    <button type="button" wire:click="saveRecommendedGroup" wire:loading.attr="disabled" wire:target="saveRecommendedGroup" class="btn btn-primary btn-sm">
                        <span wire:loading.remove wire:target="saveRecommendedGroup">Create and use group</span>
                        <span wire:loading wire:target="saveRecommendedGroup" class="loading loading-spinner loading-xs"></span>
                    </button>
                </footer>
            </aside>
        </div>
    @endif

    @if ($historyDrawer)
        <div wire:key="drug-import-history-drawer" class="fixed inset-0 z-[1200]" role="dialog" aria-modal="true" aria-labelledby="import-history-title">
            <button type="button" wire:click="$set('historyDrawer', false)" class="absolute inset-0 bg-black/35" aria-label="Close past imports"></button>
            <aside class="absolute inset-y-0 right-0 flex w-full max-w-3xl flex-col border-l border-base-300 bg-base-100 shadow-2xl">
                <header class="flex items-start justify-between gap-3 border-b border-base-300 px-5 py-4">
                    <div>
                        <h2 id="import-history-title" class="text-base font-bold">Past drug imports</h2>
                        <p class="mt-1 text-xs text-base-content/60">Reopen retained batches to review mappings, results, and audit details.</p>
                    </div>
                    <button type="button" wire:click="$set('historyDrawer', false)" class="btn btn-ghost btn-circle btn-sm" aria-label="Close drawer">✕</button>
                </header>

                <div class="grid gap-3 border-b border-base-300 bg-base-200/30 p-4 sm:grid-cols-[1fr_12rem]">
                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Filename</span></span>
                        <input wire:model.live.debounce.300ms="historySearch" placeholder="Search past imports" class="input input-bordered input-sm w-full" />
                    </label>
                    <label class="form-control">
                        <span class="label pb-1"><span class="label-text text-xs font-semibold">Status</span></span>
                        <select wire:model.live="historyStatus" class="select select-bordered select-sm w-full">
                            <option value="">All statuses</option>
                            @foreach ($historyStatuses as $status)
                                <option value="{{ $status }}">{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="flex-1 overflow-y-auto p-4">
                    <div class="space-y-3">
                        @forelse ($history as $pastBatch)
                            <article wire:key="past-import-{{ $pastBatch->id }}" class="rounded-xl border p-4 {{ $batchId === $pastBatch->id ? 'border-primary bg-primary/5' : 'border-base-300' }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="max-w-md truncate text-sm font-semibold" title="{{ $pastBatch->source_filename }}">{{ $pastBatch->source_filename }}</h3>
                                            <span class="badge badge-sm {{ $pastBatch->status === 'completed' ? 'badge-success' : ($pastBatch->status === 'review' ? 'badge-warning' : 'badge-ghost') }}">{{ str($pastBatch->status)->headline() }}</span>
                                            @if ($batchId === $pastBatch->id)<span class="badge badge-primary badge-outline badge-sm">Open</span>@endif
                                        </div>
                                        <div class="mt-1 text-xs text-base-content/55">
                                            {{ optional($pastBatch->created_at)->format('M j, Y g:i A') }}
                                            @if ($pastBatch->uploaded_by) · Uploaded by {{ $pastBatch->uploaded_by }} @endif
                                            @if ($pastBatch->approved_by) · Approved by {{ $pastBatch->approved_by }} @endif
                                        </div>
                                    </div>
                                    <button type="button" wire:click="openImportBatch('{{ $pastBatch->id }}')" class="btn btn-outline btn-xs">Open batch</button>
                                </div>

                                <dl class="mt-3 grid grid-cols-3 gap-2 text-center sm:grid-cols-6">
                                    @foreach ([
                                        ['Total', $pastBatch->total_count], ['Ready', $pastBatch->ready_count],
                                        ['Issues', $pastBatch->issue_count], ['Duplicates', $pastBatch->duplicate_count],
                                        ['Excluded', $pastBatch->excluded_count], ['Imported', $pastBatch->imported_count],
                                    ] as [$label, $count])
                                        <div class="rounded-lg bg-base-200 px-2 py-1.5">
                                            <dt class="text-[10px] text-base-content/55">{{ $label }}</dt>
                                            <dd class="text-sm font-semibold">{{ number_format($count) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </article>
                        @empty
                            <div class="py-12 text-center text-sm text-base-content/55">No past imports match these filters.</div>
                        @endforelse
                    </div>
                </div>

                @if ($history->hasPages())
                    <footer class="border-t border-base-300 p-4">{{ $history->links() }}</footer>
                @endif
            </aside>
        </div>
    @endif
</div>
