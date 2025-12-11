<?php

namespace App\Models\Pharmacy\Drugs;

use App\Models\Pharmacy\Drugs\PullOutItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PullOut extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.pharm_pull_outs';

    protected $fillable = [
        'pullout_date',
        'suppcode',
        'pharm_location_id',
    ];

    public function items()
    {
        return $this->hasMany(PullOutItem::class, 'detail_id', 'id');
    }
}
