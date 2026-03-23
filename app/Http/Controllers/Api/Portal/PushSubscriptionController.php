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
            return response()->json([
                'ntfy_topic' => $existing->ntfy_topic,
                'ntfy_url' => config('services.ntfy.url', 'http://ntfy.mmmhmc.local:2586'),
            ]);
        }

        $topic = 'salunat-' . $userId . '-' . Str::random(16);

        $subscription = PushNotificationSubscription::create([
            'user_id' => $userId,
            'ntfy_topic' => $topic,
            'platform' => $platform,
        ]);

        return response()->json([
            'ntfy_topic' => $subscription->ntfy_topic,
            'ntfy_url' => config('services.ntfy.url', 'http://ntfy.mmmhmc.local:2586'),
        ], 201);
    }

    public function unsubscribe(Request $request)
    {
        $userId = $request->user()->id;

        PushNotificationSubscription::where('user_id', $userId)->delete();

        return response()->json(['message' => 'Push subscription removed.']);
    }
}
