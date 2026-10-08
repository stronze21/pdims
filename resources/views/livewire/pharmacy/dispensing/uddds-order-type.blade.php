@php
    $udddsOrderType = strtoupper(trim((string) ($orderType ?? 'BASIC'))) ?: 'BASIC';
    $udddsOrderLabels = ['BASIC' => 'Basic (standing)', 'G24' => 'G24', 'OR' => 'OR Use'];
    $udddsOrderStyles = ['BASIC' => 'badge-accent', 'G24' => 'badge-error', 'OR' => 'badge-secondary'];
@endphp
<span class="badge badge-xs whitespace-nowrap {{ $udddsOrderStyles[$udddsOrderType] ?? 'badge-ghost' }}">{{ $udddsOrderLabels[$udddsOrderType] ?? $udddsOrderType }}</span>
