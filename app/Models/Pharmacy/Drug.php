<?php

namespace App\Models\Pharmacy;

use App\Models\Pharmacy\Drugs\DrugStock;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Drug extends Model
{
    use Compoships;
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hdmhdr';
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

    public function generic()
    {
        return $this->hasOneThrough(Generic::class, DrugGroup::class, 'grpcode', 'gencode', 'grpcode', 'gencode');
    }

    public function stocks()
    {
        return $this->hasMany(DrugStock::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr']);
    }
}
