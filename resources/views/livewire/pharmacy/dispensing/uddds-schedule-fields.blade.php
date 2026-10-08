@if ($toecode === 'ADM')
    @php($udddsIntervalReady = \App\Services\Pharmacy\UdddsService::hasIntervalColumn())
    <fieldset class="mt-3 space-y-2 border border-base-300 rounded p-3">
        <legend class="text-sm font-semibold">Order type and UDDDS supply schedule</legend>
        <label class="block text-xs">Order type
            <select class="select select-bordered select-sm w-full" wire:model.live="rx_order_type">
                <option value="BASIC">Basic (standing)</option>
                <option value="G24">G24</option>
                <option value="OR">OR Use</option>
            </select>
        </label>
        @error('rx_order_type') <p class="text-error text-xs">{{ $message }}</p> @enderror
        @if ($rx_order_type === 'BASIC')
        <p class="text-xs opacity-70">Calendar-day supply intervals anchored to the start date. Quantity is for one supply occurrence.</p>
        <div class="grid grid-cols-2 gap-2">
            <label class="text-xs">Start date<input type="date" class="input input-bordered input-sm w-full" wire:model="uddds_start_date"></label>
            <label class="text-xs">End date<input type="date" class="input input-bordered input-sm w-full" wire:model="uddds_end_date"></label>
        </div>
        <label class="block text-xs">Recurrence
            <select class="select select-bordered select-sm w-full" wire:model.live="uddds_choice">
                <option value="1">Daily</option>
                <option value="2" @disabled(!$udddsIntervalReady)>Every 2 days (48h)</option>
                <option value="3" @disabled(!$udddsIntervalReady)>Every 3 days (72h)</option>
                <option value="custom" @disabled(!$udddsIntervalReady)>Custom: every N days</option>
            </select>
        </label>
        @if ($uddds_choice === 'custom')
            <label class="block text-xs">Every N days<input type="number" min="1" max="2147483647" step="1" class="input input-bordered input-sm w-full" wire:model="uddds_interval_days"></label>
        @endif
        @unless ($udddsIntervalReady)
            <p class="text-xs text-warning">Interval enrollment requires the UDDDS interval database update. Daily remains available.</p>
        @endunless
        @else
            <p class="text-xs opacity-70">UDDDS recurrence is available only for Basic (standing) orders.</p>
        @endif
        @error('uddds_interval_days') <p class="text-error text-xs">{{ $message }}</p> @enderror
    </fieldset>
@endif
