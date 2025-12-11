<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Generic extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hgen', $primaryKey = 'gencode', $keyType = 'string';
    public $timestamps = false, $incrementing = false;

    protected $fillable = [
        'gencode',
        'gendesc',
        'genstat',
        'genlock',
        'updsw',
        'datemod',
        'entryby',
        'rationale',
        'monitor',
        'interactions',
    ];

    public function group()
    {
        return $this->belongsTo(DrugGroup::class, 'gencode', 'gencode');
    }
}
