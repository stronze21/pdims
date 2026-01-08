<div class="p-6">
    <x-mary-card title="Queue Display Settings">
        <div class="space-y-6">
            {{-- Location Selection --}}
            <x-mary-select wire:model.live="locationCode" label="Pharmacy Location" :options="$locations"
                option-value="pharm_location_code" option-label="description" />

            {{-- Refresh Settings --}}
            <div class="divider">Refresh Settings</div>

            <x-mary-input wire:model="autoRefreshSeconds" label="Auto Refresh (seconds)" type="number" min="3"
                max="300" hint="How often the display refreshes (3-300 seconds). Recommended: 5-10 seconds" />

            <x-mary-input wire:model="displayLimit" label="Display Limit" type="number" min="5" max="50"
                hint="Maximum number of queues to show (5-50)" />

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
                        Display Limit: {{ $displayLimit }} queues |
                        Patient Names: {{ $showPatientName ? 'Yes' : 'No' }}
                    </div>
                </div>
            </div>
        </div>
    </x-mary-card>
</div>
