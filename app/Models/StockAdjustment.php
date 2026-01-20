<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.pharm_stock_adjustments';

    protected $fillable = [
        'stock_id',
        'user_id',
        'from_qty',
        'to_qty',
    ];
}
