<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class PatientConversationMember extends Model
{
    protected $connection = 'portal';
    protected $table = 'patient_conversation_members';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'is_admin',
        'last_read_at',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'last_read_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(PatientConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(PortalUserAccount::class, 'user_id');
    }
}
