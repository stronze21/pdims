<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PharmLocation extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Notifiable;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.pharm_locations';

    protected $fillable = [
        'description',
    ];
}
