<?php

namespace App\Models\References;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hsupplier', $primaryKey = 'suppcode', $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false ;
}
