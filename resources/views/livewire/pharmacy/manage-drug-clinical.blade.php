<div class="space-y-5 p-3 sm:p-5">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="text-xl font-semibold">Drug clinical information</h1><p class="text-sm text-base-content/70">Pharmacist-reviewed reference information for the library and dispensing.</p></div>
        <a href="{{ route('pharmacy.drug-library') }}" wire:navigate class="btn btn-sm btn-outline">Drug library</a>
    </header>
    @if (!$available)
        <div role="status" class="alert alert-warning">Clinical information is not installed. Apply the clinical migration and permission seeder before use.</div>
    @else
        @if ($notice)<p role="status" class="alert alert-info">{{ $notice }}</p>@endif
        @if ($errors->any())<div role="alert" class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="border border-base-300 rounded-lg p-4 space-y-3" aria-label="Drug reference">
            <h2 class="font-semibold">Look up a drug</h2>
            <label class="block text-sm">Find products by name or code<input wire:model.live.debounce.350ms="productSearch" class="input input-bordered w-full mt-1" placeholder="Search drug products"></label>
            <label class="block text-sm">View clinical details<select wire:model.live="product" class="select select-bordered w-full mt-1"><option value="">Choose a product (up to 60 search results)</option>@foreach ($products as $p)<option value="{{ $p->dmdcomb }}|{{ $p->dmdctr }}">{{ $p->drug_concat ?: $p->dmdcomb }} [{{ $p->dmdcomb }}|{{ $p->dmdctr }}]</option>@endforeach</select></label>
            @if ($product)
                @php([$detailComb, $detailCtr] = array_pad(explode('|', $product, 2), 2, ''))
                <livewire:pharmacy.drug-clinical-panel :comb="$detailComb" :ctr="$detailCtr" :key="'reference-'.$product" />
            @endif
        </section>
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <section class="space-y-4 min-w-0">
                <h2 class="font-semibold">Maintain clinical records</h2>
                <label class="block text-sm">Record type<select wire:model.live="kind" class="select select-bordered w-full mt-1"><option value="side_effect">Side effects</option><option value="alternative">Therapeutic alternatives</option><option value="interaction">Drug–drug interactions</option><option value="mapping">Verified ingredient mappings</option></select></label>
                @can('edit-drug-clinical')
                    <form wire:submit="save" class="space-y-3 border border-base-300 rounded-lg p-4">
                        <h3 class="font-semibold">{{ $editingId ? 'Create a revision' : 'Create a draft' }}</h3>
                        @foreach (\App\Livewire\Pharmacy\ManageDrugClinical::fields($kind) as $field => $label)
                            <label class="block text-sm" wire:key="field-{{ $kind }}-{{ $field }}">{{ $label }}
                                @if (in_array($field, ['product', 'target_product']))
                                    <select wire:model="form.{{ $field }}" class="select select-bordered w-full mt-1"><option value="">Choose product</option>@foreach ($products as $p)<option value="{{ $p->dmdcomb }}|{{ $p->dmdctr }}">{{ $p->drug_concat ?: $p->dmdcomb }} [{{ $p->dmdcomb }}|{{ $p->dmdctr }}]</option>@endforeach</select>
                                @elseif (in_array($field, ['ingredient_id', 'ingredient_a', 'ingredient_b', 'ingredients']))
                                    <select wire:model="form.{{ $field }}" class="select select-bordered w-full mt-1" @if ($field === 'ingredients') multiple size="5" @endif><option value="">Choose ingredient</option>@foreach ($ingredients as $ingredient)<option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>@endforeach</select>
                                    @if ($field === 'ingredients')<span class="block text-xs text-base-content/70">Select every active ingredient, including all ingredients in combination medicines.</span>@endif
                                @elseif ($field === 'severity')
                                    <select wire:model="form.severity" class="select select-bordered w-full mt-1"><option value="">Choose severity</option><option value="minor">Minor</option><option value="moderate">Moderate</option><option value="major">Major</option><option value="contraindicated">Contraindicated</option></select>
                                @elseif ($field === 'serious')
                                    <input type="checkbox" wire:model="form.serious" class="checkbox checkbox-sm ml-2 align-middle">
                                @elseif (in_array($field, ['route', 'route_a', 'route_b', 'form', 'frequency']))
                                    <input wire:model="form.{{ $field }}" class="input input-bordered w-full mt-1" placeholder="{{ $field === 'frequency' ? 'Frequency from source' : 'Exact master code; blank applies to all' }}">
                                @else
                                    <textarea wire:model="form.{{ $field }}" class="textarea textarea-bordered w-full mt-1" rows="3"></textarea>
                                @endif
                            </label>
                        @endforeach
                        <label class="block text-sm">Source reference<textarea wire:model="source" required class="textarea textarea-bordered w-full mt-1" placeholder="Reference title, section/version and URL where available"></textarea></label>
                        <label class="block text-sm">Clinical review date<input type="date" wire:model="reviewedOn" required class="input input-bordered w-full mt-1"></label>
                        <div class="flex flex-wrap gap-2"><button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">Save draft</button><button type="button" wire:click="resetEditor" class="btn btn-ghost btn-sm">Clear editor</button></div>
                    </form>
                    <form wire:submit="addIngredient" class="space-y-2"><label class="block text-sm">Add a clinical ingredient<input wire:model="ingredientName" required maxlength="180" class="input input-bordered w-full mt-1" placeholder="Canonical ingredient name"></label><button class="btn btn-outline btn-sm" wire:loading.attr="disabled">Add ingredient</button></form>
                @endcan
            </section>
            <section class="min-w-0 space-y-3" aria-label="Record revisions">
                <h2 class="font-semibold">Records and revision history</h2>
                <div class="flex flex-wrap gap-2"><label class="flex-1 text-sm">Search records<input wire:model.live.debounce.350ms="search" class="input input-bordered w-full mt-1"></label><label class="text-sm">Status<select wire:model.live="status" class="select select-bordered block mt-1"><option value="">All revisions</option><option value="draft">Draft</option><option value="published">Published</option><option value="retired">Retired</option></select></label></div>
                @forelse ($records as $record)
                    @php($data = json_decode($record->payload, true))
                    <article class="border border-base-300 rounded-lg p-4 space-y-2" wire:key="record-{{ $record->id }}">
                        <div class="flex flex-wrap justify-between gap-2"><strong>#{{ $record->id }} · Revision {{ $record->revision }}</strong><span class="badge badge-outline">{{ $record->status }}</span></div>
                        <dl class="text-sm space-y-1">@foreach ($data as $name => $value)<div><dt class="inline font-medium">{{ ucfirst(str_replace('_', ' ', $name)) }}:</dt> <dd class="inline whitespace-pre-line break-words">{{ is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) }}</dd></div>@endforeach</dl>
                        <p class="text-xs break-words">Source: {{ $record->source }} · Reviewed {{ substr($record->reviewed_on, 0, 10) }}</p>
                        <p class="text-xs text-base-content/70">Author {{ $record->author }} · Approver {{ $record->approved_by ?: 'Pending' }} · {{ $record->updated_at }}</p>
                        <details class="text-xs"><summary class="cursor-pointer">Audit trail</summary><ul class="mt-2 space-y-1">@foreach ($audits->get($record->id, collect()) as $audit)<li>{{ str_replace('_', ' ', $audit->action) }} · {{ $audit->actor }} · {{ $audit->created_at }}</li>@endforeach</ul></details>
                        <div class="flex flex-wrap gap-2">@can('edit-drug-clinical')<button wire:click="edit({{ $record->id }})" class="btn btn-outline btn-xs">Revise</button>@endcan @can('publish-drug-clinical') @if ($record->status === 'draft')<button wire:click="transition({{ $record->id }}, 'publish')" wire:confirm="Publish this clinically reviewed revision?" class="btn btn-primary btn-xs">Publish</button>@endif @if ($record->status !== 'retired')<button wire:click="transition({{ $record->id }}, 'retire')" wire:confirm="Retire this revision? It will no longer appear in dispensing." class="btn btn-outline btn-xs">Retire</button>@endif @endcan</div>
                    </article>
                @empty
                    <p class="p-4 border border-dashed border-base-300 rounded-lg text-sm">No records match. Add sourced clinical information and publish it after review.</p>
                @endforelse
                {{ $records->links() }}
            </section>
        </div>
    @endif
</div>
