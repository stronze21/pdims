<?php

namespace App\Models\References;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.htypser', $primaryKey = 'tscode', $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false ;
}
