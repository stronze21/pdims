<?php

namespace App\Models\Pharmacy\Drugs;

use App\Models\Pharmacy\Drugs\PullOut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PullOutItem extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.pharm_pull_out_items';

    protected $fillable = [
        'detail_id',
        'stock_id',
        'pullout_qty',
    ];

    public function details()
    {
        return $this->belongsTo(PullOut::class, 'detail_id', 'id');
    }
}
