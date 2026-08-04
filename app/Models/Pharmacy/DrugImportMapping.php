<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Model;

class DrugImportMapping extends Model
{
    protected $connection = 'hospital';

    protected $table = 'pharm_drug_import_mappings';

    protected $guarded = [];
}
