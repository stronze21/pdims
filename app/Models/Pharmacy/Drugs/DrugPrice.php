<?php

namespace App\Models\Pharmacy\Drugs;

use App\Models\Pharmacy\Drug;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DrugPrice extends Model
{
    use HasFactory;
    use Compoships;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hdmhdrprice';
    protected $primaryKey = 'dmdprdte';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'dmdcomb',
        'dmdctr',
        'dmhdrsub',
        'dmduprice',
        'unitcode',
        'dmdrem',
        'dmdprdte',
        'dmselprice',
        'stockbal',
        'expdate',
        'brandname',
        'stock_id',
        'mark_up',
        'acquisition_cost',
        'has_compounding',
        'compounding_fee',
        'retail_price',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'dmselprice' => 'decimal:2',
        'acquisition_cost' => 'decimal:2',
        'dmduprice' => 'decimal:2',
    ];

    public function acquisition_cost()
    {
        return number_format($this->acquisition_cost, 2);
    }

    public function dmselprice()
    {
        return number_format($this->dmselprice, 2);
    }

    public function drug()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr']);
    }
}
