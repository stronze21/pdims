<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Model;

class DrugMetadata extends Model
{
    protected $connection = 'hospital';

    protected $table = 'pharm_drug_metadata';

    protected $guarded = [];
}
