<?php

namespace App\Models\Record\Patients;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\Record\Patients\PatientMss;
use App\Models\Record\Patients\PatientAddress;
use App\Models\Record\Encounters\EncounterLog;
use App\Models\References\Religion;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Patient extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hperson';
    protected $primaryKey = 'hpercode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'hpatkey',
        'hfhudcode',
        'hpercode',
        'hpatcode',
        'hspocode',
        'patlast',
        'patfirst',
        'patmiddle',
        'patsuffix',
        'patprefix',
        'patmaidnm',
        'patbdate',
        'patbplace',
        'patsex',
        'patcstat',
        'patempstat',
        'citcode',
        'natcode',
        'relcode',
        'patmmdn',
        'phicnum',
        'patmedno',
        'patemernme',
        'patemaddr',
        'pattelno',
        'relemacode',
        'f_dec',
        'patstat',
        'patlock',
        'datemod',
        'updsw',
        'confdl',
        'fm_dec',
        'bldcode',
        'entryby',
        'fatlast',
        'fatfirst',
        'fatmid',
        'motlast',
        'motfirst',
        'motmid',
        'splast',
        'spfirst',
        'spmid',
        'fataddr',
        'motaddr',
        'spaddr',
        'fatsuffix',
        'motsuffix',
        'spsuffix',
        'fatempname',
        'fatempaddr',
        'fatempeml',
        'fatemptel',
        'motempname',
        'motempaddr',
        'motempeml',
        'motemptel',
        'spempname',
        'spempaddr',
        'spempeml',
        'spemptel',
        'fattel',
        'mottel',
        'mssno',
        'srcitizen',
        'picname',
        's_dec',
        'hsmokingcs',
        'hospperson',
        'radcasenum',
        'kmc',
        'kmc_stat',
        'renal',
        'renal_stat',
        'ems',
        'ems_type',
        'kmc_id',
        'kmc_rem',
        'kmc_date',
        'hperson_verified',
    ];

    protected $casts = [
        'patbdate' => 'datetime',
        'datemod' => 'datetime',
        'kmc_date' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function addresses()
    {
        return $this->hasMany(PatientAddress::class, 'hpercode', 'hpercode');
    }

    public function primaryAddress()
    {
        return $this->hasOne(PatientAddress::class, 'hpercode', 'hpercode')
            ->where('addstat', 'A');
    }

    public function religion()
    {
        return $this->belongsTo(Religion::class, 'relcode', 'relcode');
    }

    public function encounters()
    {
        return $this->hasMany(EncounterLog::class, 'hpercode', 'hpercode');
    }

    public function activeEncounters()
    {
        return $this->hasMany(EncounterLog::class, 'hpercode', 'hpercode')
            ->where('encstat', 'A')
            ->where('toecode', '!=', 'WALKN')
            ->where('toecode', '!=', '32')
            ->where('enclock', 'N')
            ->orderBy('encdate', 'desc');
    }

    public function latestEncounter()
    {
        return $this->hasOne(EncounterLog::class, 'hpercode', 'hpercode')
            ->where('encstat', 'A')
            ->where('toecode', '!=', 'WALKN')
            ->where('toecode', '!=', '32')
            ->where('enclock', 'N')
            ->latest('encdate');
    }

    // ==========================================
    // Accessor Methods
    // ==========================================

    public function getFullnameAttribute()
    {
        $suffix = $this->patsuffix ? $this->patsuffix . ' ' : '';
        $middle = $this->patmiddle ? mb_substr($this->patmiddle, 0, 1) . '.' : '';

        return trim("{$this->patlast}, {$suffix}{$this->patfirst} {$middle}");
    }

    public function fullnameWithSuffix()
    {
        $parts = [$this->patfirst];

        if ($this->patmiddle) {
            $parts[] = mb_substr($this->patmiddle, 0, 1) . '.';
        }

        $parts[] = $this->patlast;

        if ($this->patsuffix) {
            $parts[] = $this->patsuffix;
        }

        return implode(' ', $parts);
    }

    public function age()
    {
        if (!$this->patbdate) {
            return 'N/A';
        }

        return Carbon::parse($this->patbdate)->diff(Carbon::now())->format('%yY, %mM and %dD');
    }

    public function ageInYears()
    {
        if (!$this->patbdate) {
            return null;
        }

        return Carbon::parse($this->patbdate)->age;
    }

    public function bdate_format1()
    {
        return $this->patbdate ? Carbon::parse($this->patbdate)->format('Y/m/d') : 'N/A';
    }

    public function bdateFormatted($format = 'M d, Y')
    {
        return $this->patbdate ? Carbon::parse($this->patbdate)->format($format) : 'N/A';
    }

    public function gender()
    {
        return $this->patsex === 'M' ? 'Male' : 'Female';
    }

    public function csstat()
    {
        $statuses = [
            'S' => 'Single',
            'M' => 'Married',
            'D' => 'Divorced',
            'X' => 'Separated',
            'W' => 'Widow/Widower',
            'N' => 'Not Applicable',
        ];

        return $statuses[$this->patcstat] ?? '...';
    }

    public function empstat()
    {
        $statuses = [
            'EMPLO' => 'Employed',
            'SELFE' => 'Self-employed',
            'UNEMP' => 'Unemployed',
        ];

        return $statuses[$this->patempstat] ?? 'N/A';
    }

    public function getFullAddress()
    {
        $address = $this->primaryAddress;

        if (!$address) {
            return 'No address on file';
        }

        $parts = array_filter([
            $address->patstr,
            $address->barangay?->bgyname,
            $address->city?->ctyname,
            $address->province?->provname,
        ]);

        return implode(', ', $parts);
    }

    // ==========================================
    // Status Checks
    // ==========================================

    public function isActive()
    {
        return $this->patstat === 'A';
    }

    public function isDeceased()
    {
        return $this->f_dec === 'Y' || $this->fm_dec === 'Y' || $this->s_dec === 'Y';
    }

    public function hasActiveEncounter()
    {
        return $this->activeEncounters()->exists();
    }
}
