<?php

namespace App\Models\Pharmacy;

use App\Models\Pharmacy\Drugs\DrugStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TempChargedItem extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'pharm_temp_charged_items';

    protected $fillable = [
        'docointkey',
        'stock_id',
        'dmdcomb',
        'dmdctr',
        'chrgcode',
        'loc_code',
        'exp_date',
        'qty_allocated',
        'unit_price',
        'pcchrgcod',
        'enccode',
        'hpercode',
        'dmdprdte',
        'lot_no',
        'charged_at',
        'expires_at',
    ];

    protected $casts = [
        'exp_date' => 'date',
        'dmdprdte' => 'datetime',
        'qty_allocated' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'charged_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function stock(): BelongsTo
    {
        return $this->belongsTo(DrugStock::class, 'stock_id');
    }

    // ==========================================
    // Expiry Management
    // ==========================================

    /**
     * Check if the charged item has expired (24 hours)
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Scope to get expired items
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope to get items expiring soon (within 1 hour)
     */
    public function scopeExpiringSoon($query)
    {
        return $query->whereBetween('expires_at', [now(), now()->addHour()]);
    }

    // ==========================================
    // Query Scopes
    // ==========================================

    /**
     * Scope to get items by charge code
     */
    public function scopeByChargeCode($query, string $chargeCode)
    {
        return $query->where('pcchrgcod', $chargeCode);
    }

    /**
     * Scope to get items by docointkey
     */
    public function scopeByDocointkey($query, string $docointkey)
    {
        return $query->where('docointkey', $docointkey);
    }

    /**
     * Scope to get items by encounter
     */
    public function scopeByEncounter($query, string $enccode)
    {
        return $query->where('enccode', $enccode);
    }

    // ==========================================
    // Stock Management
    // ==========================================

    /**
     * Release the allocated quantity back to stock
     */
    public function releaseToStock(): bool
    {
        try {
            \DB::connection('hospital')->update("
                UPDATE pharm_drug_stocks
                SET stock_bal = stock_bal + ?
                WHERE id = ?
            ", [$this->qty_allocated, $this->stock_id]);

            $this->delete();
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to release stock', [
                'temp_item_id' => $this->id,
                'stock_id' => $this->stock_id,
                'qty' => $this->qty_allocated,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clean up expired temporary charges (restore to stock)
     * Run this via scheduled command every hour
     */
    public static function cleanupExpired(): int
    {
        $expired = self::expired()->get();
        $count = 0;

        foreach ($expired as $item) {
            if ($item->releaseToStock()) {
                $count++;
            }
        }

        \Log::info("Cleaned up {$count} expired temp charged items");
        return $count;
    }

    /**
     * Get summary of allocations by charge code
     */
    public static function getAllocationSummary(string $chargeCode): array
    {
        $items = self::byChargeCode($chargeCode)
            ->with('stock.drug')
            ->get()
            ->groupBy('dmdcomb');

        return $items->map(function ($group) {
            return [
                'total_qty' => $group->sum('qty_allocated'),
                'total_amount' => $group->sum(fn($item) => $item->qty_allocated * $item->unit_price),
                'stock_count' => $group->count(),
                'earliest_expiry' => $group->min('exp_date'),
            ];
        })->toArray();
    }
}
