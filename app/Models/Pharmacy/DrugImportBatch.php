<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrugImportBatch extends Model
{
    use HasUuids;

    protected $connection = 'hospital';

    protected $table = 'pharm_drug_import_batches';

    protected $guarded = [];

    protected $casts = [
        'defaults_json' => 'array',
        'committed_at' => 'datetime',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(DrugImportRow::class, 'batch_id');
    }
}
