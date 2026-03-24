<?php

namespace App\Listeners;

use App\Events\Portal\NewChatMessage;
use App\Events\Portal\NewPatientChatMessage;
use App\Models\Portal\ChatConversation;
use App\Models\Portal\PatientConversationMember;
use App\Services\PushNotificationService;

class SendChatPushNotification
{
    public function __construct(
        private PushNotificationService $pushService
    ) {}

    public function handle(NewChatMessage|NewPatientChatMessage $event): void
    {
        $message = $event->message;

        \Illuminate\Support\Facades\Log::info('SendChatPushNotification: event received', [
            'event_type' => get_class($event),
            'message_id' => $message->id ?? null,
            'sender_type' => $message->sender_type ?? null,
            'sender_name' => $message->sender_name ?? null,
            'conversation_id' => $message->conversation_id ?? null,
        ]);

        if ($event instanceof NewChatMessage) {
            $this->handleStaffPatientChat($message);
        } else {
            $this->handlePatientPatientChat($message);
        }
    }

    private function handleStaffPatientChat($message): void
    {
        // Staff-to-patient chat: if sender is staff, notify the patient
        // If sender is patient, staff gets notified through their own system
        if ($message->sender_type !== 'staff') {
            \Illuminate\Support\Facades\Log::info('SendChatPushNotification: skipping non-staff message', [
                'sender_type' => $message->sender_type,
            ]);
            return;
        }

        $conversation = ChatConversation::find($message->conversation_id);
        if (!$conversation) {
            \Illuminate\Support\Facades\Log::warning('SendChatPushNotification: conversation not found', [
                'conversation_id' => $message->conversation_id,
            ]);
            return;
        }

        // Find the portal user account for this patient
        $userAccount = \App\Models\Portal\PortalUserAccount::where('patient_id', $conversation->patient_id)->first();
        if (!$userAccount) {
            \Illuminate\Support\Facades\Log::warning('SendChatPushNotification: user account not found for patient', [
                'patient_id' => $conversation->patient_id,
            ]);
            return;
        }

        $title = $message->sender_name ?? 'New Message';
        $body = \Illuminate\Support\Str::limit($message->body, 100);

        \Illuminate\Support\Facades\Log::info('SendChatPushNotification: sending push to user', [
            'user_id' => $userAccount->id,
            'title' => $title,
            'body' => $body,
        ]);

        $this->pushService->sendToUser($userAccount->id, $title, $body, [
            'type' => 'staff_chat',
            'conversation_id' => $message->conversation_id,
        ]);
    }

    private function handlePatientPatientChat($message): void
    {
        // Notify all members of the conversation except the sender
        $memberUserIds = PatientConversationMember::where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $message->sender_id)
            ->pluck('user_id');

        \Illuminate\Support\Facades\Log::info('SendChatPushNotification: patient chat, notifying members', [
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'recipient_count' => $memberUserIds->count(),
        ]);

        $title = $message->sender_name ?? 'New Message';
        $body = \Illuminate\Support\Str::limit($message->body, 100);

        foreach ($memberUserIds as $userId) {
            $this->pushService->sendToUser($userId, $title, $body, [
                'type' => 'patient_chat',
                'conversation_id' => $message->conversation_id,
            ]);
        }
    }
}
