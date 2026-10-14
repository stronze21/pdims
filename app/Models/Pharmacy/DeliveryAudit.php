<?php
namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Model;

class DeliveryAudit extends Model
{
    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.pharm_delivery_audits';
    public $timestamps = false;
    protected $casts = ['entries' => 'array', 'created_at' => 'datetime'];
}
