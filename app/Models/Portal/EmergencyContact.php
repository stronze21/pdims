<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmergencyContact extends Model
{
    use SoftDeletes;

    protected $connection = 'portal';
    protected $table = 'emergency_contacts';

    protected $fillable = [
        'name',
        'category',
        'phone',
        'label',
        'address',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
