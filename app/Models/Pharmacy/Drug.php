<?php

namespace App\Models\Pharmacy;

use App\Models\Pharmacy\DrugRoute;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\DrugStrength;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    use Compoships;
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hdmhdr';
    protected $primaryKey = ['dmdcomb', 'dmdctr'];
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'dmdcomb',
        'dmdctr',
        'drug_concat',
        'dmdnost',
        'strecode',
        'formcode',
        'rtecode',
        'brandname',
        'dmdrem',
        'dmdrxot',
        'gencode'
    ];

    public function getDrugNameAttribute()
    {
        $parts = explode('_,', $this->drug_concat);
        return implode(' ', $parts);
    }

    public function drug_concat()
    {
        $parts = explode('_,', $this->drug_concat);
        return implode(' ', $parts);
    }

    public function drug_group()
    {
        return $this->belongsTo(DrugGroup::class, 'grpcode', 'grpcode');
    }

    public function route()
    {
        return $this->belongsTo(DrugRoute::class, 'rtecode', 'rtecode');
    }

    public function form()
    {
        return $this->belongsTo(DrugForm::class, 'formcode', 'formcode');
    }

    public function strength()
    {
        return $this->belongsTo(DrugStrength::class, 'strecode', 'strecode');
    }

    public function generic()
    {
        return $this->hasOneThrough(Generic::class, DrugGroup::class, 'grpcode', 'gencode', 'grpcode', 'gencode');
    }

    public function stocks()
    {
        return $this->hasMany(DrugStock::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr']);
    }
}
