<?php

namespace App\Models\Record\Prescriptions;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionDataFrequency extends Model
{
    use HasFactory;

    protected $connection = 'webapp';
    protected $table = 'webapp.dbo.prescription_data_duration_ext';
}
