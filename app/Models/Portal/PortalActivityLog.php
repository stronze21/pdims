<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class PortalActivityLog extends Model
{
    protected $connection = 'portal';
    protected $table = 'patient_activity_logs';

    protected $fillable = [
        'patient_id',
        'is_read',
        'reference_id',
        'reference',
        'verbose',
        'type',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }
}
