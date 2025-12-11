<?php

namespace App\Models\Pharmacy\Prescriptions;

use App\Models\Hospital\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PrescriptionQueueLog extends Model
{
    use HasFactory;

    protected $connection = 'webapp';
    protected $table = 'prescription_queue_logs';

    protected $fillable = [
        'queue_id',
        'status_from',
        'status_to',
        'changed_by',
        'remarks',
    ];

    public function queue()
    {
        return $this->belongsTo(PrescriptionQueue::class, 'queue_id', 'id');
    }

    public function changer()
    {
        return $this->belongsTo(Employee::class, 'changed_by', 'employeeid');
    }

    public function getStatusChangeLabel()
    {
        return ($this->status_from ?? 'New') . ' → ' . $this->status_to;
    }
}
