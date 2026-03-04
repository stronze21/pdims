<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortalUserReset extends Model
{
    use SoftDeletes;

    protected $connection = 'portal';
    protected $table = 'app_user_resets';

    protected $fillable = [
        'patient_id',
        'device_model',
        'device_platform',
        'os_version',
        'reset_pin',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }
}
