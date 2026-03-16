<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortalPatientFamily extends Model
{
    use SoftDeletes;

    protected $connection = 'portal';
    protected $table = 'patient_families';

    protected $fillable = [
        'patient_id',
        'name',
        'birthdate',
        'civilstat',
        'relation',
        'education',
        'occupation',
        'income',
        'wchild',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'income' => 'decimal:2',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }
}
