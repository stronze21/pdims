<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LiveKitService
{
    private string $serverUrl;
    private string $wsUrl;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->serverUrl = rtrim(config('services.livekit.server_url'), '/');
        $this->wsUrl = config('services.livekit.ws_url');
        $this->apiKey = config('services.livekit.api_key', '');
        $this->apiSecret = config('services.livekit.api_secret', '');
    }

    /**
     * Create a LiveKit room and return room info.
     *
     * LiveKit rooms are created automatically when the first participant joins,
     * but we can also explicitly create them via the API.
     */
    public function createMeeting(array $params): ?array
    {
        try {
            $roomName = $this->generateRoomName($params['title'] ?? 'Teleconsult');

            // Create room via LiveKit REST API
            $token = $this->generateServiceToken();
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->serverUrl}/twirp/livekit.RoomService/CreateRoom", [
                    'name' => $roomName,
                    'empty_timeout' => 600, // 10 min auto-close when empty
                    'max_participants' => 10,
                ]);

            if (!$response->successful()) {
                Log::warning('LiveKit room creation API failed, room will be created on first join', [
                    'status' => $response->status(),
                ]);
            }

            Log::info('LiveKit meeting room created', [
                'room_name' => $roomName,
            ]);

            return [
                'room_name' => $roomName,
                'server_url' => $this->serverUrl,
                'ws_url' => $this->wsUrl,
            ];
        } catch (\Exception $e) {
            Log::error('LiveKit service error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Generate a participant access token for joining a LiveKit room.
     */
    public function generateParticipantToken(string $roomName, string $participantName, bool $canPublish = true): ?string
    {
        try {
            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'HS256',
                'typ' => 'JWT',
            ]));

            $claims = [
                'iss' => $this->apiKey,
                'sub' => Str::slug($participantName),
                'nbf' => time(),
                'exp' => time() + 86400, // 24 hours
                'jti' => Str::uuid()->toString(),
                'video' => [
                    'roomJoin' => true,
                    'room' => $roomName,
                    'canPublish' => $canPublish,
                    'canSubscribe' => true,
                    'canPublishData' => true,
                ],
                'metadata' => json_encode(['name' => $participantName]),
                'name' => $participantName,
            ];

            $payload = $this->base64UrlEncode(json_encode($claims));

            $signature = $this->base64UrlEncode(
                hash_hmac('sha256', "{$header}.{$payload}", $this->apiSecret, true)
            );

            return "{$header}.{$payload}.{$signature}";
        } catch (\Exception $e) {
            Log::error('LiveKit token generation error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Delete/close a LiveKit room.
     */
    public function deleteRoom(string $roomName): bool
    {
        try {
            $token = $this->generateServiceToken();
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->serverUrl}/twirp/livekit.RoomService/DeleteRoom", [
                    'room' => $roomName,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('LiveKit room deletion error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get the WebSocket URL for client connections.
     */
    public function getWsUrl(): string
    {
        return $this->wsUrl;
    }

    /**
     * Generate a service-level JWT for server-to-server API calls.
     */
    private function generateServiceToken(): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]));

        $claims = [
            'iss' => $this->apiKey,
            'nbf' => time(),
            'exp' => time() + 600, // 10 minutes
            'video' => [
                'roomCreate' => true,
                'roomList' => true,
                'roomAdmin' => true,
            ],
        ];

        $payload = $this->base64UrlEncode(json_encode($claims));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->apiSecret, true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

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
