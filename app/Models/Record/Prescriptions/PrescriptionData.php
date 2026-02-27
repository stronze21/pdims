<?php

namespace App\Models\Record\Prescriptions;

use App\Models\Hospital\Employee;
use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Record\Prescriptions\Prescription;
use App\Models\Record\Prescriptions\PrescriptionDataIssued;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionData extends Model
{
    use HasFactory;
    use Compoships;

    protected $connection = 'webapp';
    protected $table = 'webapp.dbo.prescription_data';

    public function issued()
    {
        return $this->hasMany(PrescriptionDataIssued::class, 'presc_data_id');
    }

    public function dm()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr']);
    }

    public function dmd()
    {
        return $this->join('hospital2.dbo.hdmhdr as hdm', 'hdm.dmdcomb', 'LIKE', 'webapp.dbo.prescription_data.dmdcomb');
    }

    public function item()
    {
        return $this->belongsTo(DrugStock::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr'])->where('stock_bal', '>', '0')->orderBy('exp_date', 'ASC');
    }

    public function rx()
    {
        return $this->belongsTo(Prescription::class, 'presc_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'entry_by', 'employeeid')->with('dept')->with('provider');
    }
}
