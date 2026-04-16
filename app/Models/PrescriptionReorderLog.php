<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionReorderLog extends Model
{
    protected $fillable = [
        'prescription_data_id',
        'prescription_id',
        'source',
        'reordered_at',
        'performed_by',
        'notes',
    ];

    protected $casts = [
        'reordered_at' => 'datetime',
    ];
}
