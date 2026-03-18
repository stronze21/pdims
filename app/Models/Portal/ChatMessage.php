<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $connection = 'portal';
    protected $table = 'chat_messages';

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'sender_name',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
}
