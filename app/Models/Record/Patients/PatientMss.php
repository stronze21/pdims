<?php


namespace App\Models\Record\Patients;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientMss extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hpatmss';
    protected $primaryKey = 'enccode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'enccode',
        'hpercode',
        'mssikey',
        'mssclass',
        'datemod',
    ];

    protected $casts = [
        'datemod' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'hpercode', 'hpercode');
    }

    // ==========================================
    // Accessor Methods
    // ==========================================

    public function mss_class()
    {
        $classes = [
            'MSSA11111999' => 'Pay',
            'MSSB11111999' => 'Pay',
            'MSSC111111999' => 'PP1',
            'MSSC211111999' => 'PP2',
            'MSSC311111999' => 'PP3',
            'MSSD11111999' => 'Indigent',
        ];

        return $classes[$this->mssikey] ?? '---';
    }

    public function getMssClassAttribute()
    {
        return $this->mss_class();
    }

    public function isIndigent()
    {
        return $this->mssikey === 'MSSD11111999';
    }

    public function isPaying()
    {
        return in_array($this->mssikey, ['MSSA11111999', 'MSSB11111999']);
    }

    public function isPartiallyPaying()
    {
        return in_array($this->mssikey, ['MSSC111111999', 'MSSC211111999', 'MSSC311111999']);
    }
}
