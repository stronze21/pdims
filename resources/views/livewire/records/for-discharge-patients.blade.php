<div class="py-5 mx-3">
    {{-- Header --}}
    <div class="mb-6">
        <x-mary-header title="Discharged Patients" separator>
            <x-slot:middle class="!justify-end">
                <x-mary-icon name="o-map-pin" label="{{ session('pharm_location_name') }}" />
            </x-slot:middle>
            <x-slot:actions>
                <div class="text-sm text-gray-500">
                    Showing {{ count($patients) }} of {{ $totalCount }} {{ Str::plural('patient', $totalCount) }}
                </div>
            </x-slot:actions>
        </x-mary-header>
    </div>

    {{-- Filters --}}
    <div class="mb-6">
        <x-mary-card>
            <div class="flex flex-wrap gap-4 items-end">

                <x-mary-input label="Search" wire:model.live.debounce.300ms="search" icon="o-magnifying-glass"
                    placeholder="Hospital #, Name, Ward, Department..." class="flex-1 min-w-64" />
            </div>
        </x-mary-card>
    </div>

    {{-- Table --}}
    <x-mary-card>
        <div class="overflow-x-auto">
            <table class="table table-sm table-pin-rows table-pin-cols">
                <thead>
                    <tr class="bg-base-200">
                        <th class="w-32">Date Admitted</th>
                        <th class="w-32">Hospital #</th>
                        <th class="w-64">Patient Name</th>
                        <th class="w-48">Ward/Room</th>
                        <th class="w-48">Department</th>
                        <th class="w-32">Date Discharged</th>
                        <th class="w-40">Condition/Status</th>
                        <th class="w-24">MSS Class</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($patients as $patient)
                        <tr wire:key="patient-{{ $patient->enccode }}-{{ $loop->index }}"
                            wire:click="viewEncounter('{{ $patient->enccode }}')"
                            class="hover cursor-pointer transition-colors">
                            <td>
                                {{ \Carbon\Carbon::parse($patient->admdate)->format('m/d/Y') }}
                            </td>
                            <td>
                                <span class="font-mono text-sm">{{ $patient->hpercode }}</span>
                            </td>
                            <td>
                                <div class="font-semibold">
                                    {{ $patient->patlast }}, {{ $patient->patfirst }}
                                    {{ $patient->patsuffix }} {{ $patient->patmiddle }}
                                </div>
                            </td>
                            <td>
                                <div class="text-sm">
                                    <span class="font-semibold">{{ $patient->wardname }}</span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $patient->rmname }}
                                </div>
                            </td>
                            <td>
                                <span class="text-sm">{{ $patient->tsdesc }}</span>
                            </td>
                            <td>
                                {{ \Carbon\Carbon::parse($patient->disdate)->format('m/d/Y') }}
                            </td>
                            <td>
                                @php
                                    $status = $this->getConditionStatus($patient->condcode);
                                    $badgeClass = match ($patient->condcode) {
                                        'RECOV' => 'badge-success',
                                        'IMPRO' => 'badge-info',
                                        'UNIMP' => 'badge-warning',
                                        default => 'badge-error',
                                    };
                                @endphp
                                <span
                                    class="badge {{ $badgeClass }} badge-sm whitespace-nowrap">{{ $status }}</span>
                            </td>
                            <td>
                                @php
                                    $mssClass = $this->getMssClass($patient->mssikey);
                                    $mssStyle = match ($mssClass) {
                                        'Pay' => 'badge-primary',
                                        'PP1', 'PP2', 'PP3' => 'badge-secondary',
                                        'Indigent' => 'badge-accent',
                                        default => 'badge-ghost',
                                    };
                                @endphp
                                <span
                                    class="badge {{ $mssStyle }} badge-sm whitespace-nowrap">{{ $mssClass }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8">
                                <div class="flex flex-col items-center gap-2 text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <span>No discharged patients found for the selected date range</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Load More Trigger --}}
        @if ($hasMore)
            <div x-intersect="$wire.loadMore()" class="py-4 text-center">
                <div wire:loading.remove wire:target="loadMore">
                    <span class="loading loading-dots loading-md"></span>
                </div>
                <div wire:loading wire:target="loadMore">
                    <span class="loading loading-spinner loading-md"></span>
                    <span class="ml-2">Loading more...</span>
                </div>
            </div>
        @endif
    </x-mary-card>

    {{-- Loading Overlay - Subtle --}}
    <div wire:loading wire:target="viewEncounter,date_from,date_to,search"
        class="fixed top-20 left-1/2 -translate-x-1/2 z-50">
        <div class="bg-base-100 rounded-xl px-6 py-4 shadow-2xl border border-primary/20">
            <div class="flex items-center gap-3">
                <span class="loading loading-spinner loading-md text-primary"></span>
                <span class="text-base font-medium">Loading patients...</span>
            </div>
        </div>
    </div>

    {{-- Subtle overlay to prevent interaction --}}
    <div wire:loading wire:target="viewEncounter,date_from,date_to,search" class="fixed inset-0 z-40 bg-base-100/5">
    </div>
</div>
