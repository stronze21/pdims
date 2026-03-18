<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class PatientConversation extends Model
{
    protected $connection = 'portal';
    protected $table = 'patient_conversations';

    protected $fillable = [
        'type',
        'name',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(PortalUserAccount::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(PatientConversationMember::class, 'conversation_id');
    }

    public function messages()
    {
        return $this->hasMany(PatientChatMessage::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(PatientChatMessage::class, 'conversation_id')->latestOfMany();
    }

    public function unreadCountForUser(int $userId): int
    {
        $member = $this->members()->where('user_id', $userId)->first();
        if (!$member || !$member->last_read_at) {
            return $this->messages()->where('sender_id', '!=', $userId)->count();
        }

        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('created_at', '>', $member->last_read_at)
            ->count();
    }
}
