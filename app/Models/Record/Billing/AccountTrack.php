<?php


namespace App\Models\Record\Billing;

use App\Models\Record\Encounters\EncounterLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTrack extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hactrack';
    protected $primaryKey = 'enccode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'enccode',
        'hpercode',
        'billstat',
        'billdate',
        'billtime',
    ];

    protected $casts = [
        'billdate' => 'datetime',
    ];

    public function encounter()
    {
        return $this->belongsTo(EncounterLog::class, 'enccode', 'enccode');
    }

    public function isBilled()
    {
        return in_array($this->billstat, ['02', '03']);
    }

    public function getBillingStatus()
    {
        $statuses = [
            '00' => 'Unbilled',
            '01' => 'Partially Billed',
            '02' => 'Fully Billed',
            '03' => 'Closed',
        ];

        return $statuses[$this->billstat] ?? 'Unknown';
    }
}
