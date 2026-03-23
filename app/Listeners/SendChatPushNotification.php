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
            return;
        }

        $conversation = ChatConversation::find($message->conversation_id);
        if (!$conversation) return;

        // Find the portal user account for this patient
        $userAccount = \App\Models\Portal\PortalUserAccount::where('patient_id', $conversation->patient_id)->first();
        if (!$userAccount) return;

        $title = $message->sender_name ?? 'New Message';
        $body = \Illuminate\Support\Str::limit($message->body, 100);

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
