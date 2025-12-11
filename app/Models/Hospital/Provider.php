<?php

namespace App\Models\Hospital;

use App\Models\Hospital\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Provider extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hprovider', $primaryKey = 'licno', $keyType = 'string';

    public function emp()
    {
        return $this->belongsTo(Employee::class, 'employeeid', 'employeeid');
    }
}
