<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class PortalUserAccount extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $connection = 'portal';
    protected $table = 'app_user_accounts';

    protected $fillable = [
        'patient_id',
        'pin',
        'email',
        'email_verified_at',
        'username',
    ];

    protected $hidden = [
        'pin',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }

    public function getAuthPassword()
    {
        return $this->pin;
    }
}
