<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortalFitnessReminder extends Model
{
    use SoftDeletes;

    protected $connection = 'portal';
    protected $table = 'fitness_reminders';

    protected $fillable = [
        'patient_id',
        'goal_id',
        'title',
        'habit_type',
        'time_of_day',
        'days_of_week',
        'message',
        'is_enabled',
        'source_type',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'is_enabled' => 'boolean',
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
