<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugRoute extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hroute';
    protected $primaryKey = 'rtecode';
    protected $keyType = 'string';

    public function drug()
    {
        return $this->hasMany(Drug::class, 'rtecode', 'rtecode');
    }
}
