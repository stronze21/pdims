<?php

namespace App\Models\Pharmacy;

use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\DrugClass1;
use App\Models\Pharmacy\DrugClass2;
use App\Models\Pharmacy\DrugClass3;
use App\Models\Pharmacy\DrugClass4;
use App\Models\Pharmacy\Generic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DrugGroup extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hdruggrp';
    protected $primaryKey = 'grpcode';
    protected $keyType = 'string';
    public $timestamps = false, $incrementing = false;

    protected $fillable = [
        'grpcode',
        'grpstat',
        'grplock',
        'grpupsw',
        'grpdtmd',
        'dmcode',
        'dms2key',
        'dms3key',
        'dms4key',
        'gencode',
    ];

    public function generic()
    {
        return $this->belongsTo(Generic::class, 'gencode', 'gencode');
    }

    public function drug()
    {
        return $this->hasMany(Drug::class, 'grpcode', 'grpcode');
    }

    public function submajor()
    {
        return $this->belongsTo(DrugClassMajor::class, 'dmcode', 'dmcode');
    }

    public function sub1()
    {
        return $this->belongsTo(DrugClass1::class, 'dms1key', 'dms1key');
    }

    public function sub2()
    {
        return $this->belongsTo(DrugClass2::class, 'dms2key', 'dms2key');
    }

    public function sub3()
    {
        return $this->belongsTo(DrugClass3::class, 'dms3key', 'dms3key');
    }

    public function sub4()
    {
        return $this->belongsTo(DrugClass4::class, 'dms4key', 'dms4key');
    }
}
