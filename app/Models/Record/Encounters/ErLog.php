<?php

namespace App\Models\Record\Encounters;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErLog extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.herlog';
    protected $primaryKey = 'enccode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'enccode',
        'hpercode',
        'erdate',
        'ertime',
        'erdtedis',
        'ertimedis',
        'erstat',
    ];

    protected $casts = [
        'erdate' => 'datetime',
        'erdtedis' => 'datetime',
    ];

    public function encounter()
    {
        return $this->belongsTo(EncounterLog::class, 'enccode', 'enccode');
    }

    public function isDischarged()
    {
        return !is_null($this->erdtedis);
    }

    public function isActive()
    {
        return $this->erstat === 'A';
    }
}
