<?php

namespace App\Livewire\Teleconsult;

use App\Events\Portal\TeleconsultEnded;
use App\Events\Portal\TeleconsultStarted;
use App\Models\Portal\TeleconsultNote;
use App\Models\Portal\TeleconsultSession;
use App\Services\PushNotificationService;
use App\Services\JitsiService;
use App\Services\LiveKitService;
use App\Services\WebexService;
use Livewire\Component;
use Mary\Traits\Toast;

class TeleconsultRoom extends Component
{
    use Toast;

    public $sessionId;
    public $session;

    // SOAP Notes
    public $subjective = '';
    public $objective = '';
    public $assessment = '';
    public $plan = '';
    public $additionalNotes = '';

    // Platform info
    public $platform = 'webex';

    // Webex connection info
    public $hostKey = '';
    public $meetingLink = '';
    public $sipAddress = '';

    // Jitsi connection info
    public $jitsiRoomName = '';
    public $jitsiMeetingLink = '';
    public $jitsiServerDomain = '';
    public $jitsiJwt = '';

    // LiveKit connection info
    public $livekitRoomName = '';
    public $livekitToken = '';
    public $livekitWsUrl = '';

    // UI state
    public $showPrescriptionModal = false;
    public $showLabOrderModal = false;
    public $showFollowUpModal = false;
    public $noteSaved = false;
    public $claimHostStatus = ''; // '', 'claiming', 'success', 'failed'

    // Follow-up form
    public $followUpDate = '';
    public $followUpRemarks = '';

    public function mount($sessionId)
    {
        $this->sessionId = $sessionId;
        $session = TeleconsultSession::with(['patient', 'note'])->findOrFail($sessionId);
        $this->session = $session;
        $this->platform = $session->platform ?? 'webex';

        // Load platform-specific connection info
        if ($this->platform === 'jitsi') {
            $this->jitsiRoomName = $session->jitsi_room_name ?? '';
            $this->jitsiMeetingLink = $session->jitsi_meeting_link ?? '';
            $jitsi = new JitsiService();
            $this->jitsiServerDomain = $jitsi->getServerDomain();
            // Generate a JWT token for the doctor (moderator) if JWT auth is configured
            $user = auth()->user();
            $this->jitsiJwt = $jitsi->generateToken(
                $this->jitsiRoomName,
                [
                    'name' => $user->name ?? 'Doctor',
                    'email' => $user->email ?? '',
                    'role' => 'moderator',
                ]
            ) ?? '';
        } elseif ($this->platform === 'livekit') {
            $this->livekitRoomName = $session->livekit_room_name ?? '';
            $livekit = new LiveKitService();
            $this->livekitWsUrl = $livekit->getWsUrl();
            // Generate a fresh token for the doctor each time they enter the room
            $this->livekitToken = $livekit->generateParticipantToken(
                $this->livekitRoomName,
                auth()->user()->name ?? 'Doctor',
                true
            ) ?? '';
        } else {
            $this->meetingLink = $session->webex_meeting_link ?? '';
            $this->sipAddress = $session->webex_sip_address ?? '';
            $this->hostKey = $session->webex_host_key ?? '';
        }

        // Load existing notes if any
        if ($session->note) {
            $this->subjective = $session->note->subjective ?? '';
            $this->objective = $session->note->objective ?? '';
            $this->assessment = $session->note->assessment ?? '';
            $this->plan = $session->note->plan ?? '';
            $this->additionalNotes = $session->note->additional_notes ?? '';
        }
    }

    public function claimHost()
    {
        if ($this->platform !== 'webex' || !$this->session->webex_meeting_id) {
            $this->error('Host claiming is only available for Webex meetings.');

            return;
        }

        $this->claimHostStatus = 'claiming';

        $webex = new WebexService();
        $result = $webex->claimHost($this->session->webex_meeting_id);

        if ($result && ($result['success'] ?? false)) {
            $this->claimHostStatus = 'success';
            $this->success('Host role claimed! You now have full meeting controls in Webex.');
        } else {
            $this->claimHostStatus = 'failed';
            $this->warning('Could not auto-claim host. Use the host key in Webex: Participants > Claim Host Role.');
        }
    }

