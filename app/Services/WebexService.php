<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebexService
{
    private string $middlewareUrl;

    public function __construct()
    {
        $this->middlewareUrl = config('services.webex.middleware_url');
    }

    /**
     * Create a Webex meeting for a teleconsult session.
     */
    public function createMeeting(array $params): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Api-Key' => config('services.webex.middleware_api_key'),
                ])
                ->post("{$this->middlewareUrl}/api/meetings", [
                    'title' => $params['title'] ?? 'Teleconsult Session',
                    'start' => $params['start'],
                    'end' => $params['end'],
                    'host_email' => $params['host_email'] ?? null,
                    'attendee_email' => $params['attendee_email'] ?? null,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Webex meeting created', [
                    'meeting_id' => $data['id'] ?? null,
                ]);

                return $data;
            }

            Log::error('Webex meeting creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Webex service error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Delete/end a Webex meeting.
     */
    public function deleteMeeting(string $meetingId): bool
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Api-Key' => config('services.webex.middleware_api_key'),
                ])
                ->delete("{$this->middlewareUrl}/api/meetings/{$meetingId}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Webex meeting deletion error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get meeting details including join info.
     */
    public function getMeeting(string $meetingId): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Api-Key' => config('services.webex.middleware_api_key'),
                ])
                ->get("{$this->middlewareUrl}/api/meetings/{$meetingId}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Webex get meeting error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Promote the first non-host participant to co-host via the middleware.
     */
    public function claimHost(string $meetingId): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Api-Key' => config('services.webex.middleware_api_key'),
                ])
                ->post("{$this->middlewareUrl}/api/meetings/{$meetingId}/claim-host");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Webex claim host failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Webex claim host error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Get the host access token for the doctor to join with full controls.
     */
    public function getHostToken(string $meetingId): ?string
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Api-Key' => config('services.webex.middleware_api_key'),
                ])
                ->post("{$this->middlewareUrl}/api/host-token", [
                    'meeting_id' => $meetingId,
                ]);

            if ($response->successful()) {
                return $response->json('token');
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Webex host token error: ' . $e->getMessage());

            return null;
        }
    }
}
