<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeleconsultNote extends Model
{
    protected $connection = 'portal';

    protected $table = 'teleconsult_notes';

    protected $fillable = [
        'teleconsult_session_id',
        'doctor_employee_id',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'additional_notes',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TeleconsultSession::class, 'teleconsult_session_id');
    }
}
