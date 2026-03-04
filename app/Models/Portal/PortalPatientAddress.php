<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortalPatientAddress extends Model
{
    use SoftDeletes;

    protected $connection = 'portal';
    protected $table = 'patient_addresses';

    protected $fillable = [
        'patient_id',
        'address_type',
        'street',
        'zip',
        'brgycode',
        'living_arrangement',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }
}
