<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugForm extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hform';
    protected $primaryKey = 'formcode';
    protected $keyType = 'string';

    public function drug()
    {
        return $this->hasMany(Drug::class, 'formcode', 'formcode');
    }
}
