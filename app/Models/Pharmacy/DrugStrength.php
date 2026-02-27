<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugStrength extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hstre';
    protected $primaryKey = 'strecode';
    protected $keyType = 'string';


    public function drug()
    {
        return $this->hasMany(Drug::class, 'strecode', 'strecode');
    }
}
