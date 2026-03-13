<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class PortalPatientVital extends Model
{
    protected $connection = 'portal';
    protected $table = 'patient_vitals';

    protected $fillable = [
        'patient_id',
        'vital_id',
        'value',
        'vitals_date',
        'remarks',
    ];

    protected $casts = [
        'vitals_date' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }

    public function vitalSign()
    {
        return $this->belongsTo(PortalVitalSign::class, 'vital_id');
    }
}
