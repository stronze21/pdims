<?php

namespace App\Models\Pharmacy\Drugs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumptionLogDetail extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.pharm_consumption_log_details';

    protected $fillable = [
        'consumption_from',
        'consumption_to',
        'status',
        'entry_by',
        'closed_by',
        'loc_code',
    ];

    public function logs()
    {
        return $this->hasMany(DrugStockLog::class, 'consumption_id', 'id');
    }
}
