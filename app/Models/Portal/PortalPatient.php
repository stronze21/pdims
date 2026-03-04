<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortalPatient extends Model
{
    protected $connection = 'portal';
    protected $table = 'patients';

    protected $fillable = [
        'hpercode',
        'patlast',
        'patfirst',
        'patmiddle',
        'patbdate',
        'pataddress',
        'patcivilstat',
        'patgender',
        'patcontactno',
        'patoccupation',
        'patemail',
        'patimage',
        'isLGBTQ',
        'patReligion',
        'patEducation',
        'patIncome',
        'patNationality',
    ];

    protected $casts = [
        'patbdate' => 'date',
        'isLGBTQ' => 'boolean',
        'patIncome' => 'decimal:2',
    ];

    public function userAccount()
    {
        return $this->hasOne(PortalUserAccount::class, 'patient_id');
    }

    public function addresses()
    {
        return $this->hasMany(PortalPatientAddress::class, 'patient_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(PortalActivityLog::class, 'patient_id');
    }

    public function additionalMedInfos()
    {
        return $this->hasMany(PortalAdditionalMedInfo::class, 'patient_id');
    }

    public function getFullnameAttribute(): string
    {
        $middle = $this->patmiddle ? ' ' . mb_substr($this->patmiddle, 0, 1) . '.' : '';
        return trim("{$this->patlast}, {$this->patfirst}{$middle}");
    }
}
