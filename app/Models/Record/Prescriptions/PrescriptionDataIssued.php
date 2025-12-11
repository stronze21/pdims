<?php

namespace App\Models\Record\Prescriptions;

use App\Models\Pharmacy\Dispensing\DrugOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionDataIssued extends Model
{
    use HasFactory;

    protected $connection = 'webapp';
    protected $table = 'webapp.dbo.prescription_data_issued';
    const UPDATED_AT = 'update_at';

    public $fillable = [
        'presc_data_id',
        'docointkey',
        'qtyissued',
    ];

    public function rxo()
    {
        return $this->belongsTo(DrugOrder::class, 'docointkey');
    }

    public function rx()
    {
        return $this->belongsTo(Prescription::class, 'presc_id', 'id');
    }
}
