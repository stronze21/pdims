<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionReorderRunLog extends Model
{
    protected $fillable = [
        'source',
        'status',
        'dry_run',
        'reordered_count',
        'run_at',
        'performed_by',
        'notes',
    ];

    protected $casts = [
        'dry_run' => 'boolean',
        'run_at' => 'datetime',
    ];
}
