<?php

namespace App\Models\Pharmacy\Dispensing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderChargeCode extends Model
{
    use HasFactory;

    protected $connection = 'hospital', $table = 'hospital.dbo.charge_code';
    public $timestamps = ["created_at"]; //only want to used created_at column
    const UPDATED_AT = null; //and updated by default null set

    protected $fillable = [
        'charge_desc',
    ];

    public function orders()
    {
        return $this->hasMany(DrugOrder::class, 'pcchrgcod', 'id');
    }
}
