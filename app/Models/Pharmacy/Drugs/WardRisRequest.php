<?php

namespace App\Models\Pharmacy\Drugs;

use Carbon\Carbon;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\References\ChargeCode;
use App\Models\Pharmacy\PharmLocation;
use App\Models\Pharmacy\RisWard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WardRisRequest extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.pharm_ward_ris_requests';

    protected $fillable = [
        'trans_no',
        'stock_id',
        'ris_location_id',
        'dmdcomb',
        'dmdctr',
        'loc_code',
        'chrgcode',
        'issued_qty',
        'return_qty',
        'issued_by',
        'trans_stat',
        'dmdprdte',
    ];

    public function drug()
    {
        return $this->belongsTo(DrugStock::class, 'stock_id', 'id');
    }

    public function charge()
    {
        return $this->belongsTo(ChargeCode::class, 'chrgcode', 'chrgcode');
    }

    public function location()
    {
        return $this->belongsTo(PharmLocation::class, 'loc_code', 'id');
    }

    public function ward()
    {
        return $this->belongsTo(RisWard::class, 'ris_location_id', 'id');
    }

    public function created_at()
    {
        return Carbon::parse($this->created_at)->format('M d, Y G:i A');
    }
}
