<?php

namespace App\Models\Pharmacy\Dispensing;

use App\Models\Hospital\Department;
use App\Models\Hospital\Employee;
use App\Models\Pharmacy\Dispensing\DrugOrderReturn;
use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Record\Encounters\EncounterLog;
use App\Models\Record\Patients\Patient;
use App\Models\Record\Prescriptions\Prescription;
use App\Models\Record\Prescriptions\PrescriptionData;
use App\Models\References\ChargeCode;
use App\Models\User;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugOrder extends Model
{
    use Compoships;
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hrxo';
    protected $primaryKey = 'docointkey';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'docointkey',
        'enccode',
        'hpercode',
        'rxooccid',
        'rxoref',
        'dmdcomb',
        'dmdctr',
        'repdayno1',
        'rxostatus',
        'rxolock',
        'rxoupsw',
        'rxoconfd',
        'estatus',
        'entryby',
        'ordcon',
        'orderupd',
        'locacode',
        'orderfrom',
        'issuetype',
        'has_tag',
        'tx_type',
        'ris',
        'pchrgqty',
        'qtyissued',
        'pchrgup',
        'pcchrgamt',
        'pcchrgcod',
        'dodate',
        'dotime',
        'dodtepost',
        'dotmepost',
        'dmdprdte',
        'exp_date',
        'loc_code',
        'item_id',
        'remarks',
        'prescription_data_id',
        'prescribed_by',
        'deptcode',
        'qtybal',
        'order_by'

    ];

    protected $casts = [
        'dodate' => 'datetime',
        'dotime' => 'datetime',
        'dodtepost' => 'datetime',
        'dotmepost' => 'datetime',
        'exp_date' => 'date',
        'pchrgqty' => 'decimal:2',
        'qtyissued' => 'decimal:2',
        'pchrgup' => 'decimal:2',
        'pcchrgamt' => 'decimal:2',
        'ris' => 'boolean',
    ];

    // Relationships
    public function encounter()
    {
        return $this->belongsTo(EncounterLog::class, 'enccode', 'enccode');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'hpercode', 'hpercode');
    }

    public function drug()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr']);
    }

    public function chargeCode()
    {
        return $this->belongsTo(ChargeCode::class, 'orderfrom', 'chrgcode');
    }

    public function prescriptionData()
    {
        return $this->belongsTo(PrescriptionData::class, 'prescription_data_id');
    }

    // Helper methods
    public function getStatusAttribute()
    {
        if (!$this->pcchrgcod && $this->estatus == 'U') {
            return 'pending';
        } elseif ($this->pcchrgcod && $this->estatus == 'P') {
            return 'charged';
        } elseif ($this->estatus == 'S') {
            return 'issued';
        }
        return 'unknown';
    }

    public function getStatusBadgeAttribute()
    {
        return match ($this->getStatusAttribute()) {
            'pending' => 'warning',
            'charged' => 'info',
            'issued' => 'success',
            default => 'ghost'
        };
    }

    public function isPending()
    {
        return $this->estatus == 'U' && !$this->pcchrgcod;
    }

    public function isCharged()
    {
        return $this->estatus == 'P' && $this->pcchrgcod;
    }

    public function isIssued()
    {
        return $this->estatus == 'S';
    }

    public function canBeDeleted()
    {
        return $this->isPending();
    }

    public function canBeUpdated()
    {
        return $this->isPending();
    }

    public function canBeReturned()
    {
        return $this->isIssued() && $this->qtyissued > 0;
    }
}
