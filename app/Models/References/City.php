<?php


namespace App\Models\References;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hcity';
    protected $primaryKey = 'ctycode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ctycode',
        'ctyname',
        'provcode',
    ];

    public function province()
    {
        return $this->belongsTo(Province::class, 'provcode', 'provcode');
    }

    public function barangays()
    {
        return $this->hasMany(Barangay::class, 'ctycode', 'ctycode');
    }
}
