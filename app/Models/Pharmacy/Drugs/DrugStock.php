<?php

namespace App\Models\Pharmacy\Drugs;

use Carbon\Carbon;
use App\Models\Pharmacy\Drug;
use Awobaz\Compoships\Compoships;
use App\Models\References\ChargeCode;
use App\Models\Pharmacy\PharmLocation;
use Illuminate\Database\Eloquent\Model;
use App\Models\Pharmacy\Drugs\DrugStockIssue;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DrugStock extends Model
{
    use HasFactory;
    use Compoships;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.pharm_drug_stocks';

    protected $fillable = [
        'dmdcomb',
        'dmdctr',
        'loc_code',
        'chrgcode',
        'exp_date',
        'stock_bal',
        'beg_bal',
        'retail_price',
        'dmdprdte',
        'drug_concat',
        'dmdnost',
        'strecode',
        'formcode',
        'rtecode',
        'brandname',
        'dmdrem',
        'dmdrxot',
        'gencode',

        'lot_no',
    ];

    protected $casts = [
        'exp_date' => 'date',
        'stock_bal' => 'decimal:2',
        'retail_price' => 'decimal:2',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function drug()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr']);
    }

    public function chargeCode()
    {
        return $this->belongsTo(ChargeCode::class, 'chrgcode', 'chrgcode');
    }

    public function currentPrice()
    {
        return $this->hasOne(DrugPrice::class, 'dmdprdte', 'dmdprdte');
    }

    public function issues()
    {
        return $this->hasMany(DrugStockIssue::class, 'stock_id');
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * Check if the stock is expired
     */
    public function isExpired()
    {
        return $this->exp_date < now();
    }

    /**
     * Check if the stock is near expiry
     * Default: 168 days (24 weeks / ~6 months)
     */
    public function isNearExpiry($days = 168)
    {
        return $this->exp_date->diffInDays(now(), false) > -$days &&
            $this->exp_date->diffInDays(now(), false) <= 0;
    }

    /**
     * Check if there's available stock
     */
    public function hasStock()
    {
        return $this->stock_bal > 0;
    }

    /**
     * Get expiry status
     */
    public function getExpiryStatusAttribute()
    {
        if ($this->isExpired()) {
            return 'expired';
        } elseif ($this->isNearExpiry()) {
            return 'near-expiry';
        }
        return 'good';
    }

    /**
     * Get badge class for expiry status
     */
    public function getExpiryBadgeAttribute()
    {
        return match ($this->getExpiryStatusAttribute()) {
            'expired' => 'error',
            'near-expiry' => 'warning',
            default => 'success'
        };
    }

    /**
     * Get clean drug name without underscores and commas
     */
    public function getDrugNameAttribute()
    {
        $parts = explode('_,', $this->drug_concat);
        return implode(' ', $parts);
    }

    /**
     * Get formatted drug with generic and brand separated
     */
    public function getFormattedDrugAttribute()
    {
        $parts = explode('_,', $this->drug_concat);
        return [
            'generic' => $parts[0] ?? '',
            'brand' => $parts[1] ?? ''
        ];
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiryAttribute()
    {
        return $this->exp_date->diffInDays(now(), false);
    }

    /**
     * Get formatted expiry date
     */
    public function getFormattedExpiryAttribute()
    {
        return $this->exp_date->format('M d, Y');
    }

    /**
     * Check if stock is available for dispensing
     */
    public function isAvailableForDispensing()
    {
        return $this->hasStock() && !$this->isExpired();
    }

    /**
     * Get stock status description
     */
    public function getStatusDescriptionAttribute()
    {
        if (!$this->hasStock()) {
            return 'Out of Stock';
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        if ($this->isNearExpiry(30)) { // 30 days
            return 'Expiring Soon';
        }

        if ($this->stock_bal < 10) {
            return 'Low Stock';
        }

        return 'Available';
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope for available stocks only
     */
    public function scopeAvailable($query)
    {
        return $query->where('stock_bal', '>', 0)
            ->where('exp_date', '>', now());
    }

    /**
     * Scope for specific location
     */
    public function scopeInLocation($query, $locCode)
    {
        return $query->where('loc_code', $locCode);
    }

    /**
     * Scope for near expiry stocks
     */
    public function scopeNearExpiry($query, $days = 168)
    {
        return $query->where('exp_date', '<=', now()->addDays($days))
            ->where('exp_date', '>', now());
    }

    /**
     * Scope for expired stocks
     */
    public function scopeExpired($query)
    {
        return $query->where('exp_date', '<=', now())
            ->where('stock_bal', '>', 0);
    }

    /**
     * Scope for low stock
     */
    public function scopeLowStock($query, $threshold = 10)
    {
        return $query->where('stock_bal', '<=', $threshold)
            ->where('stock_bal', '>', 0);
    }

    public function charge()
    {
        return $this->belongsTo(ChargeCode::class, 'chrgcode', 'chrgcode');
    }

    public function location()
    {
        return $this->belongsTo(PharmLocation::class, 'loc_code', 'id');
    }

    public function balance()
    {
        return number_format($this->stock_bal ?? 0, 0);
    }

    public function expiry()
    {
        if (Carbon::parse($this->exp_date)->diffInDays(now(), false) >= 1 && $this->stock_bal > 0) {
            $badge = '<span class="badge badge-xs text-nowrap whitespace-nowrap badge-error">' . Carbon::create($this->exp_date)->format('Y-m-d') . '</span>';
        } elseif (Carbon::parse($this->exp_date)->diffInDays(now(), false) > -182.5 && $this->stock_bal > 0) {
            $badge = '<span class="badge badge-xs text-nowrap whitespace-nowrap badge-warning">' . Carbon::create($this->exp_date)->format('Y-m-d') . '</span>';
        } elseif ($this->stock_bal < 1) {
            $badge = '<span class="badge badge-xs text-nowrap whitespace-nowrap badge-ghost">' . Carbon::create($this->exp_date)->format('Y-m-d') . '</span>';
        } elseif (Carbon::parse($this->exp_date)->diffInDays(now(), false) <= -182.5) {
            $badge = '<span class="badge badge-xs text-nowrap whitespace-nowrap badge-success">' . Carbon::create($this->exp_date)->format('Y-m-d') . '</span>';
        }

        return $badge;
    }

    public function prices()
    {
        // return $this->hasMany(DrugPrice::class, ['dmdcomb', 'dmdctr', 'dmhdrsub', 'expdate'], ['dmdcomb', 'dmdctr', 'chrgcode', 'exp_date']);
        return $this->hasMany(DrugPrice::class, ['dmdcomb', 'dmdctr', 'dmhdrsub'], ['dmdcomb', 'dmdctr', 'chrgcode'])
            ->where('expdate', 'LIKE', '%' . $this->exp_date)
            ->latest('dmdprdte');
    }

    public function stock_prices()
    {
        return $this->hasMany(DrugPrice::class, 'stock_id', 'id')->latest('dmdprdte');
    }

    public function issued_drugs()
    {
        return $this->hasMany(DrugStockIssue::class, 'stock_id');
    }

    public function current_price()
    {
        return $this->belongsTo(DrugPrice::class, 'dmdprdte', 'dmdprdte');
    }

    public function drug_concat()
    {
        $concat = explode('_', $this->drug_concat);

        return implode("", $concat);
    }
}
