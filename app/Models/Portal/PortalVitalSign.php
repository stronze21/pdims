<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class PortalVitalSign extends Model
{
    protected $connection = 'portal';
    protected $table = 'vital_signs';

    protected $fillable = [
        'vital_sign',
        'description',
        'unit',
        'low',
        'high',
    ];

    protected $casts = [
        'low' => 'decimal:2',
        'high' => 'decimal:2',
    ];
}
