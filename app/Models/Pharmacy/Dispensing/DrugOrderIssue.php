<?php

namespace App\Models\Pharmacy\Dispensing;

use App\Models\User;
use App\Models\Pharmacy\Drug;
use App\Models\Hospital\Employee;
use Awobaz\Compoships\Compoships;
use App\Models\Pharmacy\DrugPrice;
use App\Models\References\ChargeCode;
use App\Models\Record\Patients\Patient;
use Illuminate\Database\Eloquent\Model;
use App\Models\Record\Admission\PatientRoom;
use App\Models\Record\Encounters\AdmissionLog;
use App\Models\Record\Encounters\EncounterLog;
use App\Models\Pharmacy\Dispensing\DrugOrderReturn;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DrugOrderIssue extends Model
{
    use Compoships;
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hrxoissue', $primaryKey = 'docointkey', $keyType = 'string';
    public $timestamps = false, $incrementing = false;

    protected $fillable = [
        'docointkey',
        'enccode',
        'hpercode',
        'dmdcomb',
        'dmdctr',
        'issuedte',
        'issuetme',
        'qty',
        'uomcode', //NULL
        'issuedby',
        'status', //A
        'rxolock', //N
        'datemod', //NULL
        'updsw', //N
        'confdl', //N
        'entryby',
        'locacode', //PHARM
        'dmdprdte',
        'issuedfrom',
        'pcchrgcod',
        'chrgcode',
        'pchrgup', //NULL
        'tagg', //NULL
        'taggdte', //NULL
        'enctype', //NULL
        'othcrge', //NULL
        'issuetype', //c
        'amt_paid', //NULL
        'ris',
        'prescription_data_id',
        'prescribed_by',
    ];

    public function current_price()
    {
        return $this->belongsTo(DrugPrice::class, 'dmdprdte', 'dmdprdte');
    }

    public function dm()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr'])
            ->with('generic')
            ->with('strength')
            ->with('form');
    }

    public function charge()
    {
        return $this->belongsTo(ChargeCode::class, 'issuedfrom', 'chrgcode');
    }

    public function returns()
    {
        return $this->hasMany(DrugOrderReturn::class, 'docointkey', 'docointkey');
    }

    public function issuer()
    {
        return $this->belongsTo(Employee::class, 'issuedby', 'employeeid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'issuedby', 'employeeid');
    }

    public function issued_date()
    {
        return date('m/d/Y H:i A', strtotime($this->issuedte));
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'hpercode', 'hpercode');
    }

    public function encounter()
    {
        return $this->belongsTo(EncounterLog::class, 'enccode', 'enccode');
    }

    public function adm_pat_room()
    {
        return $this->hasOneThrough(PatientRoom::class, AdmissionLog::class, 'enccode', 'enccode', 'enccode')
            ->with('ward')
            ->with('room');
    }

    public function main_order()
    {
        return $this->belongsTo(DrugOrder::class, 'docointkey', 'docointkey');
    }
}
