<?php

use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

Broadcast::channel('pharmacy.location.{locationCode}', function ($user, $locationCode) {
    return true;
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    return \App\Models\Portal\ChatConversation::where('id', $conversationId)
        ->where('patient_id', $user->patient_id)
        ->exists();
});
