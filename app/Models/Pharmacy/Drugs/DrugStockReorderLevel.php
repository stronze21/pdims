<?php

namespace App\Models\Pharmacy\Drugs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugStockReorderLevel extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.pharm_drug_stock_reorder_levels';

    protected $fillable = [
        'dmdcomb',
        'dmdctr',
        'reorder_point',
        'user_id',
        'loc_code',
    ];
}
