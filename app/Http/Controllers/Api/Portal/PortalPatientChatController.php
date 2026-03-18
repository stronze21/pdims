<?php

namespace App\Http\Controllers\Api\Portal;

use App\Events\Portal\NewPatientChatMessage;
use App\Http\Controllers\Controller;
use App\Models\Portal\Friend;
use App\Models\Portal\PatientChatMessage;
use App\Models\Portal\PatientConversation;
use App\Models\Portal\PatientConversationMember;
use Illuminate\Http\Request;

class PortalPatientChatController extends Controller
{
    public function conversations(Request $request)
    {
        $userId = $request->user()->id;

        $conversationIds = PatientConversationMember::where('user_id', $userId)
            ->pluck('conversation_id');

        $conversations = PatientConversation::whereIn('id', $conversationIds)
            ->with(['members.user.patient', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function ($conv) use ($userId) {
                $latestMsg = $conv->latestMessage;
                $members = $conv->members->map(function ($m) {
                    $patient = $m->user?->patient;
                    return [
                        'user_id' => $m->user_id,
                        'username' => $m->user?->username,
                        'first_name' => $patient?->patfirst,
                        'last_name' => $patient?->patlast,
                        'avatar' => $patient?->patimage,
                        'is_admin' => $m->is_admin,
                    ];
                });

                return [
                    'id' => $conv->id,
                    'type' => $conv->type,
                    'name' => $conv->name,
                    'avatar' => null,
                    'last_message_at' => $conv->last_message_at?->toISOString(),
                    'last_message' => $latestMsg?->body,
                    'last_message_sender_name' => $latestMsg?->sender_name,
                    'unread_count' => $conv->unreadCountForUser($userId),
                    'member_count' => $conv->members->count(),
                    'members' => $members,
                    'created_at' => $conv->created_at?->toISOString(),
                ];
            });

        return response()->json($conversations);
    }

    public function createConversation(Request $request)
    {
        $request->validate([
            'type' => 'required|in:dm,group',
            'friend_user_id' => 'required_if:type,dm|integer',
            'name' => 'required_if:type,group|string|max:255',
            'member_user_ids' => 'required_if:type,group|array',
            'member_user_ids.*' => 'integer',
        ]);

        $userId = $request->user()->id;
        $type = $request->type;

        if ($type === 'dm') {
            $friendUserId = $request->friend_user_id;

            // Verify friendship
            $isFriend = Friend::where('user_id', $userId)
                ->where('friend_user_id', $friendUserId)
                ->exists();

            if (!$isFriend) {
                return response()->json(['message' => 'You can only message friends.'], 422);
            }

            // Check for existing DM between these two users
            $existingConversation = PatientConversation::where('type', 'dm')
                ->whereHas('members', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->whereHas('members', function ($q) use ($friendUserId) {
                    $q->where('user_id', $friendUserId);
                })
                ->first();

            if ($existingConversation) {
                return response()->json([
                    'message' => 'Conversation already exists.',
                    'conversation_id' => $existingConversation->id,
                ]);
            }

            $conversation = PatientConversation::create([
                'type' => 'dm',
                'created_by' => $userId,
            ]);

            PatientConversationMember::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'is_admin' => false,
            ]);
            PatientConversationMember::create([
                'conversation_id' => $conversation->id,
                'user_id' => $friendUserId,
                'is_admin' => false,
            ]);
        } else {
            // Group chat
            $memberUserIds = $request->member_user_ids;

            $conversation = PatientConversation::create([
                'type' => 'group',
                'name' => $request->name,
                'created_by' => $userId,
            ]);

            // Add creator as admin
            PatientConversationMember::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'is_admin' => true,
            ]);

