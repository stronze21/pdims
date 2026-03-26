<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\TeleconsultSession;
use App\Services\JitsiService;
use App\Services\LiveKitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortalTeleconsultController extends Controller
{
    /**
     * Get teleconsult session details for an appointment.
     */
    public function show(Request $request, $appointmentId)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        try {
            $session = TeleconsultSession::where('appointment_id', $appointmentId)
                ->where('patient_id', $patient->id)
                ->first();

            if (!$session) {
                return response()->json(['message' => 'Teleconsult session not found.'], 404);
            }

            return response()->json([
                'id' => $session->id,
                'appointment_id' => $session->appointment_id,
                'status' => $session->status,
                'doctor_name' => $session->doctor_name,
                'platform' => $session->platform ?? 'webex',
                'scheduled_at' => $session->scheduled_at?->toISOString(),
                'started_at' => $session->started_at?->toISOString(),
                'ended_at' => $session->ended_at?->toISOString(),
                'duration_minutes' => $session->duration_minutes,
                'meeting_link' => match($session->platform) {
                    'jitsi' => $session->jitsi_meeting_link,
                    'livekit' => null, // LiveKit uses token-based connection, not a URL
                    default => $session->webex_meeting_link,
                },
                'webex_meeting_link' => $session->webex_meeting_link,
                'jitsi_meeting_link' => $session->jitsi_meeting_link,
                'livekit_room_name' => $session->livekit_room_name,
                'is_joinable' => $session->isJoinable(),
                'is_active' => $session->isActive(),
            ]);
        } catch (\Exception $e) {
            Log::error('[Teleconsult] Error fetching session', [
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to load teleconsult session.'], 500);
        }
    }

    /**
     * Get meeting join info for patient to join the teleconsult via browser.
     */
    public function joinInfo(Request $request, $sessionId)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        try {
            $session = TeleconsultSession::where('id', $sessionId)
                ->where('patient_id', $patient->id)
                ->first();

            if (!$session) {
                return response()->json(['message' => 'Teleconsult session not found.'], 404);
            }

            if (!$session->isJoinable()) {
                return response()->json(['message' => 'This teleconsult session is no longer available.'], 422);
            }

            $platform = $session->platform ?? 'webex';

            $data = [
                'platform' => $platform,
                'meeting_link' => match($platform) {
                    'jitsi' => $session->jitsi_meeting_link,
                    'livekit' => null,
                    default => $session->webex_meeting_link,
                },
            ];

            if ($platform === 'jitsi') {
                $data['jitsi_room_name'] = $session->jitsi_room_name;
                $data['jitsi_server_domain'] = config('services.jitsi.server_url')
                    ? parse_url(config('services.jitsi.server_url'), PHP_URL_HOST)
                    : null;

                // Generate JWT for the patient if JWT auth is configured on the Jitsi server
                $jitsi = new JitsiService();
                $patientName = $patient->patlast
                    ? trim(($patient->patlast ?? '') . ', ' . ($patient->patfirst ?? ''))
                    : 'Patient';
                $jwt = $jitsi->generateToken(
                    $session->jitsi_room_name,
                    [
                        'name' => $patientName,
                        'email' => $account->email ?? '',
                        'role' => 'member',
                    ]
                );
                // Build the meeting link with JWT and config overrides for the iframe
                $link = $session->jitsi_meeting_link;
                $link .= $jwt ? '?jwt=' . $jwt : '';
                $link .= '#config.disableDeepLinking=true&config.prejoinPageEnabled=false';
                $data['meeting_link'] = $link;
            } elseif ($platform === 'livekit') {
                $livekit = new LiveKitService();
                $patientName = $patient->patlast
                    ? trim(($patient->patlast ?? '') . ', ' . ($patient->patfirst ?? ''))
                    : 'Patient';
                $data['livekit_room_name'] = $session->livekit_room_name;
                $data['livekit_ws_url'] = $livekit->getWsUrl();
                $data['livekit_token'] = $livekit->generateParticipantToken(
                    $session->livekit_room_name,
                    $patientName,
                    true
                );
            } else {
                $data['sip_address'] = $session->webex_sip_address;
                $data['meeting_number'] = $session->webex_meeting_number;
                $data['password'] = $session->webex_meeting_password;
            }

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('[Teleconsult] Error fetching join info', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to get meeting info.'], 500);
        }
    }

    /**
     * Mark patient as joined the teleconsult.
     */
    public function join(Request $request, $sessionId)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        try {
            $session = TeleconsultSession::where('id', $sessionId)
                ->where('patient_id', $patient->id)
                ->first();

            if (!$session) {
                return response()->json(['message' => 'Teleconsult session not found.'], 404);
            }

            if (!$session->isJoinable()) {
                return response()->json(['message' => 'This teleconsult session is no longer available.'], 422);
            }

            Log::info('[Teleconsult] Patient joined session', [
                'session_id' => $session->id,
                'patient_id' => $patient->id,
            ]);

            return response()->json(['message' => 'Joined successfully.']);
        } catch (\Exception $e) {
            Log::error('[Teleconsult] Error joining session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to join teleconsult.'], 500);
        }
    }

    /**
     * Mark patient as left the teleconsult.
     */
    public function leave(Request $request, $sessionId)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        try {
            $session = TeleconsultSession::where('id', $sessionId)
                ->where('patient_id', $patient->id)
                ->first();

            if (!$session) {
                return response()->json(['message' => 'Teleconsult session not found.'], 404);
            }

            Log::info('[Teleconsult] Patient left session', [
                'session_id' => $session->id,
                'patient_id' => $patient->id,
            ]);

            return response()->json(['message' => 'Left successfully.']);
        } catch (\Exception $e) {
            Log::error('[Teleconsult] Error leaving session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to leave teleconsult.'], 500);
        }
    }

    /**
     * Get upcoming teleconsult sessions for the patient.
     */
    public function upcoming(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        try {
            $sessions = TeleconsultSession::where('patient_id', $patient->id)
                ->whereIn('status', ['scheduled', 'waiting', 'in_progress'])
                ->orderBy('scheduled_at')
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'appointment_id' => $session->appointment_id,
                        'status' => $session->status,
                        'doctor_name' => $session->doctor_name,
                        'platform' => $session->platform ?? 'webex',
                        'scheduled_at' => $session->scheduled_at?->toISOString(),
                        'is_joinable' => $session->isJoinable(),
                        'is_active' => $session->isActive(),
                    ];
                });

            return response()->json($sessions);
        } catch (\Exception $e) {
            Log::error('[Teleconsult] Error fetching upcoming sessions', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([]);
        }
    }
}
