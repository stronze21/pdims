<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\PushNotificationSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $request->validate([
            'platform' => 'sometimes|string|in:android,ios',
        ]);

        $userId = $request->user()->id;
        $platform = $request->input('platform', 'android');

        // Return existing subscription if one exists for this user+platform
        $existing = PushNotificationSubscription::where('user_id', $userId)
            ->where('platform', $platform)
            ->first();

        if ($existing) {
            return response()->json($this->buildResponse($existing->ntfy_topic));
        }

        $topic = 'salunat-' . $userId . '-' . Str::random(16);

        $subscription = PushNotificationSubscription::create([
            'user_id' => $userId,
            'ntfy_topic' => $topic,
            'platform' => $platform,
        ]);

        return response()->json($this->buildResponse($subscription->ntfy_topic), 201);
    }

    private function buildResponse(string $topic): array
    {
        $response = [
            'ntfy_topic' => $topic,
            'ntfy_url' => config('services.ntfy.subscriber_url') ?: config('services.ntfy.url', 'http://ntfy.mmmhmc.local:2586'),
        ];

        // Include subscriber credentials if configured (for auth-protected ntfy servers)
        $subscriberUser = config('services.ntfy.subscriber_user');
        $subscriberPassword = config('services.ntfy.subscriber_password');

        if ($subscriberUser && $subscriberPassword) {
            $response['ntfy_user'] = $subscriberUser;
            $response['ntfy_password'] = $subscriberPassword;
        }

        return $response;
    }

    public function unsubscribe(Request $request)
    {
        $userId = $request->user()->id;

        PushNotificationSubscription::where('user_id', $userId)->delete();

        return response()->json(['message' => 'Push subscription removed.']);
    }
}
