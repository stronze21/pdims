<section class="space-y-3 p-3 border border-base-300 rounded-lg text-sm" aria-label="Clinical drug reference">
    @if ($check)
        <details open>
            <summary class="font-semibold cursor-pointer">Drug–drug interactions <span class="badge badge-outline badge-sm">Advisory</span></summary>
            <div class="space-y-2 mt-3" aria-live="polite">
                <p class="text-xs text-base-content/70">Published local rules only. Includes this encounter and linked original-encounter orders; excludes other encounters, home medicines, food, allergies, and dose checks.</p>
                @if (!$check['available'])
                    <p role="status" class="alert alert-warning">Interaction checking is unavailable. Dispensing remains available; review interactions using your clinical reference.</p>
                @else
                    @if ($check['uncertain'])<p class="text-xs">Encounter orders with unknown therapy duration are included for review; concurrent use is not confirmed.</p>@endif
                    @foreach ($check['alerts'] as $alert)
                        <article class="border rounded-lg {{ in_array($alert['data']['severity'], ['major', 'contraindicated']) ? 'border-error' : 'border-warning' }} p-3 space-y-1">
                            <p class="font-semibold">{{ ucfirst($alert['data']['severity']) }} · {{ $alert['left']['label'] }} + {{ $alert['right']['label'] }}</p>
                            <p class="whitespace-pre-line">{{ $alert['data']['effect'] }}</p><p class="whitespace-pre-line">{{ $alert['data']['advice'] }}</p>
                            <p class="text-xs break-words">Source: {{ $alert['source'] }} · Reviewed {{ substr($alert['reviewed_on'], 0, 10) }}</p>
                        </article>
                    @endforeach
                    @if (!$check['alerts'])<p>No interactions found in published local rules.</p>@endif
                    @if ($check['unmapped'])<p class="text-warning">Not checked because verified ingredient mappings are missing: {{ implode('; ', $check['unmapped']) }}.</p>@endif
                @endif
                <a href="{{ route('pharmacy.drug-clinical') }}" target="_blank" rel="noopener" class="link link-primary">Look up another drug or maintain clinical information</a>
            </div>
        </details>
    @endif
    @if ($failed)<p role="status" class="alert alert-warning">Clinical reference information is unavailable. No dispensing restriction has been applied.</p>@endif
    @if ($details)
        <h3 class="font-semibold break-words">{{ $details['drug']->drug_concat ?: $comb.'|'.$ctr }}</h3>
        @if (!$details['mapping'])<p class="text-warning">No published verified ingredient mapping. Ingredient-based clinical information may be incomplete.</p>@endif
        <details open><summary class="font-semibold cursor-pointer">Side effects</summary><div class="mt-2 space-y-3">
            @forelse ($details['effects'] as $effect)
                <article class="border-l-2 {{ $effect->data['serious'] ? 'border-error' : 'border-base-300' }} pl-3">
                    <p class="font-medium">{{ $effect->data['serious'] ? 'Serious' : 'Other reported effect' }} · {{ $effect->data['effect'] }}</p>
                    <p>{{ ($effect->data['frequency'] ?? '') ?: 'Frequency not recorded' }}</p><p class="whitespace-pre-line">{{ $effect->data['advice'] }}</p>
                    <p class="text-xs break-words">Source: {{ $effect->source }} · Reviewed {{ substr($effect->reviewed_on, 0, 10) }}</p>
                </article>
            @empty<p class="text-base-content/70">No published side-effect information.</p>@endforelse
        </div></details>
        <details><summary class="font-semibold cursor-pointer">Equivalent products</summary><div class="mt-2 space-y-2">
            <p class="text-xs">Existing equivalent-product groups. Inventory and reporting behavior remain unchanged.</p>
            @forelse ($equivalents as $equivalent)<p>{{ $equivalent->drug_concat ?: $equivalent->dmdcomb }} @if (isset($equivalent->total_stock)) · Available: {{ $equivalent->total_stock }} @endif</p>@empty<p class="text-base-content/70">No {{ $location ? 'in-stock ' : '' }}equivalent products found.</p>@endforelse
        </div></details>
        @if ($details['availableStock'] !== null && $details['availableStock'] <= 0)
            <p role="status" class="alert alert-warning">This product has no available unexpired stock at the current location. Review equivalent products and therapeutic alternatives below.</p>
        @endif
        <details open><summary class="font-semibold cursor-pointer">Therapeutic alternatives</summary><div class="mt-2 space-y-3">
            <p class="text-xs">For clinical review when the prescribed drug is unavailable. Obtain an authorized prescription or follow an approved hospital protocol; no automatic replacement or dose conversion.</p>
            @forelse ($details['alternatives'] as $alternative)
                <article class="border border-base-300 rounded p-3 space-y-1">
                    <h4 class="font-semibold">{{ $alternative->target->drug_concat ?? $alternative->data['target_dmdcomb'] }} @if (!$alternative->target || $alternative->target->dmdstat !== 'A')<span class="badge badge-warning">Inactive or missing product</span>@endif</h4>
                    @if ($alternative->stock !== null)<p>Available at current location: {{ $alternative->stock }}</p>@endif
                    <dl>@foreach (['indication' => 'Indication', 'conditions' => 'Conditions', 'dose_notes' => 'Dose and route', 'monitoring' => 'Monitoring', 'authorization' => 'Authorization'] as $field => $label)<div class="mt-1"><dt class="font-medium">{{ $label }}</dt><dd class="whitespace-pre-line">{{ $alternative->data[$field] }}</dd></div>@endforeach</dl>
                    <p class="text-xs break-words">Source: {{ $alternative->source }} · Reviewed {{ substr($alternative->reviewed_on, 0, 10) }}</p>
                </article>
            @empty<p class="text-base-content/70">No published therapeutic alternatives.</p>@endforelse
        </div></details>
        <details><summary class="font-semibold cursor-pointer">Interaction reference</summary><div class="mt-2 space-y-3">
            @forelse ($details['interactions'] as $interaction)
                <article class="border-l-2 border-warning pl-3"><p class="font-medium">{{ $names[$interaction->data['ingredient_a']] ?? 'Unknown ingredient' }} + {{ $names[$interaction->data['ingredient_b']] ?? 'Unknown ingredient' }} · {{ ucfirst($interaction->data['severity']) }}</p><p>{{ $interaction->data['effect'] }}</p><p>{{ $interaction->data['advice'] }}</p>
                    @if (!empty($interaction->data['route_a']) || !empty($interaction->data['route_b']))<p class="text-xs">Route restrictions: {{ ($interaction->data['route_a'] ?? '') ?: 'Any' }} / {{ ($interaction->data['route_b'] ?? '') ?: 'Any' }}</p>@endif
                    <p class="text-xs break-words">Source: {{ $interaction->source }} · Reviewed {{ substr($interaction->reviewed_on, 0, 10) }}</p></article>
            @empty<p class="text-base-content/70">No published interaction reference information.</p>@endforelse
        </div></details>
    @endif
</section>
