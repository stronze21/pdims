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

        Log::info('PushNotificationService: sending to user', [
            'user_id' => $userId,
            'subscription_count' => $subscriptions->count(),
            'topics' => $subscriptions->pluck('ntfy_topic')->toArray(),
        ]);

        if ($subscriptions->isEmpty()) {
            Log::warning('PushNotificationService: no subscriptions found for user', [
                'user_id' => $userId,
            ]);
            return;
        }

        foreach ($subscriptions as $subscription) {
            $this->sendToTopic($subscription->ntfy_topic, $title, $body, $data);
        }
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): void
    {
        $ntfyUrl = config('services.ntfy.url', 'http://ntfy.mmmhmc.local:2586');
        $ntfyUser = config('services.ntfy.user');
        $ntfyPassword = config('services.ntfy.password');

        Log::info('PushNotificationService: posting to ntfy', [
            'url' => "{$ntfyUrl}/{$topic}",
            'title' => $title,
            'has_auth' => !empty($ntfyUser),
        ]);

        try {
            $request = Http::timeout(5)
                ->withHeaders([
                    'Title' => $title,
                    'Priority' => 'high',
                    'Tags' => 'chat',
                ]);

            if (!empty($data)) {
                $request = $request->withHeaders([
                    'X-Data' => json_encode($data),
                ]);
            }

            if ($ntfyUser && $ntfyPassword) {
                $request = $request->withBasicAuth($ntfyUser, $ntfyPassword);
            }

            // ntfy expects the message body as raw text with Content-Type text/plain
            $response = $request->withBody($body, 'text/plain')->post("{$ntfyUrl}/{$topic}");

            Log::info('PushNotificationService: ntfy response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('PushNotificationService: failed to send ntfy push notification', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
