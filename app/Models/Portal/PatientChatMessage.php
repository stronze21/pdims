<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class PatientChatMessage extends Model
{
    protected $connection = 'portal';
    protected $table = 'patient_chat_messages';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_name',
        'body',
    ];

    public function conversation()
    {
        return $this->belongsTo(PatientConversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(PortalUserAccount::class, 'sender_id');
    }
}
