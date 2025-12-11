<?php


namespace App\Models\References;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hbrgy';
    protected $primaryKey = 'bgycode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'bgycode',
        'bgyname',
        'ctycode',
        'provcode',
    ];

    public function city()
    {
        return $this->belongsTo(City::class, 'ctycode', 'ctycode');
    }

    public function province()
    {
        return $this->belongsTo(Province::class, 'provcode', 'provcode');
    }
}
