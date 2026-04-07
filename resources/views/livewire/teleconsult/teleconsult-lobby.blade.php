<div>
    <x-mary-header title="Teleconsult" subtitle="Manage telemedicine sessions" separator>
        <x-slot:actions>
            <x-mary-button label="New Session" icon="o-plus" class="btn-primary"
                wire:click="openCreateDrawer" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Status Filter Tabs --}}
    <div class="flex flex-wrap gap-2 mb-6">
        <button wire:click="$set('statusFilter', 'today')"
            class="btn btn-sm {{ $statusFilter === 'today' ? 'btn-primary' : 'btn-ghost' }}">
            Today
            <span class="badge badge-sm ml-1">{{ $this->statusCounts['today'] }}</span>
        </button>
        <button wire:click="$set('statusFilter', 'upcoming')"
            class="btn btn-sm {{ $statusFilter === 'upcoming' ? 'btn-primary' : 'btn-ghost' }}">
            Upcoming
            <span class="badge badge-sm ml-1">{{ $this->statusCounts['upcoming'] }}</span>
        </button>
        <button wire:click="$set('statusFilter', 'in_progress')"
            class="btn btn-sm {{ $statusFilter === 'in_progress' ? 'btn-success' : 'btn-ghost' }}">
            In Progress
            <span class="badge badge-sm ml-1">{{ $this->statusCounts['in_progress'] }}</span>
        </button>
        <button wire:click="$set('statusFilter', 'completed')"
            class="btn btn-sm {{ $statusFilter === 'completed' ? 'btn-primary' : 'btn-ghost' }}">
            Completed
            <span class="badge badge-sm ml-1">{{ $this->statusCounts['completed'] }}</span>
        </button>
    </div>

    {{-- Search --}}
    <div class="mb-6">
        <x-mary-input wire:model.live.debounce.300ms="search" placeholder="Search by patient name, doctor, or hpercode..."
            icon="o-magnifying-glass" clearable />
    </div>

    {{-- Sessions Table --}}
    <x-mary-card>
        <x-mary-table :headers="[
            ['key' => 'patient', 'label' => 'Patient'],
            ['key' => 'hpercode', 'label' => 'Hpercode'],
            ['key' => 'ref_no', 'label' => 'Ref No.'],
            ['key' => 'doctor', 'label' => 'Doctor'],
            ['key' => 'scheduled', 'label' => 'Scheduled'],
            ['key' => 'platform', 'label' => 'Platform'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'actions', 'label' => 'Actions'],
        ]" :rows="$sessions" with-pagination>

            @scope('cell_patient', $session)
                <div class="font-medium">
                    {{ $session->patient?->getFullnameAttribute() ?? 'N/A' }}
                </div>
            @endscope

            @scope('cell_hpercode', $session)
                <div class="font-mono text-sm">
                    {{ $session->patient?->hpercode ?? '-' }}
                </div>
            @endscope

            @scope('cell_ref_no', $session)
                @php
                    $refNo = $session->appointment_id
                        ? \Illuminate\Support\Facades\DB::connection('portal')
                            ->table('appointments')
                            ->where('id', $session->appointment_id)
                            ->value('ref_no')
                        : null;
                @endphp
                <div class="font-mono text-sm">
                    {{ $refNo ?? '-' }}
                </div>
            @endscope

            @scope('cell_doctor', $session)
                <div>{{ $session->doctor_name ?? 'Unassigned' }}</div>
            @endscope

            @scope('cell_scheduled', $session)
                <div>
                    {{ $session->scheduled_at?->format('M d, Y') ?? 'N/A' }}
                    <div class="text-xs text-gray-500">
                        {{ $session->scheduled_at?->format('h:i A') }}
                    </div>
                </div>
            @endscope

            @scope('cell_platform', $session)
                @php
                    $p = $session->platform ?? 'webex';
                    $platformBadge = match($p) {
                        'jitsi' => ['badge-info', 'o-video-camera', 'Jitsi'],
                        'livekit' => ['badge-secondary', 'o-signal', 'LiveKit'],
                        default => ['badge-accent', 'o-globe-alt', 'Webex'],
                    };
                @endphp
                <span class="badge {{ $platformBadge[0] }} badge-sm gap-1">
                    <x-mary-icon :name="$platformBadge[1]" class="w-3 h-3" />
                    {{ $platformBadge[2] }}
                </span>
            @endscope

            @scope('cell_status', $session)
                @php
                    $statusColors = [
                        'scheduled' => 'badge-primary',
                        'waiting' => 'badge-warning',
                        'in_progress' => 'badge-success',
                        'completed' => 'badge-ghost',
                        'cancelled' => 'badge-error',
                        'no_show' => 'badge-error',
                    ];
                @endphp
                <span class="badge {{ $statusColors[$session->status] ?? 'badge-ghost' }}">
                    {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                </span>
            @endscope

            @scope('cell_actions', $session)
                <div class="flex gap-1">
                    @if (in_array($session->status, ['scheduled', 'waiting', 'in_progress']))
                        <x-mary-button label="Start" icon="o-video-camera" class="btn-sm btn-primary"
                            wire:click="startSession({{ $session->id }})" />
                    @endif

                    @if ($session->status === 'completed')
                        <x-mary-button label="Summary" icon="o-document-text" class="btn-sm btn-ghost"
                            link="{{ route('teleconsult.summary', $session->id) }}" />
                    @endif

                    @if ($session->status === 'scheduled')
                        <x-mary-button icon="o-x-mark" class="btn-sm btn-ghost text-error"
                            wire:click="cancelSession({{ $session->id }})"
                            wire:confirm="Are you sure you want to cancel this session?" />
                    @endif
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- New Session Drawer --}}
    <div class="drawer drawer-end" x-data="{ open: @entangle('showCreateDrawer').live }">
        <input type="checkbox" class="drawer-toggle" x-model="open" />
        <div class="drawer-side z-50">
            <label class="drawer-overlay" @click="open = false; $wire.closeCreateDrawer()"></label>
            <div class="bg-base-100 w-[96vw] lg:w-[1100px] h-full flex flex-col shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-base-300 bg-base-200 px-5 py-4">
                    <div>
                        <h3 class="text-lg font-bold text-base-content">Create Teleconsult Session</h3>
                        <p class="text-sm text-base-content/60">Pick an appointment from the list, then create a Jitsi Meet session.</p>
                    </div>
                    <button class="btn btn-ghost btn-sm" @click="open = false; $wire.closeCreateDrawer()">
                        <x-mary-icon name="o-x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <div class="grid h-full min-h-0 lg:grid-cols-[1.5fr_1fr]">
                    <div class="flex min-h-0 flex-col border-r border-base-300">
                        <div class="border-b border-base-300 p-4">
                            <x-mary-input
                                wire:model.live.debounce.300ms="appointmentSearch"
                                placeholder="Search by ref no., patient name, or hpercode..."
                                icon="o-magnifying-glass"
                                clearable />
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <div class="overflow-x-auto">
                                <table class="table table-sm">
                                    <thead class="bg-base-200 sticky top-0 z-10">
                                        <tr>
                                            <th class="text-xs font-bold uppercase text-base-content">Appointment</th>
                                            <th class="text-xs font-bold uppercase text-base-content">Patient</th>
                                            <th class="text-xs font-bold uppercase text-base-content">Date / Time</th>
                                            <th class="text-xs font-bold uppercase text-base-content">Type</th>
                                            <th class="text-xs font-bold uppercase text-base-content text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($appointmentRows as $appointment)
                                            <tr
                                                wire:key="appointment-{{ $appointment['id'] }}"
                                                class="cursor-pointer border-b border-base-300 transition-colors {{ (int) $appointmentId === (int) $appointment['id'] ? 'bg-primary/10' : 'hover:bg-base-200/70' }}"
                                                wire:click="selectAppointment({{ $appointment['id'] }})">
                                                <td class="py-3">
                                                    <div class="font-mono text-sm font-semibold text-base-content">{{ $appointment['ref_no'] ?? '-' }}</div>
                                                    @if($appointment['doctor'])
                                                        <div class="text-xs text-base-content/60">Dr. {{ $appointment['doctor'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="py-3">
                                                    <div class="font-semibold text-sm text-base-content">{{ $appointment['patient_name'] }}</div>
                                                    <div class="text-xs text-base-content/60 font-mono">{{ $appointment['hpercode'] ?? 'N/A' }}</div>
                                                </td>
                                                <td class="py-3">
                                                    <div class="text-sm text-base-content">{{ \Illuminate\Support\Carbon::parse($appointment['appointment_date'])->format('M d, Y') }}</div>
                                                    <div class="text-xs text-base-content/60">{{ \Illuminate\Support\Carbon::parse($appointment['appointment_date'])->format('h:i A') }}</div>
                                                </td>
                                                <td class="py-3">
                                                    <span class="badge badge-sm badge-ghost">{{ $appointment['type_name'] }}</span>
                                                </td>
                                                <td class="py-3 text-center">
                                                    <x-mary-button
                                                        label="{{ (int) $appointmentId === (int) $appointment['id'] ? 'Selected' : 'Select' }}"
                                                        class="{{ (int) $appointmentId === (int) $appointment['id'] ? 'btn-primary btn-xs' : 'btn-ghost btn-xs' }}"
                                                        wire:click.stop="selectAppointment({{ $appointment['id'] }})" />
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-14 text-center">
                                                    <div class="flex flex-col items-center">
                                                        <x-mary-icon name="o-calendar-days" class="mb-3 h-14 w-14 text-base-content/30" />
                                                        <span class="text-lg font-semibold text-base-content/60">No appointments found</span>
                                                        <span class="mt-2 text-sm text-base-content/50">Try another search term or wait for more confirmed appointments.</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-col">
                        <div class="border-b border-base-300 p-4">
                            <h4 class="text-base font-semibold text-base-content">Session Details</h4>
                            <p class="text-sm text-base-content/60">This session will always use Jitsi Meet.</p>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto p-4 space-y-4">
                            @if($appointmentId && !empty($selectedAppointment))
                                <div class="rounded-2xl border border-primary/20 bg-primary/10 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-primary">Selected Appointment</div>
                                    <div class="mt-2 space-y-2 text-sm text-base-content">
                                        <div><span class="text-base-content/60">Ref No:</span> {{ $selectedAppointment['ref_no'] }}</div>
                                        <div><span class="text-base-content/60">Patient:</span> {{ $selectedAppointment['patient_name'] }}</div>
                                        <div><span class="text-base-content/60">HPerson Code:</span> {{ $selectedAppointment['hpercode'] ?? 'N/A' }}</div>
                                        <div><span class="text-base-content/60">Appointment Date:</span> {{ \Illuminate\Support\Carbon::parse($selectedAppointment['appointment_date'])->format('M d, Y h:i A') }}</div>
                                        <div><span class="text-base-content/60">Type:</span> {{ $selectedAppointment['type_name'] }}</div>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-2xl border border-dashed border-base-300 bg-base-200/40 p-6 text-center">
                                    <x-mary-icon name="o-hand-thumb-up" class="mx-auto mb-3 h-12 w-12 text-base-content/30" />
                                    <p class="font-semibold text-base-content/70">Select an appointment to continue</p>
                                    <p class="mt-1 text-sm text-base-content/50">The date and time fields will be filled automatically.</p>
                                </div>
                            @endif

                            <x-mary-form wire:submit="createSession">
                                <input type="hidden" wire:model="appointmentId" />

                                <div class="grid grid-cols-2 gap-4">
                                    <x-mary-input label="Date" type="date" wire:model="scheduledDate" />
                                    <x-mary-input label="Time" type="time" wire:model="scheduledTime" />
                                </div>

                                <x-slot:actions>
                                    <x-mary-button label="Cancel" type="button" wire:click="closeCreateDrawer" />
                                    <x-mary-button label="Create Session" type="submit" class="btn-primary" spinner="createSession" />
                                </x-slot:actions>
                            </x-mary-form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
