<div class="p-6">
    <x-mary-card title="Queue Display Settings">
        <div class="space-y-6">
            {{-- Location Selection --}}
            <x-mary-select wire:model.live="locationCode" label="Pharmacy Location" :options="$locations"
                option-label="description" />

            {{-- Refresh Settings --}}
            <div class="divider">Refresh Settings</div>

            <x-mary-input wire:model="autoRefreshSeconds" label="Auto Refresh (seconds)" type="number" min="3"
                max="300" hint="How often the display refreshes (3-300 seconds). Recommended: 5-10 seconds" />

            <x-mary-input wire:model="displayLimit" label="Display Limit" type="number" min="5" max="50"
                hint="Maximum number of queues to show (5-50)" />

            {{-- Window Configuration --}}
            <div class="divider">Window Configuration</div>

            <x-mary-input wire:model="pharmacyWindows" label="Number of Pharmacy Windows" type="number" min="1"
                max="10"
                hint="Total pharmacy windows (handles both preparation and dispensing at same window: 1-10)" />

            {{-- Cashier Settings --}}
            <div class="divider">Cashier Configuration</div>

            <x-mary-checkbox wire:model.live="requireCashier" label="Require Cashier Payment"
                hint="Enable cashier workflow before dispensing. Uncheck if cashier is in another building." />

            @if ($requireCashier)
                <x-mary-input wire:model="cashierLocation" label="Cashier Location (Optional)"
                    placeholder="e.g., Building A, 2nd Floor"
                    hint="Physical location of cashier for patient information" />
            @else
                <div class="alert alert-warning">
                    <x-mary-icon name="o-exclamation-triangle" class="w-5 h-5" />
                    <span class="text-sm">Cashier workflow is bypassed. Queue will go directly from preparing to ready
                        for dispensing.</span>
                </div>
            @endif

            {{-- Display Options --}}
            <div class="divider">Display Options</div>

            <x-mary-checkbox wire:model="showPatientName" label="Show Patient Names"
                hint="Display patient names on the queue screen (privacy consideration)" />

            <x-mary-checkbox wire:model="playSoundAlert" label="Play Sound Alert"
                hint="Play notification sound when queue is called" />

            <x-mary-checkbox wire:model="showEstimatedWait" label="Show Estimated Wait Time"
                hint="Display estimated wait time for each queue" />

            {{-- Action Buttons --}}
            <div class="flex gap-2 pt-4">
                <button wire:click="save" class="btn btn-primary">
                    <x-mary-icon name="o-check" class="w-5 h-5" />
                    Save Settings
                </button>
                <button wire:click="loadSettings" class="btn btn-ghost">
                    <x-mary-icon name="o-arrow-path" class="w-5 h-5" />
                    Reset
                </button>
            </div>

            {{-- Info Box --}}
            <div class="alert alert-info mt-6">
                <x-mary-icon name="o-information-circle" class="w-6 h-6" />
                <div>
                    <div class="font-semibold">Current Settings</div>
                    <div class="text-sm">
                        Refresh: {{ $autoRefreshSeconds }}s |
                        Display: {{ $displayLimit }} queues<br>
                        Pharmacy Windows: {{ $pharmacyWindows }}<br>
                        Cashier: {{ $requireCashier ? 'Required' : 'Bypassed' }}
                        @if ($requireCashier && $cashierLocation)
                            ({{ $cashierLocation }})
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-mary-card>
</div>
