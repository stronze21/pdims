<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortalPrescriptionRefill extends Model
{
    use SoftDeletes;

    protected $connection = 'portal';
    protected $table = 'prescription_refill_requests';

    protected $fillable = [
        'patient_id',
        'hpercode',
        'enccode',
        'prescription_id',
        'prescription_data_id',
        'dmdcomb',
        'dmdctr',
        'drug_name',
        'qty_requested',
        'remarks',
        'status',
        'admin_remarks',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'qty_requested' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(PortalPatient::class, 'patient_id');
    }
}
