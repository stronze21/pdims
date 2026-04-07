<div>
    {{-- Top Bar --}}
    <div class="flex items-center justify-between bg-base-200 px-4 py-2 rounded-t-lg">
        <div class="flex items-center gap-3">
            <x-mary-button icon="o-arrow-left" class="btn-sm btn-ghost"
                link="{{ request()->routeIs('portal.teleconsult.*') ? route('portal.teleconsult.lobby') : route('teleconsult.lobby') }}" />
            <div>
                <h3 class="font-bold text-lg">Teleconsult Room</h3>
                <p class="text-sm text-gray-500">
                    Patient: {{ $session->patient?->getFullnameAttribute() ?? 'N/A' }}
                    &middot;
                    <span class="badge badge-sm {{ $session->status === 'in_progress' ? 'badge-success' : 'badge-warning' }}">
                        {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                    </span>
                    &middot;
                    @php
                        $platformLabel = match($platform) {
                            'jitsi' => ['badge-info', 'Jitsi Meet'],
                            'livekit' => ['badge-secondary', 'LiveKit'],
                            default => ['badge-accent', 'Webex'],
                        };
                    @endphp
                    <span class="badge badge-sm {{ $platformLabel[0] }}">
                        {{ $platformLabel[1] }}
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
        {{-- Video / Meeting Panel --}}
        <div class="lg:w-3/5 w-full bg-black rounded-bl-lg flex items-center justify-center relative">

            @if ($platform === 'jitsi')
                {{-- ===== JITSI MEET EMBEDDED ===== --}}
                @if ($jitsiRoomName)
                    @php
                        // Build the full Jitsi URL with JWT and config for the popup/new-tab fallback
                        $jitsiFullUrl = $jitsiMeetingLink;
                        if ($jitsiJwt) {
                            $jitsiFullUrl .= '?jwt=' . $jitsiJwt;
                        }
                        $jitsiFullUrl .= '#config.disableDeepLinking=true&config.prejoinPageEnabled=false&config.startWithAudioMuted=true&userInfo.displayName=%22' . urlencode(auth()->user()->name ?? 'Doctor') . '%22';
                    @endphp
                    <div class="w-full h-full flex flex-col"
                        wire:ignore
                        x-data="jitsiMeet()"
                        x-init="init()">
                        {{-- Jitsi iframe container (used when page is HTTPS) --}}
                        <div id="jitsi-container" x-ref="container" class="flex-1 w-full" style="min-height: 400px;"></div>

                        {{-- Popup/new-tab UI (shown when page is HTTP — WebRTC requires secure context) --}}
                        <div id="jitsi-popup-mode" x-ref="popupMode" class="hidden absolute inset-0 flex items-center justify-center">
                            <div class="text-white text-center space-y-4 max-w-md px-4">
                                <x-mary-icon name="o-video-camera" class="w-20 h-20 mx-auto opacity-70" />
                                <p class="text-xl font-semibold">Jitsi Meeting Ready</p>
                                <p class="text-sm text-gray-400">
                                    The meeting opens in a separate window because this page is served over HTTP.
                                    WebRTC requires a secure (HTTPS) context for camera and microphone access.
                                </p>
                                <a href="{{ $jitsiFullUrl }}" target="_blank" rel="noopener"
                                    class="btn btn-primary btn-lg gap-2 w-full"
                                    id="jitsi-open-btn">
                                    <x-mary-icon name="o-arrow-top-right-on-square" class="w-5 h-5" />
                                    Open Jitsi Meeting
                                </a>
                                <p class="text-xs text-gray-500">
                                    The meeting will open in a new tab with full video and audio support.
                                </p>
                            </div>
                        </div>

                        {{-- Error message (shown if API fails to load entirely) --}}
                        <div id="jitsi-load-error" x-ref="loadError" class="hidden absolute inset-0 flex items-center justify-center">
                            <div class="text-white text-center space-y-4 max-w-md px-4">
                                <x-mary-icon name="o-exclamation-triangle" class="w-16 h-16 mx-auto text-yellow-400" />
                                <p class="text-lg font-semibold">Unable to load Jitsi Meet</p>
                                <p class="text-sm text-gray-400">
                                    The embedded meeting could not be loaded. This is usually caused by an untrusted SSL certificate on the Jitsi server.
                                </p>
                                <a href="{{ $jitsiFullUrl }}" target="_blank" rel="noopener"
                                    class="btn btn-primary btn-md gap-2 w-full">
                                    <x-mary-icon name="o-arrow-top-right-on-square" class="w-5 h-5" />
                                    Open Meeting in New Tab
                                </a>
                            </div>
                        </div>

                        {{-- Fallback link --}}
                        <div class="text-center py-2 bg-gray-900">
                            <a href="{{ $jitsiFullUrl }}" target="_blank" rel="noopener"
                                class="text-xs text-gray-400 hover:text-white transition">
                                Open in new tab &rarr;
                            </a>
                        </div>
                    </div>

                    @push('scripts')
                    <script>
                        window.__jitsiApiLoaded = false;
                    </script>
                    <script src="https://{{ $jitsiServerDomain }}/external_api.js"
                        onload="window.__jitsiApiLoaded = true;"
                        onerror="
                            window.__jitsiApiLoaded = false;
                            console.error('Failed to load Jitsi external_api.js — possible SSL certificate issue with {{ $jitsiServerDomain }}');
                            var errEl = document.getElementById('jitsi-load-error');
                            if (errEl) { errEl.classList.remove('hidden'); }
                            var container = document.getElementById('jitsi-container');
                            if (container) { container.style.display = 'none'; }
                        "></script>
                    <script>
                        function jitsiMeet() {
                            return {
                                api: null,
                                init() {
                                    var container = this.$refs.container;
                                    var popup = this.$refs.popupMode;
                                    var errEl = this.$refs.loadError;

                                    if (!container || container.dataset.jitsiMounted === 'true') {
                                        return;
                                    }
                                    // WebRTC requires a secure context (HTTPS). If the parent page
                                    // is HTTP, embedding Jitsi in an iframe won't work — the browser
                                    // blocks navigator.mediaDevices. Fall back to opening in a new tab.
                                    if (!window.isSecureContext) {
                                        console.warn('Page is not a secure context (HTTP). Jitsi will open in a new tab for WebRTC support.');
                                        if (container) { container.style.display = 'none'; }
                                        if (popup) { popup.classList.remove('hidden'); }
                                        return;
                                    }

                                    if (typeof JitsiMeetExternalAPI === 'undefined') {
                                        console.error('Jitsi Meet API not loaded');
                                        if (errEl) { errEl.classList.remove('hidden'); }
                                        if (container) { container.style.display = 'none'; }
                                        return;
                                    }
                                    container.innerHTML = '';
                                    var apiOptions = {
                                        roomName: '{{ $jitsiRoomName }}',
                                        parentNode: container,
                                        width: '100%',
                                        height: '100%',
                                        userInfo: {
                                            displayName: '{{ auth()->user()->name ?? "Doctor" }}'
                                        },
                                        configOverrides: {
                                            startWithAudioMuted: true,
                                            startWithVideoMuted: false,
                                            prejoinPageEnabled: false,
                                            disableDeepLinking: true,
                                            deeplinking: { disabled: true },
                                        },
                                        interfaceConfigOverrides: {
                                            TOOLBAR_BUTTONS: [
                                                'microphone', 'camera', 'desktop', 'chat',
                                                'raisehand', 'participants-pane', 'tileview',
                                                'select-background', 'fullscreen', 'hangup'
                                            ],
                                            SHOW_JITSI_WATERMARK: false,
                                            SHOW_WATERMARK_FOR_GUESTS: false,
                                            MOBILE_APP_PROMO: false,
                                        }
                                    };
                                    @if ($jitsiJwt)
                                        apiOptions.jwt = '{{ $jitsiJwt }}';
                                    @endif
                                    this.api = new JitsiMeetExternalAPI('{{ $jitsiServerDomain }}', apiOptions);
                                    container.dataset.jitsiMounted = 'true';
                                },
                                destroy() {
                                    if (this.api && typeof this.api.dispose === 'function') {
                                        this.api.dispose();
                                    }

                                    var container = this.$refs.container;
                                    if (container) {
                                        container.innerHTML = '';
                                        delete container.dataset.jitsiMounted;
                                    }
                                }
                            }
                        }
                    </script>
                    @endpush
                @else
                    <div class="text-white text-center">
                        <x-mary-icon name="o-video-camera" class="w-16 h-16 mx-auto mb-4 opacity-50" />
                        <p class="text-lg">No Jitsi meeting configured</p>
                        <p class="text-sm text-gray-400">Meeting will appear here once the session is created.</p>
                    </div>
                @endif

            @elseif ($platform === 'livekit')
                {{-- ===== LIVEKIT EMBEDDED ===== --}}
                @if ($livekitRoomName && $livekitToken)
                    <div class="w-full h-full flex flex-col" x-data="livekitRoom()" x-init="init()">
                        <div id="livekit-container" class="flex-1 w-full relative" style="min-height: 400px;">
                            {{-- Remote video --}}
                            <div id="livekit-remote" class="absolute inset-0 flex items-center justify-center">
                                <div id="livekit-waiting" class="text-white text-center">
                                    <x-mary-icon name="o-signal" class="w-16 h-16 mx-auto mb-4 opacity-50 animate-pulse" />
                                    <p class="text-lg">Waiting for patient to join...</p>
                                </div>
                            </div>
                            {{-- Local video (picture-in-picture) --}}
                            <div id="livekit-local" class="absolute bottom-4 right-4 w-48 h-36 rounded-lg overflow-hidden border-2 border-gray-600 bg-gray-900 z-10"></div>
                        </div>

                        {{-- Controls --}}
                        <div class="flex items-center justify-center gap-3 py-3 bg-gray-900">
                            <button x-on:click="toggleAudio()" class="btn btn-circle btn-sm"
                                :class="audioMuted ? 'btn-error' : 'btn-ghost text-white'">
                                <x-mary-icon x-show="!audioMuted" name="o-microphone" class="w-5 h-5" />
                                <x-mary-icon x-show="audioMuted" name="o-microphone" class="w-5 h-5 line-through" />
                            </button>
                            <button x-on:click="toggleVideo()" class="btn btn-circle btn-sm"
                                :class="videoMuted ? 'btn-error' : 'btn-ghost text-white'">
                                <x-mary-icon x-show="!videoMuted" name="o-video-camera" class="w-5 h-5" />
                                <x-mary-icon x-show="videoMuted" name="o-video-camera" class="w-5 h-5 line-through" />
                            </button>
                            <button x-on:click="toggleScreenShare()" class="btn btn-circle btn-sm btn-ghost text-white">
                                <x-mary-icon name="o-computer-desktop" class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    @push('scripts')
                    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.js"></script>
                    <script>
                        function livekitRoom() {
                            return {
                                room: null,
                                audioMuted: false,
                                videoMuted: false,
                                async init() {
                                    if (typeof LivekitClient === 'undefined') {
                                        console.error('LiveKit client SDK not loaded');
                                        return;
                                    }
                                    try {
                                        this.room = new LivekitClient.Room({
                                            adaptiveStream: true,
                                            dynacast: true,
                                        });

                                        // Handle remote tracks
                                        this.room.on(LivekitClient.RoomEvent.TrackSubscribed, (track, pub, participant) => {
                                            const el = track.attach();
                                            el.style.width = '100%';
                                            el.style.height = '100%';
                                            el.style.objectFit = 'cover';
                                            const container = document.getElementById('livekit-remote');
                                            const waiting = document.getElementById('livekit-waiting');
                                            if (waiting) waiting.style.display = 'none';
                                            container.appendChild(el);
                                        });

                                        this.room.on(LivekitClient.RoomEvent.TrackUnsubscribed, (track) => {
                                            track.detach().forEach(el => el.remove());
                                        });

                                        this.room.on(LivekitClient.RoomEvent.ParticipantDisconnected, () => {
                                            const waiting = document.getElementById('livekit-waiting');
                                            if (waiting) waiting.style.display = 'block';
                                        });

                                        // Connect
                                        await this.room.connect('{{ $livekitWsUrl }}', '{{ $livekitToken }}');

                                        // Publish local tracks
                                        await this.room.localParticipant.enableCameraAndMicrophone();

                                        // Attach local video
                                        const localContainer = document.getElementById('livekit-local');
                                        this.room.localParticipant.videoTrackPublications.forEach((pub) => {
                                            if (pub.track) {
                                                const el = pub.track.attach();
                                                el.style.width = '100%';
                                                el.style.height = '100%';
                                                el.style.objectFit = 'cover';
                                                el.style.transform = 'scaleX(-1)';
                                                localContainer.appendChild(el);
                                            }
                                        });
                                    } catch (e) {
                                        console.error('LiveKit connection error:', e);
                                    }
                                },
                                toggleAudio() {
                                    if (!this.room) return;
                                    this.audioMuted = !this.audioMuted;
                                    this.room.localParticipant.setMicrophoneEnabled(!this.audioMuted);
                                },
                                toggleVideo() {
                                    if (!this.room) return;
                                    this.videoMuted = !this.videoMuted;
                                    this.room.localParticipant.setCameraEnabled(!this.videoMuted);
                                },
                                async toggleScreenShare() {
                                    if (!this.room) return;
                                    const enabled = this.room.localParticipant.isScreenShareEnabled;
                                    await this.room.localParticipant.setScreenShareEnabled(!enabled);
                                }
                            }
                        }
                    </script>
                    @endpush
                @else
                    <div class="text-white text-center">
                        <x-mary-icon name="o-signal" class="w-16 h-16 mx-auto mb-4 opacity-50" />
                        <p class="text-lg">No LiveKit room configured</p>
                        <p class="text-sm text-gray-400">Room will appear here once the session is created.</p>
                    </div>
                @endif

            @else
                {{-- ===== WEBEX MEETING ===== --}}
                @if ($meetingLink)
                    <div class="text-white text-center space-y-4">
                        <x-mary-icon name="o-video-camera" class="w-20 h-20 mx-auto opacity-70" />
                        <p class="text-xl font-semibold">Webex Meeting Ready</p>
                        <p class="text-sm text-gray-400">Click below to join. Host key will be copied to your clipboard automatically.</p>

                        {{-- Join button: copies host key to clipboard then opens Webex --}}
                        <a href="{{ $meetingLink }}" target="_blank" rel="noopener"
                            class="btn btn-primary btn-lg gap-2"
                            @if ($hostKey)
                                x-data
                                x-on:click="
                                    navigator.clipboard.writeText('{{ $hostKey }}');
                                    $dispatch('notify', { message: 'Host key copied to clipboard!', type: 'info' });
                                "
                            @endif
                        >
                            <x-mary-icon name="o-video-camera" class="w-5 h-5" />
                            Join Webex Meeting
                        </a>

                        {{-- Claim Host button: API-based promotion --}}
                        @if ($session->status === 'in_progress' && $session->webex_meeting_id)
                            <div class="mt-2">
                                @if ($claimHostStatus === 'success')
                                    <div class="badge badge-success gap-1 py-3">
                                        <x-mary-icon name="o-check-circle" class="w-4 h-4" />
                                        Host role claimed
                                    </div>
                                @else
                                    <x-mary-button
                                        label="Claim Host Role"
                                        icon="o-shield-check"
                                        class="btn-sm btn-warning"
                                        wire:click="claimHost"
                                        spinner="claimHost"
                                    />
                                    <p class="text-gray-500 text-[10px] mt-1">Click after joining Webex to get host controls</p>
                                @endif
                            </div>
                        @endif

                        <div class="text-xs text-gray-500 mt-4 space-y-1">
                            @if ($session->webex_meeting_number)
                                <p>Meeting Number: {{ $session->webex_meeting_number }}</p>
                            @endif
                            @if ($sipAddress)
                                <p>SIP: {{ $sipAddress }}</p>
                            @endif
                            @if ($hostKey)
                                <div class="mt-3 p-2 bg-gray-800 rounded-lg inline-block" x-data>
                                    <p class="text-yellow-400 font-semibold">
                                        Host Key: {{ $hostKey }}
                                        <button class="btn btn-xs btn-ghost text-gray-400 ml-1"
                                            x-on:click="navigator.clipboard.writeText('{{ $hostKey }}')">
                                            <x-mary-icon name="o-clipboard-document" class="w-3 h-3" />
                                        </button>
                                    </p>
                                    <p class="text-gray-500 text-[10px]">Fallback: In Webex, go to Participants > ... > Claim Host Role</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-white text-center">
                        <x-mary-icon name="o-video-camera" class="w-16 h-16 mx-auto mb-4 opacity-50" />
                        <p class="text-lg">No Webex meeting configured</p>
                        <p class="text-sm text-gray-400">Meeting will appear here once the session is started.</p>
                    </div>
                @endif
            @endif
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
</div>
