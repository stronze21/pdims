<?php

namespace App\Models\Pharmacy\Prescriptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PrescriptionQueueDisplaySetting extends Model
{
    use HasFactory;

    protected $connection = 'webapp';
    protected $table = 'prescription_queue_display_settings';

    protected $fillable = [
        'location_code',
        'display_limit',
        'auto_refresh_seconds',
        'pharmacy_windows',
        'require_cashier',
        'cashier_location',
        'show_patient_name',
        'play_sound_alert',
        'show_estimated_wait',
        'display_mode',
    ];

    protected $casts = [
        'display_limit' => 'integer',
        'auto_refresh_seconds' => 'integer',
        'pharmacy_windows' => 'integer',
        'require_cashier' => 'boolean',
        'show_patient_name' => 'boolean',
        'play_sound_alert' => 'boolean',
        'show_estimated_wait' => 'boolean',
    ];

    public static function getForLocation($locationCode)
    {
        return self::firstOrCreate(
            ['location_code' => $locationCode],
            [
                'display_limit' => 10,
                'auto_refresh_seconds' => 5,
                'pharmacy_windows' => 3,
                'require_cashier' => true,
                'cashier_location' => null,
                'show_patient_name' => false,
                'play_sound_alert' => true,
                'show_estimated_wait' => true,
                'display_mode' => 'split',
            ]
        );
    }
}
