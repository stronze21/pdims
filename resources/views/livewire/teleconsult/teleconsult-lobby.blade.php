<div>
    <x-mary-header title="Teleconsult" subtitle="Manage telemedicine sessions" separator>
        <x-slot:actions>
            <x-mary-button label="New Session" icon="o-plus" class="btn-primary"
                wire:click="$set('showCreateModal', true)" />
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

    {{-- Create Session Modal --}}
    <x-mary-modal wire:model="showCreateModal" title="Create Teleconsult Session" class="backdrop-blur" box-class="w-11/12 max-w-2xl overflow-visible">
        <x-mary-form wire:submit="createSession">
            <x-mary-choices
                label="Appointment"
                wire:model="appointmentId"
                :options="$appointmentOptions"
                option-value="id"
                option-label="name"
                placeholder="Search by ref no., patient name, or hpercode..."
                search-function="searchAppointments"
                min-chars="2"
                searchable
                single />

            <x-mary-select
                label="Platform"
                wire:model="platform"
                :options="$this->platformOptions"
                option-value="id"
                option-label="name"
                placeholder="Select video platform..."
                icon="o-video-camera" />

            <div class="grid grid-cols-2 gap-4">
                <x-mary-input label="Date" type="date" wire:model="scheduledDate" />
                <x-mary-input label="Time" type="time" wire:model="scheduledTime" />
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('showCreateModal', false)" />
                <x-mary-button label="Create Session" type="submit" class="btn-primary" spinner="createSession" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
