<x-mary-modal wire:model="manualEquivalenceModal" title="Create equivalent group manually" separator box-class="w-[96vw] max-w-5xl">
    @if ($manualEquivalenceModal)
        @error('manualEquivalence')<div class="alert alert-error mb-4 text-sm" role="alert">{{ $message }}</div>@enderror
        <p class="mb-4 text-sm text-base-content/70">Select 2 to 50 active, ungrouped drugs. The reference drug becomes the canonical entry. Review the selection before saving.</p>
        <label for="manual-equivalence-search" class="mb-2 block text-sm font-medium">Search drugs</label>
        <input id="manual-equivalence-search" type="search" class="input input-bordered w-full" wire:model.live.debounce.300ms="manualEquivalenceSearch" placeholder="Enter at least 2 characters: description, brand or code" maxlength="200" />
        <div class="mt-3 max-h-48 overflow-auto rounded-lg border border-base-300" aria-label="Drug search results">
            <table class="table table-sm">
                <thead><tr><th>Drug</th><th>Code</th><th><span class="sr-only">Select</span></th></tr></thead>
                <tbody>
                    @forelse ($manualEquivalenceCandidates as $candidate)
                        @php($candidateKey = $candidate->dmdcomb.'|'.$candidate->dmdctr)
                        <tr wire:key="manual-candidate-{{ $candidateKey }}">
                            <td>{{ $candidate->drug_concat }}</td>
                            <td class="text-xs">{{ $candidate->dmdcomb }}-{{ $candidate->dmdctr }}</td>
                            <td><button type="button" class="btn btn-outline btn-xs" wire:click="selectManualEquivalence('{{ $candidateKey }}')" @disabled(in_array($candidateKey, $manualEquivalenceKeys, true) || count($manualEquivalenceKeys) >= 50)>{{ in_array($candidateKey, $manualEquivalenceKeys, true) ? 'Selected' : 'Select' }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-4 text-base-content/70">{{ mb_strlen(trim($manualEquivalenceSearch)) < 2 ? 'Search to find drugs to include.' : 'No active, ungrouped drugs found. Try another search.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h3 class="mb-2 mt-5 font-semibold">Selected drugs ({{ count($manualEquivalenceKeys) }})</h3>
        <div class="max-h-60 overflow-auto rounded-lg border border-base-300">
            <table class="table table-sm">
                <thead><tr><th>Reference</th><th>Drug</th><th>Code</th><th><span class="sr-only">Remove</span></th></tr></thead>
                <tbody>
                    @forelse ($manualEquivalenceDrugs as $selectedDrug)
                        @php($selectedKey = $selectedDrug->dmdcomb.'|'.$selectedDrug->dmdctr)
                        <tr wire:key="manual-selected-{{ $selectedKey }}">
                            <td><input type="radio" name="manual-reference" class="radio radio-primary radio-sm" wire:model.live="manualEquivalenceCanonical" value="{{ $selectedKey }}" aria-label="Use {{ $selectedDrug->drug_concat }} as reference" /></td>
                            <td>{{ $selectedDrug->drug_concat }}</td>
                            <td class="text-xs">{{ $selectedDrug->dmdcomb }}-{{ $selectedDrug->dmdctr }}</td>
                            <td><button type="button" class="btn btn-ghost btn-xs" wire:click="removeManualEquivalence('{{ $selectedKey }}')">Remove</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4 text-base-content/70">No drugs selected yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($manualEquivalenceReview)
            @if ($manualEquivalenceReview['has_warnings'])
                <div class="alert alert-warning mt-5 block text-sm" role="alert">
                    <h3 class="font-semibold">These records do not have matching clinical fields</h3>
                    <p class="mt-2">They may represent different drugs or incomplete library records. Creating this active group will allow these members to appear as alternatives in dispensing searches. Confirmation does not establish clinical interchangeability.</p>
                </div>
                <div class="mt-3 max-h-64 overflow-auto rounded-lg border border-base-300">
                    <table class="table table-sm">
                        <thead><tr><th>Member</th><th>Field</th><th>Reference value</th><th>Member value</th></tr></thead>
                        <tbody>
                            @foreach ($manualEquivalenceReview['members'] as $reviewMember)
                                @foreach ($reviewMember['differences'] as $difference)
                                    <tr><td>{{ $reviewMember['description'] }}</td><td>{{ $difference['field'] }}</td><td>{{ $difference['canonical'] }}</td><td>{{ $difference['candidate'] }}</td></tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <label for="manual-equivalence-reason" class="mb-2 mt-4 block text-sm font-medium">Reason for grouping despite differences</label>
                <textarea id="manual-equivalence-reason" class="textarea textarea-bordered w-full" wire:model="manualEquivalenceReason" minlength="10" maxlength="1000" rows="2" placeholder="Explain why these records should be grouped (10–1000 characters)"></textarea>
                <label class="mt-3 flex items-start gap-3 text-sm">
                    <input type="checkbox" class="checkbox checkbox-warning checkbox-sm shrink-0" wire:model.live="manualEquivalenceConfirmed" />
                    <span>I have reviewed the differences and confirm grouping these records for dispensing. My confirmation and reason will be recorded.</span>
                </label>
            @else
                <p class="alert alert-success mt-5 text-sm">The selected records have matching clinical fields. Ready to create the group.</p>
            @endif
        @endif
        <div class="mt-5 flex flex-wrap justify-end gap-2">
            <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('manualEquivalenceModal', false)">Cancel</button>
            <button type="button" class="btn btn-outline btn-sm" wire:click="reviewManualEquivalence" wire:loading.attr="disabled" @disabled(count($manualEquivalenceKeys) < 2)>Review selection</button>
            @if ($manualEquivalenceReview)
                <button type="button" class="btn btn-sm {{ $manualEquivalenceReview['has_warnings'] ? 'btn-warning' : 'btn-primary' }}" wire:click="saveManualEquivalence" wire:loading.attr="disabled" @disabled($manualEquivalenceReview['has_warnings'] && ! $manualEquivalenceConfirmed)>{{ $manualEquivalenceReview['has_warnings'] ? 'Confirm and create group' : 'Create group' }}</button>
            @endif
        </div>
    @endif
</x-mary-modal>
