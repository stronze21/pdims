<?php

namespace App\Models\Pharmacy\Drugs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugStockLogCounter extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.pharm_drug_stock_log_counters';

    protected $fillable = [
        'date_start',
        'date_end',
        'status',
        'user_id',
    ];
}
