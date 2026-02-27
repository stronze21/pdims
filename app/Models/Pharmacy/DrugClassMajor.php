<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugClassMajor extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.dmmajor';
    protected $primaryKey = 'dmcode';
    protected $keyType = 'string';
}
