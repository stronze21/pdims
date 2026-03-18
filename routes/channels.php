<?php

use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

Broadcast::channel('pharmacy.location.{locationCode}', function ($user, $locationCode) {
    return true;
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    // Staff users can access all chat conversations
    if (!isset($user->patient_id)) {
        return true;
    }

    // Patients can only access their own conversations
    return \App\Models\Portal\ChatConversation::where('id', $conversationId)
        ->where('patient_id', $user->patient_id)
        ->exists();
});

Broadcast::channel('patient.chat.{conversationId}', function ($user, $conversationId) {
    // Verify user is a member of the patient-to-patient conversation
    return \App\Models\Portal\PatientConversationMember::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});