    public function startConsult()
    {
        $this->session->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
        $this->session->refresh();

        // Notify patient via broadcast
        broadcast(new TeleconsultStarted($this->session));

        // Push notification to patient
        try {
            $pushService = app(PushNotificationService::class);
            $pushService->sendToUser(
                $this->session->patient_id,
                'Teleconsult Ready',
                'Dr. ' . $this->session->doctor_name . ' is ready for your teleconsult. Tap to join.',
                ['type' => 'teleconsult_started', 'session_id' => $this->session->id]
            );
        } catch (\Exception $e) {
            // Push notification is non-critical
        }

        $this->success('Teleconsult started. Waiting for patient to join.');
    }

    public function saveNotes()
    {
        $user = auth()->user();

        TeleconsultNote::updateOrCreate(
            ['teleconsult_session_id' => $this->sessionId],
            [
                'doctor_employee_id' => $user->employeeid ?? $user->id,
                'subjective' => $this->subjective,
                'objective' => $this->objective,
                'assessment' => $this->assessment,
                'plan' => $this->plan,
                'additional_notes' => $this->additionalNotes,
            ]
        );

        $this->noteSaved = true;
        $this->success('Notes saved.');
    }

    public function endConsult()
    {
        $this->saveNotes();

        $startedAt = $this->session->started_at;
        $endedAt = now();
        $duration = $startedAt ? $startedAt->diffInMinutes($endedAt) : null;

        $this->session->update([
            'status' => 'completed',
            'ended_at' => $endedAt,
            'duration_minutes' => $duration,
        ]);
        $this->session->refresh();

        // Clean up platform meeting
        if ($this->session->platform === 'webex' && $this->session->webex_meeting_id) {
            $webex = new WebexService();
            $webex->deleteMeeting($this->session->webex_meeting_id);
        } elseif ($this->session->platform === 'livekit' && $this->session->livekit_room_name) {
            $livekit = new LiveKitService();
            $livekit->deleteRoom($this->session->livekit_room_name);
        }

        // Notify patient
        broadcast(new TeleconsultEnded($this->session));

        return redirect()->route('teleconsult.summary', ['sessionId' => $this->sessionId]);
    }

    public function markNoShow()
    {
        $this->session->update([
            'status' => 'no_show',
            'ended_at' => now(),
        ]);

        if ($this->session->platform === 'webex' && $this->session->webex_meeting_id) {
            $webex = new WebexService();
            $webex->deleteMeeting($this->session->webex_meeting_id);
        } elseif ($this->session->platform === 'livekit' && $this->session->livekit_room_name) {
            $livekit = new LiveKitService();
            $livekit->deleteRoom($this->session->livekit_room_name);
        }

        $this->success('Session marked as no-show.');

        return redirect()->route('teleconsult.lobby');
    }

    public function scheduleFollowUp()
    {
        $this->validate([
            'followUpDate' => 'required|date|after:today',
            'followUpRemarks' => 'nullable|string|max:255',
        ]);

        // Create a new appointment for the follow-up
        \Illuminate\Support\Facades\DB::connection('portal')->table('appointments')->insert([
            'patient_id' => $this->session->patient_id,
            'appointment_date' => $this->followUpDate,
            'appointment_type' => $this->session->appointment_id
                ? \Illuminate\Support\Facades\DB::connection('portal')->table('appointments')
                    ->where('id', $this->session->appointment_id)->value('appointment_type')
                : 1,
            'attending_clinic' => \Illuminate\Support\Facades\DB::connection('portal')->table('appointments')
                ->where('id', $this->session->appointment_id)->value('attending_clinic') ?? '',
            'remarks' => 'Follow-up from teleconsult session #' . $this->sessionId .
                ($this->followUpRemarks ? '. ' . $this->followUpRemarks : ''),
            'ref_no' => 'PA-' . now()->format('Ymd') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->showFollowUpModal = false;
        $this->reset(['followUpDate', 'followUpRemarks']);
        $this->success('Follow-up appointment scheduled.');
    }

    public function render()
    {
        return view('livewire.teleconsult.teleconsult-room')
            ->layout('layouts.app');
    }
}
