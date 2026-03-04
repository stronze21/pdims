<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class PortalAdditionalMedInfo extends Model
{
    protected $connection = 'portal';
    protected $table = 'patient_additional_med_infos';

    protected $fillable = [
        'medinfo_id',
        'patient_id',
        'description',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }
}
