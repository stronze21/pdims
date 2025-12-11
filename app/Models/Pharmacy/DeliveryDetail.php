<?php

namespace App\Models\Pharmacy;

use App\Models\References\ChargeCode;
use App\Models\References\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeliveryDetail extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.pharm_delivery_details';

    protected $fillable = [
        'po_no',
        'si_no',
        'pharm_location_id',
        'user_id',
        'delivery_date',
        'suppcode',
        'delivery_type',
        'charge_code',
    ];


    public function items()
    {
        return $this->hasMany(DeliveryItems::class, 'delivery_id', 'id')->with('drug');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'suppcode', 'suppcode');
    }

    public function charge()
    {
        return $this->belongsTo(ChargeCode::class, 'charge_code', 'chrgcode');
    }

}
