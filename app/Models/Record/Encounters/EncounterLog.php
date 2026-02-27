<?php

namespace App\Models\Record\Encounters;

use App\Models\Record\Patients\Patient;
use Illuminate\Database\Eloquent\Model;
use App\Models\Record\Billing\AccountTrack;
use App\Models\Pharmacy\Dispensing\DrugOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EncounterLog extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.henctr';
    protected $primaryKey = 'enccode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'enccode',
        'fhud',
        'hpercode',
        'encdate',
        'enctime',
        'toecode',
        'sopcode1',
        'encstat',
        'confdl',
        'enclock',
    ];

    protected $casts = [
        'encdate' => 'datetime',
        'enctime' => 'datetime',
    ];

    // Relationships
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'hpercode', 'hpercode');
    }

    public function opd()
    {
        return $this->hasOne(OpdLog::class, 'enccode', 'enccode');
    }

    public function er()
    {
        return $this->hasOne(ErLog::class, 'enccode', 'enccode');
    }

    public function adm()
    {
        return $this->hasOne(AdmLog::class, 'enccode', 'enccode');
    }

    public function diagnosis()
    {
        return $this->hasOne(EncounterDiagnosis::class, 'enccode', 'enccode');
    }

    public function allDiagnoses()
    {
        return $this->hasMany(EncounterDiagnosis::class, 'enccode', 'enccode');
    }

    public function accountTrack()
    {
        return $this->hasOne(AccountTrack::class, 'enccode', 'enccode');
    }

    public function rxo()
    {
        return $this->hasMany(DrugOrder::class, 'enccode', 'enccode');
    }

    // Helper methods
    public function getEncounterType()
    {
        $types = [
            'OPD' => 'Out-Patient',
            'ER' => 'Emergency Room',
            'ERADM' => 'ER to Admission',
            'ADM' => 'Admission',
            'OPDAD' => 'OPD to Admission',
            'WALKN' => 'Walk-In',
        ];

        return $types[$this->toecode] ?? $this->toecode;
    }

    public function isActive()
    {
        return $this->encstat === 'A';
    }

    public function isWalkIn()
    {
        return $this->toecode === 'WALKN';
    }

    public function isDischarged()
    {
        if ($this->opd && $this->opd->isDischarged()) return true;
        if ($this->er && $this->er->isDischarged()) return true;
        if ($this->adm && $this->adm->isDischarged()) return true;

        return false;
    }
}
