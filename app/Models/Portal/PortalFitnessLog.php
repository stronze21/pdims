<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class PortalFitnessLog extends Model
{
    protected $connection = 'portal';
    protected $table = 'fitness_logs';

    protected $fillable = [
        'patient_id',
        'goal_id',
        'title',
        'habit_type',
        'value',
        'unit',
        'logged_at',
        'source_type',
        'notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'logged_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }

    public function goal()
    {
        return $this->belongsTo(PortalFitnessGoal::class, 'goal_id');
    }
}
