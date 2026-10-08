@php
    try {
        $supplyLabel = \App\Services\Pharmacy\UdddsSchedule::label($rxo->uddds_interval_days ?? null);
        $nextSupply = \App\Services\Pharmacy\UdddsSchedule::next($rxo->uddds_start_date, $rxo->uddds_end_date, $rxo->uddds_interval_days ?? null, now('Asia/Manila')->toDateString());
    } catch (\InvalidArgumentException $e) {
        $supplyLabel = 'Invalid schedule: ' . $e->getMessage();
        $nextSupply = null;
    }
@endphp
<span class="block text-xs" title="Calendar-day supply schedule">{{ $supplyLabel }} · Next supply: {{ $nextSupply ?? 'None within window' }}</span>
