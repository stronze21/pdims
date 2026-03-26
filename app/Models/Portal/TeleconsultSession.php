<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeleconsultSession extends Model
{
    protected $connection = 'portal';

    protected $table = 'teleconsult_sessions';

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_employee_id',
        'doctor_name',
        'platform',
        'webex_meeting_id',
        'webex_meeting_link',
        'webex_sip_address',
        'webex_meeting_number',
        'webex_meeting_password',
        'webex_host_key',
        'jitsi_room_name',
        'jitsi_meeting_link',
        'livekit_room_name',
        'livekit_token',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'duration_minutes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    protected $hidden = [
        'webex_meeting_password',
        'webex_host_key',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }

    public function note(): HasOne
    {
        return $this->hasOne(TeleconsultNote::class);
    }

    public function isJoinable(): bool
    {
        return in_array($this->status, ['scheduled', 'waiting', 'in_progress']);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['waiting', 'in_progress']);
    }
}
