<?php

namespace App\Models\Pharmacy\Drugs;

use App\Models\User;
use App\Models\Pharmacy\Drug;
use Awobaz\Compoships\Compoships;
use App\Models\Pharmacy\DrugPrice;
use App\Models\References\ChargeCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DrugEmergencyPurchase extends Model
{
    use HasFactory;
    use Compoships;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.pharm_drug_emergency_purchases';

    protected $fillable = [
        'or_no',
        'pharmacy_name',
        'user_id',
        'purchase_date',
        'dmdcomb',
        'dmdctr',
        'qty',
        'dmdprdte',
        'unit_price',
        'markup_price',
        'total_amount',
        'retail_price',
        'lot_no',
        'expiry_date',
        'charge_code',
        'pharm_location_id',
        'remarks',
    ];

    public function drug()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr']);
    }

    public function current_price()
    {
        return $this->belongsTo(DrugPrice::class, 'dmdprdte', 'dmdprdte');
    }

    public function charge()
    {
        return $this->belongsTo(ChargeCode::class, 'charge_code', 'chrgcode');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
