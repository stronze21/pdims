<div>
    <x-mary-header title="Ward Prescriptions" separator />

    {{-- Navigation Tabs --}}
    <div class="flex justify-center gap-2 mb-4">
        <a href="{{ route('rx.ward') }}" class="btn btn-primary btn-sm" wire:navigate>
            <x-mary-icon name="o-building-office-2" class="w-5 h-5" />
            Wards
        </a>
        <a href="{{ route('rx.opd') }}" class="btn btn-ghost btn-sm" wire:navigate>
            <x-mary-icon name="o-user-group" class="w-5 h-5" />
            Out Patient Department
        </a>
        <a href="{{ route('rx.er') }}" class="btn btn-ghost btn-sm" wire:navigate>
            <x-mary-icon name="o-heart" class="w-5 h-5" />
            Emergency Room
        </a>
    </div>

    {{-- Filters and Table Card --}}
    <x-mary-card>
        {{-- Filters Row --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            {{-- Ward Filter --}}
            <div>
                <label class="label">
                    <span class="label-text">Ward</span>
                </label>
                <select class="select select-bordered select-sm w-full" wire:model.live="wardcode">
                    <option value="">All</option>
                    @foreach ($wards as $ward)
                        <option value="{{ $ward->wardcode }}">{{ $ward->wardname }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Rx Tag Filter - Radio Button Style --}}
            <div class="md:col-span-2">
                <label class="label">
                    <span class="label-text">Rx Tag</span>
                </label>
                <div class="flex gap-2">
                    <button wire:click="setRxTagFilter('all')"
                        class="btn btn-sm {{ $rx_tag_filter === 'all' ? 'btn-primary' : 'btn-ghost' }}">
                        All
                    </button>
                    <button wire:click="setRxTagFilter('basic')"
                        class="btn btn-sm {{ $rx_tag_filter === 'basic' ? 'btn-accent' : 'btn-ghost' }}">
                        BASIC
                    </button>
                    <button wire:click="setRxTagFilter('g24')"
                        class="btn btn-sm {{ $rx_tag_filter === 'g24' ? 'btn-error' : 'btn-ghost' }}">
                        Good For 24 Hrs
                    </button>
                    <button wire:click="setRxTagFilter('or')"
                        class="btn btn-sm {{ $rx_tag_filter === 'or' ? 'btn-secondary' : 'btn-ghost' }}">
                        Operating Room
                    </button>
                </div>
            </div>

            {{-- Patient Search --}}
            <div>
                <label class="label">
                    <span class="label-text">Patient</span>
                </label>
                <x-mary-input wire:model.live.debounce.300ms="search" icon="o-magnifying-glass"
                    placeholder="Search patient..." class="input-sm" />
            </div>
        </div>

        {{-- Loading State --}}
        <div wire:loading class="flex items-center gap-2 p-4">
            <span class="loading loading-spinner loading-sm"></span>
            <span>Loading prescriptions...</span>
        </div>

        {{-- Table --}}
        <div wire:loading.remove class="overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date Admitted</th>
                        <th>Patient Name</th>
                        <th>Department</th>
                        <th>Patient Classification</th>
                        <th>Rx Tag</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($prescriptions as $index => $rx)
                        @php
                            // Single filter selection logic
                            if ($rx_tag_filter === 'all') {
                                $showRow = true; // Show all prescriptions
                            } elseif ($rx_tag_filter === 'basic') {
                                $showRow = $rx->basic > 0; // Show only BASIC
                            } elseif ($rx_tag_filter === 'g24') {
                                $showRow = $rx->g24 > 0; // Show only Good For 24 Hrs
                            } elseif ($rx_tag_filter === 'or') {
                                $showRow = $rx->or > 0; // Show only Operating Room
                            } else {
                                $showRow = true; // Default: show all
                            }

                            // Apply search filter
                            if ($search && $showRow) {
                                $searchLower = strtolower($search);
                                $patientName = strtolower($rx->patlast . ' ' . $rx->patfirst . ' ' . $rx->patmiddle);
                                $showRow =
                                    str_contains($patientName, $searchLower) ||
                                    str_contains(strtolower($rx->hpercode), $searchLower);
                            }
                        @endphp

                        @if ($showRow)
                            <tr wire:key="ward-{{ $rx->enccode }}" wire:click="viewEncounter('{{ $rx->enccode }}')"
                                class="cursor-pointer hover">
                                <td>{{ $index + 1 }}</td>
                                <td class="whitespace-nowrap">
                                    <div class="text-sm">
                                        {{ \Carbon\Carbon::parse($rx->admdate)->format('Y/m/d') }}
                                    </div>
                                    <div class="text-xs opacity-70">
                                        {{ \Carbon\Carbon::parse($rx->admdate)->format('g:i A') }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="font-medium">
                                        {{ strtoupper($rx->patlast) }}, {{ strtoupper($rx->patfirst) }}
                                        {{ strtoupper($rx->patsuffix) }} {{ strtoupper($rx->patmiddle) }}
                                    </div>
                                    <div class="text-xs">
                                        <span class="badge badge-ghost badge-xs">{{ $rx->hpercode }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="text-sm font-medium">{{ $rx->wardname }}</div>
                                    <div class="text-xs opacity-70">{{ $rx->rmname }}</div>
                                </td>
                                <td>
                                    @php
                                        $class = match ($rx->mssikey) {
                                            'MSSA11111999', 'MSSB11111999' => 'Pay',
                                            'MSSC111111999' => 'PP1',
                                            'MSSC211111999' => 'PP2',
                                            'MSSC311111999' => 'PP3',
                                            'MSSD11111999' => 'Indigent',
                                            default => $rx->mssikey ? '---' : 'Indigent',
                                        };

                                        $badgeClass = match ($class) {
                                            'Pay' => 'badge-success',
                                            'PP1', 'PP2', 'PP3' => 'badge-warning',
                                            'Indigent' => 'badge-ghost',
                                            default => 'badge-info',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} badge-sm">{{ $class }}</span>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        @if ($rx->basic)
                                            <div class="tooltip" data-tip="BASIC">
                                                <div class="badge badge-accent badge-sm gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3"
                                                        viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                                        <path fill-rule="evenodd"
                                                            d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    {{ $rx->basic }}
                                                </div>
                                            </div>
                                        @endif
                                        @if ($rx->g24)
                                            <div class="tooltip" data-tip="Good For 24 Hrs">
                                                <div class="badge badge-error badge-sm gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3"
                                                        viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    {{ $rx->g24 }}
                                                </div>
                                            </div>
                                        @endif
                                        @if ($rx->or)
                                            <div class="tooltip" data-tip="For Operating Use">
                                                <div class="badge badge-secondary badge-sm gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3"
                                                        viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                            d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    {{ $rx->or }}
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="flex flex-col items-center gap-2 py-8">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 opacity-30" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="text-sm opacity-50">No prescriptions found</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-mary-card>
</div>
