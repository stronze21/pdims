<div>
    <x-mary-header title="Teleconsult Summary" separator>
        <x-slot:actions>
            <x-mary-button label="Back to Lobby" icon="o-arrow-left"
                class="btn-ghost" wire:click="backToLobby" />
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Session Details --}}
        <x-mary-card title="Session Details">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-500">Patient</span>
                    <span class="font-medium">{{ $session->patient?->getFullnameAttribute() ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Doctor</span>
                    <span class="font-medium">{{ $session->doctor_name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Status</span>
                    <span class="badge {{ $session->status === 'completed' ? 'badge-success' : 'badge-ghost' }}">
                        {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Scheduled</span>
                    <span>{{ $session->scheduled_at?->format('M d, Y h:i A') ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Started</span>
                    <span>{{ $session->started_at?->format('M d, Y h:i A') ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Ended</span>
                    <span>{{ $session->ended_at?->format('M d, Y h:i A') ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Duration</span>
                    <span>{{ $session->duration_minutes ? $session->duration_minutes . ' minutes' : 'N/A' }}</span>
                </div>
            </div>
        </x-mary-card>

        {{-- SOAP Notes --}}
        <x-mary-card title="Consultation Notes (SOAP)">
            @if ($session->note)
                <div class="space-y-4">
                    @if ($session->note->subjective)
                        <div>
                            <h5 class="font-bold text-sm text-primary mb-1">Subjective</h5>
                            <p class="text-sm whitespace-pre-wrap">{{ $session->note->subjective }}</p>
                        </div>
                    @endif

                    @if ($session->note->objective)
                        <div>
                            <h5 class="font-bold text-sm text-primary mb-1">Objective</h5>
                            <p class="text-sm whitespace-pre-wrap">{{ $session->note->objective }}</p>
                        </div>
                    @endif

                    @if ($session->note->assessment)
                        <div>
                            <h5 class="font-bold text-sm text-primary mb-1">Assessment</h5>
                            <p class="text-sm whitespace-pre-wrap">{{ $session->note->assessment }}</p>
                        </div>
                    @endif

                    @if ($session->note->plan)
                        <div>
                            <h5 class="font-bold text-sm text-primary mb-1">Plan</h5>
                            <p class="text-sm whitespace-pre-wrap">{{ $session->note->plan }}</p>
                        </div>
                    @endif

                    @if ($session->note->additional_notes)
                        <div>
                            <h5 class="font-bold text-sm text-primary mb-1">Additional Notes</h5>
                            <p class="text-sm whitespace-pre-wrap">{{ $session->note->additional_notes }}</p>
                        </div>
                    @endif
                </div>
            @else
                <div class="text-center py-8 text-gray-400">
                    <x-mary-icon name="o-document-text" class="w-12 h-12 mx-auto mb-2 opacity-50" />
                    <p>No consultation notes recorded.</p>
                </div>
            @endif
        </x-mary-card>
    </div>
</div>
