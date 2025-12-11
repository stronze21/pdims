<?php

namespace App\Models\Record\Patients;

use App\Models\References\Barangay;
use App\Models\References\City;
use App\Models\References\Province;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientAddress extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.haddr';
    protected $primaryKey = 'hpercode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'hpercode',
        'patstr',
        'brg',
        'ctycode',
        'provcode',
        'patzip',
        'cntrycode',
        'addstat',
        'addlock',
        'datemod',
        'updsw',
        'confdl',
        'haddrdte',
        'entryby',
        'distzip',
    ];

    protected $casts = [
        'datemod' => 'datetime',
        'haddrdte' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'hpercode', 'hpercode');
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class, 'brg', 'bgycode');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'ctycode', 'ctycode');
    }

    public function province()
    {
        return $this->belongsTo(Province::class, 'provcode', 'provcode');
    }

    // ==========================================
    // Accessor Methods
    // ==========================================

    public function getFullAddress()
    {
        $parts = array_filter([
            $this->patstr,
            $this->barangay?->bgyname,
            $this->city?->ctyname,
            $this->province?->provname,
            $this->patzip,
        ]);

        return implode(', ', $parts);
    }

    public function isActive()
    {
        return $this->addstat === 'A';
    }
}
