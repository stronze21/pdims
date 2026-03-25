<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JitsiService
{
    private string $serverUrl;
    private ?string $jwtSecret;
    private ?string $jwtAppId;

    public function __construct()
    {
        $this->serverUrl = rtrim(config('services.jitsi.server_url'), '/');
        $this->jwtSecret = config('services.jitsi.jwt_secret');
        $this->jwtAppId = config('services.jitsi.jwt_app_id');
    }

    /**
     * Create a Jitsi meeting room.
     *
     * Jitsi rooms are created on-the-fly when the first participant joins.
     * This method generates a unique room name and returns the meeting link.
     */
    public function createMeeting(array $params): ?array
    {
        try {
            $roomName = $this->generateRoomName($params['title'] ?? 'Teleconsult');
            $meetingLink = "{$this->serverUrl}/{$roomName}";

            Log::info('Jitsi meeting room created', [
                'room_name' => $roomName,
                'link' => $meetingLink,
            ]);

            return [
                'room_name' => $roomName,
                'meeting_link' => $meetingLink,
            ];
        } catch (\Exception $e) {
            Log::error('Jitsi service error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Generate a JWT token for authenticated Jitsi meetings.
     * Only works if JWT auth is configured on the Jitsi server.
     */
    public function generateToken(string $roomName, array $user, int $expMinutes = 60): ?string
    {
        if (!$this->jwtSecret || !$this->jwtAppId) {
            return null;
        }

        try {
            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'HS256',
                'typ' => 'JWT',
            ]));

            $payload = $this->base64UrlEncode(json_encode([
                'iss' => $this->jwtAppId,
                'sub' => parse_url($this->serverUrl, PHP_URL_HOST),
                'aud' => $this->jwtAppId,
                'room' => $roomName,
                'exp' => time() + ($expMinutes * 60),
                'context' => [
                    'user' => [
                        'name' => $user['name'] ?? 'Participant',
                        'email' => $user['email'] ?? '',
                        'affiliation' => $user['role'] ?? 'member',
                    ],
                ],
                'moderator' => ($user['role'] ?? '') === 'moderator',
            ]));

            $signature = $this->base64UrlEncode(
                hash_hmac('sha256', "{$header}.{$payload}", $this->jwtSecret, true)
            );

            return "{$header}.{$payload}.{$signature}";
        } catch (\Exception $e) {
            Log::error('Jitsi JWT generation error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Get the Jitsi server URL for embedding via IFrame API.
     */
    public function getServerDomain(): string
    {
        return parse_url($this->serverUrl, PHP_URL_HOST)
            . (parse_url($this->serverUrl, PHP_URL_PORT) ? ':' . parse_url($this->serverUrl, PHP_URL_PORT) : '');
    }

    /**
     * Generate a unique, URL-safe room name.
     */
    private function generateRoomName(string $title): string
    {
        $slug = Str::slug($title, '-');
        $unique = Str::random(8);

        return "teleconsult-{$slug}-{$unique}";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
