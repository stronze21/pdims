<?php

namespace App\Models\Record\Encounters;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpdLog extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hopdlog';
    protected $primaryKey = 'enccode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'enccode',
        'hpercode',
        'opddate',
        'opdtime',
        'opddtedis',
        'opdtimedis',
        'opdstat',
    ];

    protected $casts = [
        'opddate' => 'datetime',
        'opddtedis' => 'datetime',
    ];

    public function encounter()
    {
        return $this->belongsTo(EncounterLog::class, 'enccode', 'enccode');
    }

    public function isDischarged()
    {
        return !is_null($this->opddtedis);
    }

    public function isActive()
    {
        return $this->opdstat === 'A';
    }
}
