<?php

namespace App\Models\Record\Prescriptions;

use App\Models\Hospital\Employee;
use App\Models\Record\Admission\PatientRoom;
use App\Models\Record\Encounters\AdmissionLog;
use App\Models\Record\Encounters\ErLog;
use App\Models\Record\Encounters\OpdLog;
use App\Models\Record\Patients\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;

    protected $connection = 'webapp';
    protected $table = 'webapp.dbo.prescription';

    public function data()
    {
        return $this->hasMany(PrescriptionData::class, 'presc_id', 'id')->with('dm')->with('employee')->latest('updated_at');
    }

    public function data_active()
    {
        return $this->hasMany(PrescriptionData::class, 'presc_id', 'id')->with('dm')->with('employee')->where('stat', 'A');
    }

    public function active_g24()
    {
        return $this->hasMany(PrescriptionData::class, 'presc_id', 'id')->with('dm')->where('stat', 'A')->where('order_type', 'G24');
    }

    public function active_or()
    {
        return $this->hasMany(PrescriptionData::class, 'presc_id', 'id')->with('dm')->where('stat', 'A')->where('order_type', 'OR');
    }

    public function active_basic()
    {
        return $this->hasMany(PrescriptionData::class, 'presc_id', 'id')->with('dm')->where('stat', 'A')->where(function (Builder $query) {
            // $query->whereRaw("order_type IS NULL OR order_type = 'null'");
            $query->whereNull('order_type')->orWhere('order_type', '');
        });
    }

    public function g24()
    {
        return $this->data_active()->where('order_type', 'G24')->count();
    }

    public function or()
    {
        return $this->data_active()->where('order_type', 'OR')->count();
    }

    public function active_opd()
    {
        return $this->belongsTo(OpdLog::class, 'enccode', 'enccode')
            ->where('opdstat', 'A')
            ->has('patient')->has('provider')->has('service_type')
            ->with('patient')->with('provider')
            ->with('service_type');
    }

    public function active_er()
    {
        return $this->belongsTo(ErLog::class, 'enccode', 'enccode')
            ->where('erstat', 'A')
            ->has('patient')->has('provider')
            ->with('patient')->with('provider')
            ->with('service_type');
    }

    public function active_adm()
    {
        return $this->belongsTo(AdmissionLog::class, 'enccode', 'enccode')
            ->where('admstat', 'A')
            ->has('patient')->has('patient_room')
            ->with('patient')->with('patient_room');
    }

    public function opd()
    {
        return $this->belongsTo(OpdLog::class, 'enccode', 'enccode')
            ->has('patient')->has('provider')
            ->with('patient')->with('provider')
            ->with('service_type');
    }

    public function er()
    {
        return $this->belongsTo(ErLog::class, 'enccode', 'enccode')
            ->has('patient')->has('provider')
            ->with('patient')->with('provider')
            ->with('service_type');
    }

    public function adm()
    {
        return $this->belongsTo(AdmissionLog::class, 'enccode', 'enccode')
            ->has('patient')->has('patient_room')
            ->with('patient')->with('patient_room');
    }

    public function encounter()
    {
        return $this->belongsTo(AdmissionLog::class, 'enccode', 'enccode');
    }

    public function adm_pat_room()
    {
        return $this->hasOneThrough(PatientRoom::class, AdmissionLog::class, 'enccode', 'enccode', 'enccode')
            ->with('ward')
            ->with('room')
            ->latest('hprdate');
    }

    public function opd_patient()
    {
        return $this->hasOneThrough(Patient::class, OpdLog::class, 'hpercode', 'hpercode', 'enccode');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'empid', 'employeeid')->with('dept')->with('provider');
    }
}
