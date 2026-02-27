<?php

namespace App\Models\Pharmacy;

use App\Models\References\ChargeCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZeroBillingCharge extends Model
{

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.pharm_zero_billing_charges';

    protected $fillable = [
        'chrgcode',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public static function isZeroBilling(string $chrgcode): bool
    {
        return self::where('chrgcode', $chrgcode)
            ->where('is_active', true)
            ->exists();
    }

    public static function getActiveCodes(): array
    {
        return self::where('is_active', true)
            ->pluck('chrgcode')
            ->toArray();
    }

    public function chargeCode()
    {
        return $this->belongsTo(ChargeCode::class, 'chrgcode', 'chrgcode');
    }
}
