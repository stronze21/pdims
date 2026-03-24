<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\TeleconsultSession;
use App\Services\WebexService;
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
                'scheduled_at' => $session->scheduled_at?->toISOString(),
                'started_at' => $session->started_at?->toISOString(),
                'ended_at' => $session->ended_at?->toISOString(),
                'duration_minutes' => $session->duration_minutes,
                'webex_meeting_link' => $session->webex_meeting_link,
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
     * Get guest token for patient to join the teleconsult via Webex SDK.
     */
    public function guestToken(Request $request, $sessionId)
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

            // Return cached guest token if still valid
            if ($session->webex_guest_token) {
                return response()->json([
                    'token' => $session->webex_guest_token,
                    'meeting_link' => $session->webex_meeting_link,
                    'sip_address' => $session->webex_sip_address,
                ]);
            }

            // Generate new guest token via middleware
            $webex = new WebexService();
            $displayName = $patient->getFullnameAttribute();
            $token = $webex->generateGuestToken($displayName, (string) $session->id);

            if (!$token) {
                return response()->json(['message' => 'Failed to generate access token.'], 500);
            }

            $session->update(['webex_guest_token' => $token]);

            return response()->json([
                'token' => $token,
                'meeting_link' => $session->webex_meeting_link,
                'sip_address' => $session->webex_sip_address,
            ]);
        } catch (\Exception $e) {
            Log::error('[Teleconsult] Error generating guest token', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to generate access token.'], 500);
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
