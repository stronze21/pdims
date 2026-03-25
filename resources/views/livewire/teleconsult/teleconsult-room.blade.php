<div>
    {{-- Top Bar --}}
    <div class="flex items-center justify-between bg-base-200 px-4 py-2 rounded-t-lg">
        <div class="flex items-center gap-3">
            <x-mary-button icon="o-arrow-left" class="btn-sm btn-ghost"
                link="{{ route('teleconsult.lobby') }}" />
            <div>
                <h3 class="font-bold text-lg">Teleconsult Room</h3>
                <p class="text-sm text-gray-500">
                    Patient: {{ $session->patient?->getFullnameAttribute() ?? 'N/A' }}
                    &middot;
                    <span class="badge badge-sm {{ $session->status === 'in_progress' ? 'badge-success' : 'badge-warning' }}">
                        {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                    </span>
                </p>
            </div>
        </div>
        <div class="flex gap-2">
            @if ($session->status === 'scheduled' || $session->status === 'waiting')
                <x-mary-button label="Start Consult" icon="o-video-camera"
                    class="btn-sm btn-primary" wire:click="startConsult" spinner="startConsult" />
                <x-mary-button label="No Show" icon="o-x-circle"
                    class="btn-sm btn-error btn-outline" wire:click="markNoShow"
                    wire:confirm="Mark this session as no-show?" spinner="markNoShow" />
            @endif
            @if ($session->status === 'in_progress')
                <x-mary-button label="Save Notes" icon="o-document-check"
                    class="btn-sm btn-ghost" wire:click="saveNotes" spinner="saveNotes" />
                <x-mary-button label="End Consult" icon="o-phone-x-mark"
                    class="btn-sm btn-error" wire:click="endConsult"
                    wire:confirm="Are you sure you want to end the consultation?" spinner="endConsult" />
            @endif
        </div>
    </div>

    {{-- Main Content: Video + Sidebar --}}
    <div class="flex flex-col lg:flex-row gap-0 h-[calc(100vh-140px)]">
        {{-- Video Panel --}}
        <div class="lg:w-3/5 w-full bg-black rounded-bl-lg flex items-center justify-center relative">
            <div id="webex-meeting-container" class="w-full h-full"
                data-host-token="{{ $hostToken }}"
                data-meeting-link="{{ $meetingLink }}"
                data-sip-address="{{ $sipAddress }}"
                data-session-id="{{ $sessionId }}">

                @if (!$hostToken && !$meetingLink)
                    <div class="text-white text-center">
                        <x-mary-icon name="o-video-camera" class="w-16 h-16 mx-auto mb-4 opacity-50" />
                        <p class="text-lg">No Webex meeting configured</p>
                        <p class="text-sm text-gray-400">Meeting will appear here once the session is started.</p>
                    </div>
                @else
                    <div id="webex-loading" class="text-white text-center">
                        <div class="loading loading-spinner loading-lg mb-4"></div>
                        <p>Connecting to Webex...</p>
                    </div>
                    <div id="remote-video" class="w-full h-full hidden"></div>
                    <div id="local-video" class="absolute bottom-4 right-4 w-48 h-36 bg-gray-800 rounded-lg overflow-hidden hidden"></div>

                    {{-- Media Controls --}}
                    <div id="media-controls" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-3 hidden">
                        <button onclick="WebexTeleconsult.toggleAudio()" class="btn btn-circle btn-sm bg-gray-700 hover:bg-gray-600 border-0 text-white" title="Toggle Microphone">
                            <x-mary-icon name="o-microphone" class="w-5 h-5" />
                        </button>
                        <button onclick="WebexTeleconsult.toggleVideo()" class="btn btn-circle btn-sm bg-gray-700 hover:bg-gray-600 border-0 text-white" title="Toggle Camera">
                            <x-mary-icon name="o-video-camera" class="w-5 h-5" />
                        </button>
                        <button onclick="WebexTeleconsult.leave()" class="btn btn-circle btn-sm bg-red-600 hover:bg-red-700 border-0 text-white" title="Leave Meeting">
                            <x-mary-icon name="o-phone-x-mark" class="w-5 h-5" />
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Sidebar: SOAP Notes + Actions --}}
        <div class="lg:w-2/5 w-full flex flex-col border-l border-base-300 bg-base-100 rounded-br-lg">
            {{-- SOAP Notes --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <h4 class="font-bold text-sm uppercase tracking-wider text-gray-500 mb-2">SOAP Notes</h4>

                <x-mary-textarea label="Subjective" wire:model="subjective"
                    placeholder="Chief complaint, history of present illness, symptoms reported by patient..."
                    rows="3" />

                <x-mary-textarea label="Objective" wire:model="objective"
                    placeholder="Observable findings during teleconsult, vitals if reported..."
                    rows="3" />

                <x-mary-textarea label="Assessment" wire:model="assessment"
                    placeholder="Clinical impression, diagnosis..."
                    rows="3" />

                <x-mary-textarea label="Plan" wire:model="plan"
                    placeholder="Treatment plan, medications, follow-up instructions..."
                    rows="3" />

                <x-mary-textarea label="Additional Notes" wire:model="additionalNotes"
                    placeholder="Any other relevant observations..."
                    rows="2" />

                @if ($noteSaved)
                    <div class="alert alert-success text-sm py-2">
                        <x-mary-icon name="o-check-circle" class="w-4 h-4" />
                        Notes saved successfully.
                    </div>
                @endif
            </div>

            {{-- Quick Actions --}}
            <div class="p-4 border-t border-base-300 space-y-2">
                <h4 class="font-bold text-sm uppercase tracking-wider text-gray-500 mb-2">Quick Actions</h4>
                <div class="grid grid-cols-1 gap-2">
                    <x-mary-button label="Schedule Follow-up" icon="o-calendar-days"
                        class="btn-sm btn-outline w-full justify-start"
                        wire:click="$set('showFollowUpModal', true)" />
                </div>
            </div>
        </div>
    </div>

    {{-- Follow-up Modal --}}
    <x-mary-modal wire:model="showFollowUpModal" title="Schedule Follow-up Appointment">
        <x-mary-form wire:submit="scheduleFollowUp">
            <x-mary-input label="Follow-up Date" type="date" wire:model="followUpDate" />
            <x-mary-textarea label="Remarks" wire:model="followUpRemarks"
                placeholder="Notes for the follow-up appointment..." rows="3" />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('showFollowUpModal', false)" />
                <x-mary-button label="Schedule" type="submit" class="btn-primary" spinner="scheduleFollowUp" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Webex SDK via CDN (only loaded on this page) --}}
    @push('scripts')
        <script src="https://unpkg.com/webex@latest/umd/webex.min.js"></script>
    @endpush
</div>
