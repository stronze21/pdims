<?php

namespace App\Models\Record\Encounters;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EncounterDiagnosis extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hencdiag';
    protected $primaryKey = 'enccode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'enccode',
        'hpercode',
        'diagcode',
        'diagtext',
    ];

    public function encounter()
    {
        return $this->belongsTo(EncounterLog::class, 'enccode', 'enccode');
    }
}
