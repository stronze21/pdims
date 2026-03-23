<?php

namespace App\Services;

use App\Models\Portal\PushNotificationSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $subscriptions = PushNotificationSubscription::where('user_id', $userId)->get();

        foreach ($subscriptions as $subscription) {
            $this->sendToTopic($subscription->ntfy_topic, $title, $body, $data);
        }
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): void
    {
        $ntfyUrl = config('services.ntfy.url', 'http://ntfy.mmmhmc.local:2586');
        $ntfyUser = config('services.ntfy.user');
        $ntfyPassword = config('services.ntfy.password');

        try {
            $request = Http::timeout(5)
                ->withHeaders([
                    'Title' => $title,
                    'Priority' => 'high',
                    'Tags' => 'chat',
                ]);

            if (!empty($data)) {
                $request = $request->withHeaders([
                    'Actions' => 'view, Open Chat, intent://chat/' . ($data['conversation_id'] ?? ''),
                    'X-Data' => json_encode($data),
                ]);
            }

            if ($ntfyUser && $ntfyPassword) {
                $request = $request->withBasicAuth($ntfyUser, $ntfyPassword);
            }

            $request->post("{$ntfyUrl}/{$topic}", $body);
        } catch (\Exception $e) {
            Log::warning('Failed to send ntfy push notification', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
