<?php

namespace App\Models\References;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hprov';
    protected $primaryKey = 'provcode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'provcode',
        'provname',
    ];

    public function cities()
    {
        return $this->hasMany(City::class, 'provcode', 'provcode');
    }

    public function barangays()
    {
        return $this->hasMany(Barangay::class, 'provcode', 'provcode');
    }
}
