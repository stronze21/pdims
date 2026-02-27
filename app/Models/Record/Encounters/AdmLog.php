<?php


namespace App\Models\Record\Encounters;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmLog extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hadmlog';
    protected $primaryKey = 'enccode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'enccode',
        'hpercode',
        'admdate',
        'admtime',
        'disdate',
        'distime',
        'admstat',
    ];

    protected $casts = [
        'admdate' => 'datetime',
        'disdate' => 'datetime',
    ];

    public function encounter()
    {
        return $this->belongsTo(EncounterLog::class, 'enccode', 'enccode');
    }

    public function isDischarged()
    {
        return !is_null($this->disdate);
    }

    public function isActive()
    {
        return $this->admstat === 'A';
    }
}