            // Add other members
            foreach ($memberUserIds as $memberId) {
                if ($memberId !== $userId) {
                    PatientConversationMember::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $memberId,
                        'is_admin' => false,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Conversation created successfully.',
            'conversation_id' => $conversation->id,
        ], 201);
    }

    public function messages(Request $request, $conversationId)
    {
        $userId = $request->user()->id;

        // Verify membership
        $isMember = PatientConversationMember::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Not a member of this conversation.'], 403);
        }

        $conversation = PatientConversation::with(['members.user.patient'])
            ->findOrFail($conversationId);

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'sender_id' => $msg->sender_id,
                    'sender_name' => $msg->sender_name,
                    'sender_avatar' => $msg->sender?->patient?->patimage,
                    'body' => $msg->body,
                    'read_at' => null,
                    'created_at' => $msg->created_at?->toISOString(),
                ];
            });

        $members = $conversation->members->map(function ($m) {
            $patient = $m->user?->patient;
            return [
                'user_id' => $m->user_id,
                'username' => $m->user?->username,
                'first_name' => $patient?->patfirst,
                'last_name' => $patient?->patlast,
                'avatar' => $patient?->patimage,
                'is_admin' => $m->is_admin,
            ];
        });

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'name' => $conversation->name,
                'avatar' => null,
                'last_message_at' => $conversation->last_message_at?->toISOString(),
                'member_count' => $conversation->members->count(),
                'members' => $members,
                'created_at' => $conversation->created_at?->toISOString(),
            ],
            'messages' => $messages,
        ]);
    }

    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $account = $request->user();
        $account->load('patient');
        $userId = $account->id;

        // Verify membership
        $isMember = PatientConversationMember::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Not a member of this conversation.'], 403);
        }

        $senderName = $account->patient
            ? trim($account->patient->patfirst . ' ' . $account->patient->patlast)
            : $account->username;

        $message = PatientChatMessage::create([
            'conversation_id' => $conversationId,
            'sender_id' => $userId,
            'sender_name' => $senderName,
            'body' => $request->body,
        ]);

        PatientConversation::where('id', $conversationId)
            ->update(['last_message_at' => now()]);

        broadcast(new NewPatientChatMessage($message));

        return response()->json([
            'message' => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender_name,
                'sender_avatar' => $account->patient?->patimage,
                'body' => $message->body,
                'created_at' => $message->created_at?->toISOString(),
            ],
        ], 201);
    }

    public function markRead(Request $request, $conversationId)
    {
        $userId = $request->user()->id;

        $member = PatientConversationMember::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $member->update(['last_read_at' => now()]);

        return response()->json(['message' => 'Messages marked as read.']);
    }

    public function addMember(Request $request, $conversationId)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $userId = $request->user()->id;

        $conversation = PatientConversation::where('id', $conversationId)
            ->where('type', 'group')
            ->firstOrFail();

        // Verify requester is a member
        $isMember = $conversation->members()->where('user_id', $userId)->exists();
        if (!$isMember) {
            return response()->json(['message' => 'Not a member of this conversation.'], 403);
        }

        // Check if already a member
        $alreadyMember = $conversation->members()->where('user_id', $request->user_id)->exists();
        if ($alreadyMember) {
            return response()->json(['message' => 'User is already a member.'], 422);
        }

        PatientConversationMember::create([
            'conversation_id' => $conversationId,
            'user_id' => $request->user_id,
            'is_admin' => false,
        ]);

        return response()->json(['message' => 'Member added.']);
    }

    public function removeMember(Request $request, $conversationId, $memberId)
    {
        $userId = $request->user()->id;

        $conversation = PatientConversation::where('id', $conversationId)
            ->where('type', 'group')
            ->firstOrFail();

        // Verify requester is admin
        $isAdmin = $conversation->members()
            ->where('user_id', $userId)
            ->where('is_admin', true)
            ->exists();

        if (!$isAdmin) {
            return response()->json(['message' => 'Only admins can remove members.'], 403);
        }

        $conversation->members()->where('user_id', $memberId)->delete();

        return response()->json(['message' => 'Member removed.']);
    }

    public function leave(Request $request, $conversationId)
    {
        $userId = $request->user()->id;

        $conversation = PatientConversation::findOrFail($conversationId);

        $conversation->members()->where('user_id', $userId)->delete();

        // If no members left, delete the conversation
        if ($conversation->members()->count() === 0) {
            $conversation->messages()->delete();
            $conversation->delete();
        }

        return response()->json(['message' => 'Left conversation.']);
    }
}
