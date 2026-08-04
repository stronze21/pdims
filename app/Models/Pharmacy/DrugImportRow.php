<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrugImportRow extends Model
{
    protected $connection = 'hospital';

    protected $table = 'pharm_drug_import_rows';

    protected $guarded = [];

    protected $casts = [
        'raw_json' => 'array',
        'issues_json' => 'array',
        'dmdnost' => 'decimal:2',
        'packvolno' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DrugImportBatch::class, 'batch_id');
    }
}
