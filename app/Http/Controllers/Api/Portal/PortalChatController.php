<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\ChatConversation;
use App\Models\Portal\ChatMessage;
use Illuminate\Http\Request;

class PortalChatController extends Controller
{
    public function conversations(Request $request)
    {
        $account = $request->user();
        $patientId = $account->patient_id;

        $conversations = ChatConversation::where('patient_id', $patientId)
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function ($conv) {
                return [
                    'id' => $conv->id,
                    'subject' => $conv->subject,
                    'status' => $conv->status,
                    'last_message_at' => $conv->last_message_at?->toISOString(),
                    'last_message' => $conv->latestMessage?->body,
                    'last_message_sender' => $conv->latestMessage?->sender_type,
                    'unread_count' => $conv->unreadCountForPatient(),
                    'created_at' => $conv->created_at?->toISOString(),
                ];
            });

        return response()->json($conversations);
    }

    public function createConversation(Request $request)
    {
        $request->validate([
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $account = $request->user();
        $account->load('patient');
        $patientId = $account->patient_id;

        $conversation = ChatConversation::create([
            'patient_id' => $patientId,
            'subject' => $request->subject ?? 'General Inquiry',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $patientName = $account->patient
            ? trim($account->patient->patfirst . ' ' . $account->patient->patlast)
            : $account->username;

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'patient',
            'sender_id' => $patientId,
            'sender_name' => $patientName,
            'body' => $request->message,
        ]);

        return response()->json([
            'message' => 'Conversation created successfully.',
            'conversation_id' => $conversation->id,
        ], 201);
    }

    public function messages(Request $request, $conversationId)
    {
        $account = $request->user();
        $patientId = $account->patient_id;

        $conversation = ChatConversation::where('id', $conversationId)
            ->where('patient_id', $patientId)
            ->firstOrFail();

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'sender_type' => $msg->sender_type,
                    'sender_name' => $msg->sender_name,
                    'body' => $msg->body,
                    'read_at' => $msg->read_at?->toISOString(),
                    'created_at' => $msg->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
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
        $patientId = $account->patient_id;

        $conversation = ChatConversation::where('id', $conversationId)
            ->where('patient_id', $patientId)
            ->where('status', 'open')
            ->firstOrFail();

        $patientName = $account->patient
            ? trim($account->patient->patfirst . ' ' . $account->patient->patlast)
            : $account->username;

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'patient',
            'sender_id' => $patientId,
            'sender_name' => $patientName,
            'body' => $request->body,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_name,
                'body' => $message->body,
                'created_at' => $message->created_at?->toISOString(),
            ],
        ], 201);
    }

    public function markRead(Request $request, $conversationId)
    {
        $account = $request->user();
        $patientId = $account->patient_id;

        $conversation = ChatConversation::where('id', $conversationId)
            ->where('patient_id', $patientId)
            ->firstOrFail();

        $conversation->messages()
            ->where('sender_type', 'staff')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Messages marked as read.']);
    }
}
