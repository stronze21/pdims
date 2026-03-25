/**
 * Webex Teleconsult Integration
 *
 * Initializes the Webex Browser SDK for the teleconsult room.
 * The Webex SDK is loaded via CDN in the teleconsult-room blade view.
 * This script handles:
 * - Connecting to Webex with host/guest tokens
 * - Joining meetings
 * - Managing audio/video streams
 * - Rendering local/remote video
 */

window.WebexTeleconsult = {
    webex: null,
    meeting: null,
    localStream: null,

    /**
     * Initialize the Webex SDK and join a meeting.
     * Called from the teleconsult room blade view.
     */
    async init() {
        const container = document.getElementById('webex-meeting-container');
        if (!container) return;

        const hostToken = container.dataset.hostToken;
        const meetingLink = container.dataset.meetingLink;
        const sipAddress = container.dataset.sipAddress;

        if (!hostToken || (!meetingLink && !sipAddress)) {
            console.log('[Webex] No token or meeting link available');
            return;
        }

        try {
            // Webex SDK is loaded via CDN (<script> tag in teleconsult-room blade)
            if (typeof window.Webex === 'undefined') {
                throw new Error('Webex SDK not loaded. Ensure the CDN script is included.');
            }

            this.webex = window.Webex.init({
                credentials: {
                    access_token: hostToken,
                },
            });

            await this.webex.meetings.register();

            console.log('[Webex] SDK initialized, creating meeting...');

            // Create meeting using SIP address or meeting link
            const destination = sipAddress || meetingLink;
            this.meeting = await this.webex.meetings.create(destination);

            // Set up media event listeners
            this.setupMediaListeners();

            // Join the meeting
            const mediaOptions = await this.getMediaOptions();
            await this.meeting.join({ mediaOptions });

            console.log('[Webex] Joined meeting successfully');

            // Hide loading, show video
            const loading = document.getElementById('webex-loading');
            if (loading) loading.classList.add('hidden');

        } catch (error) {
            console.error('[Webex] Error:', error);
            const loading = document.getElementById('webex-loading');
            if (loading) {
                loading.innerHTML = `
                    <div class="text-red-400 text-center">
                        <p class="text-lg mb-2">Failed to connect to Webex</p>
                        <p class="text-sm">${error.message || 'Unknown error'}</p>
                        <button onclick="WebexTeleconsult.init()" class="btn btn-sm btn-outline mt-4">
                            Retry
                        </button>
                    </div>
                `;
            }
        }
    },

    /**
     * Get media options for joining (request camera + microphone).
     */
    async getMediaOptions() {
        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });

            return {
                receiveVideo: true,
                receiveAudio: true,
                receiveShare: true,
                sendVideo: true,
                sendAudio: true,
                localVideo: this.localStream.getVideoTracks()[0] ? true : false,
                localAudio: this.localStream.getAudioTracks()[0] ? true : false,
            };
        } catch (error) {
            console.warn('[Webex] Media access denied, joining audio-only:', error);
            return {
                receiveVideo: true,
                receiveAudio: true,
                receiveShare: true,
                sendVideo: false,
                sendAudio: true,
            };
        }
    },

    /**
     * Set up listeners for remote and local media streams.
     */
    setupMediaListeners() {
        if (!this.meeting) return;

        this.meeting.on('media:ready', (media) => {
            if (media.type === 'remoteVideo') {
                const remoteVideo = document.getElementById('remote-video');
                if (remoteVideo) {
                    remoteVideo.classList.remove('hidden');
                    const videoEl = document.createElement('video');
                    videoEl.srcObject = media.stream;
                    videoEl.autoplay = true;
                    videoEl.playsInline = true;
                    videoEl.style.cssText = 'width:100%;height:100%;object-fit:contain;';
                    remoteVideo.innerHTML = '';
                    remoteVideo.appendChild(videoEl);
                }
            }

            if (media.type === 'localVideo') {
                const localVideo = document.getElementById('local-video');
                if (localVideo) {
                    localVideo.classList.remove('hidden');
                    const videoEl = document.createElement('video');
                    videoEl.srcObject = media.stream;
                    videoEl.autoplay = true;
                    videoEl.playsInline = true;
                    videoEl.muted = true;
                    videoEl.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                    localVideo.innerHTML = '';
                    localVideo.appendChild(videoEl);
                }
            }

            if (media.type === 'remoteAudio') {
                const audioEl = document.createElement('audio');
                audioEl.srcObject = media.stream;
                audioEl.autoplay = true;
                document.body.appendChild(audioEl);
            }
        });

        this.meeting.on('media:stopped', (media) => {
            if (media.type === 'remoteVideo') {
                const remoteVideo = document.getElementById('remote-video');
                if (remoteVideo) remoteVideo.innerHTML = '';
            }
            if (media.type === 'localVideo') {
                const localVideo = document.getElementById('local-video');
                if (localVideo) localVideo.innerHTML = '';
            }
        });

        this.meeting.on('meeting:stateChanged', (state) => {
            console.log('[Webex] Meeting state changed:', state);
        });
    },

    /**
     * Toggle local video on/off.
     */
    async toggleVideo() {
        if (!this.meeting) return;
        try {
            if (this.meeting.isVideoMuted()) {
                await this.meeting.unmuteVideo();
            } else {
                await this.meeting.muteVideo();
            }
        } catch (error) {
            console.error('[Webex] Toggle video error:', error);
        }
    },

    /**
     * Toggle local audio on/off.
     */
    async toggleAudio() {
        if (!this.meeting) return;
        try {
            if (this.meeting.isAudioMuted()) {
                await this.meeting.unmuteAudio();
            } else {
                await this.meeting.muteAudio();
            }
        } catch (error) {
            console.error('[Webex] Toggle audio error:', error);
        }
    },

    /**
     * Leave the meeting and clean up resources.
     */
    async leave() {
        try {
            if (this.meeting) {
                await this.meeting.leave();
            }
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => track.stop());
            }
            if (this.webex) {
                await this.webex.meetings.unregister();
            }
        } catch (error) {
            console.error('[Webex] Leave error:', error);
        }
    },
};

// Auto-initialize when the teleconsult room loads
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('webex-meeting-container')) {
        WebexTeleconsult.init();
    }
});

// Clean up on page navigation
window.addEventListener('beforeunload', () => {
    WebexTeleconsult.leave();
});
