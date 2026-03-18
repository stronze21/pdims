<?php

namespace App\Http\Controllers\Api\Portal;

use App\Events\Portal\FriendRequestAccepted;
use App\Events\Portal\FriendRequestSent;
use App\Http\Controllers\Controller;
use App\Models\Portal\Friend;
use App\Models\Portal\FriendRequest;
use App\Models\Portal\PortalUserAccount;
use Illuminate\Http\Request;

class PortalFriendsController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $friends = Friend::where('user_id', $userId)
            ->with(['friendUser.patient'])
            ->get()
            ->map(function ($friend) {
                $user = $friend->friendUser;
                $patient = $user?->patient;
                return [
                    'id' => $friend->id,
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $patient?->patfirst,
                    'last_name' => $patient?->patlast,
                    'avatar' => $patient?->patimage,
                    'is_online' => false,
                    'created_at' => $friend->created_at?->toISOString(),
                ];
            });

        return response()->json($friends);
    }

    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1',
        ]);

        $userId = $request->user()->id;
        $query = $request->q;

        $friendIds = Friend::where('user_id', $userId)->pluck('friend_user_id')->toArray();

        $pendingRequestUserIds = FriendRequest::where('status', 'pending')
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)->orWhere('to_user_id', $userId);
            })
            ->get()
            ->flatMap(fn ($r) => [$r->from_user_id, $r->to_user_id])
            ->unique()
            ->toArray();

        $users = PortalUserAccount::where('id', '!=', $userId)
            ->whereNotNull('patient_id')
            ->where(function ($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")
                    ->orWhereHas('patient', function ($q2) use ($query) {
                        $q2->where('patfirst', 'like', "%{$query}%")
                            ->orWhere('patlast', 'like', "%{$query}%");
                    });
            })
            ->with('patient')
            ->limit(20)
            ->get()
            ->map(function ($user) use ($friendIds, $pendingRequestUserIds) {
                $patient = $user->patient;
                return [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $patient?->patfirst,
                    'last_name' => $patient?->patlast,
                    'avatar' => $patient?->patimage,
                    'is_friend' => in_array($user->id, $friendIds),
                    'has_pending_request' => in_array($user->id, $pendingRequestUserIds),
                ];
            });

        return response()->json($users);
    }

    public function sendRequest(Request $request)
    {
        $request->validate([
            'to_user_id' => 'required|integer',
        ]);

        $fromUserId = $request->user()->id;
        $toUserId = $request->to_user_id;

        if ($fromUserId === $toUserId) {
            return response()->json(['message' => 'Cannot send friend request to yourself.'], 422);
        }

        $targetUser = PortalUserAccount::find($toUserId);
        if (!$targetUser) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $existingFriend = Friend::where('user_id', $fromUserId)
            ->where('friend_user_id', $toUserId)
            ->exists();

        if ($existingFriend) {
            return response()->json(['message' => 'Already friends.'], 422);
        }

        $existingRequest = FriendRequest::where('status', 'pending')
            ->where(function ($q) use ($fromUserId, $toUserId) {
                $q->where(function ($q2) use ($fromUserId, $toUserId) {
                    $q2->where('from_user_id', $fromUserId)->where('to_user_id', $toUserId);
                })->orWhere(function ($q2) use ($fromUserId, $toUserId) {
                    $q2->where('from_user_id', $toUserId)->where('to_user_id', $fromUserId);
                });
            })
            ->first();

        if ($existingRequest) {
            return response()->json(['message' => 'A pending friend request already exists.'], 422);
        }

        $friendRequest = FriendRequest::create([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'status' => 'pending',
        ]);

        $friendRequest->load(['fromUser.patient', 'toUser.patient']);
        $formatted = $this->formatFriendRequest($friendRequest);

        broadcast(new FriendRequestSent($toUserId, $formatted));

        return response()->json([
            'message' => 'Friend request sent.',
            'friend_request' => $formatted,
        ], 201);
    }

    public function requests(Request $request)
    {
        $userId = $request->user()->id;

        $requests = FriendRequest::where('to_user_id', $userId)
            ->where('status', 'pending')
            ->with(['fromUser.patient', 'toUser.patient'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => $this->formatFriendRequest($r));

        return response()->json($requests);
    }

    public function sentRequests(Request $request)
    {
        $userId = $request->user()->id;

        $requests = FriendRequest::where('from_user_id', $userId)
            ->where('status', 'pending')
            ->with(['fromUser.patient', 'toUser.patient'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => $this->formatFriendRequest($r));

        return response()->json($requests);
    }

    public function acceptRequest(Request $request, $requestId)
    {
        $userId = $request->user()->id;

        $friendRequest = FriendRequest::where('id', $requestId)
            ->where('to_user_id', $userId)
            ->where('status', 'pending')
            ->firstOrFail();

        $friendRequest->update(['status' => 'accepted']);

        // Create bidirectional friendship
        Friend::create([
            'user_id' => $friendRequest->from_user_id,
            'friend_user_id' => $friendRequest->to_user_id,
        ]);
        Friend::create([
            'user_id' => $friendRequest->to_user_id,
            'friend_user_id' => $friendRequest->from_user_id,
        ]);

        $friendRequest->load(['fromUser.patient', 'toUser.patient']);
        $formatted = $this->formatFriendRequest($friendRequest);

        // Notify the original requester that their request was accepted
        broadcast(new FriendRequestAccepted($friendRequest->from_user_id, $formatted));

        return response()->json([
            'message' => 'Friend request accepted.',
            'friend_request' => $formatted,
        ]);
    }

    public function declineRequest(Request $request, $requestId)
    {
        $userId = $request->user()->id;

        $friendRequest = FriendRequest::where('id', $requestId)
            ->where('to_user_id', $userId)
            ->where('status', 'pending')
            ->firstOrFail();

        $friendRequest->update(['status' => 'declined']);
        $friendRequest->load(['fromUser.patient', 'toUser.patient']);

        return response()->json([
            'message' => 'Friend request declined.',
            'friend_request' => $this->formatFriendRequest($friendRequest),
        ]);
    }

    public function removeFriend(Request $request, $friendId)
    {
        $userId = $request->user()->id;

        $friend = Friend::where('id', $friendId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $friendUserId = $friend->friend_user_id;

        // Remove bidirectional friendship
        Friend::where('user_id', $userId)->where('friend_user_id', $friendUserId)->delete();
        Friend::where('user_id', $friendUserId)->where('friend_user_id', $userId)->delete();

        return response()->json(['message' => 'Friend removed.']);
    }

    private function formatFriendRequest(FriendRequest $r): array
    {
        $fromPatient = $r->fromUser?->patient;
        $toPatient = $r->toUser?->patient;

        return [
            'id' => $r->id,
            'from_user_id' => $r->from_user_id,
            'to_user_id' => $r->to_user_id,
            'from_username' => $r->fromUser?->username,
            'from_first_name' => $fromPatient?->patfirst,
            'from_last_name' => $fromPatient?->patlast,
            'from_avatar' => $fromPatient?->patimage,
            'to_username' => $r->toUser?->username,
            'to_first_name' => $toPatient?->patfirst,
            'to_last_name' => $toPatient?->patlast,
            'status' => $r->status,
            'created_at' => $r->created_at?->toISOString(),
        ];
    }
}
