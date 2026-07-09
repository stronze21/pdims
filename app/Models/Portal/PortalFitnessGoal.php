<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortalFitnessGoal extends Model
{
    use SoftDeletes;

    protected $connection = 'portal';
    protected $table = 'fitness_goals';

    protected $fillable = [
        'patient_id',
        'title',
        'habit_type',
        'unit',
        'target_value',
        'frequency',
        'goal_category',
        'source_type',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }

    public function logs()
    {
        return $this->hasMany(PortalFitnessLog::class, 'goal_id');
    }

    public function reminders()
    {
        return $this->hasMany(PortalFitnessReminder::class, 'goal_id');
    }
}
