<?php

namespace App\Models\Pharmacy;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugSub extends Model
{
    use HasFactory;
    use Compoships;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hdmhdrsub';
}
