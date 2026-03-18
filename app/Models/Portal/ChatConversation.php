<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    protected $connection = 'portal';
    protected $table = 'chat_conversations';

    protected $fillable = [
        'patient_id',
        'subject',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany();
    }

    public function unreadCountForPatient()
    {
        return $this->messages()
            ->where('sender_type', 'staff')
            ->whereNull('read_at')
            ->count();
    }
}
