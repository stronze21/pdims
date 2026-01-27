<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NonPnfDrug extends Model
{
    use SoftDeletes;

    protected $table = 'pharm_non_pnf_drugs';

    protected $fillable = [
        'medicine_name',
        'dose',
        'unit',
        'is_active',
        'remarks',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope to get only active drugs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to search drugs
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('medicine_name', 'like', "%{$search}%")
                ->orWhere('dose', 'like', "%{$search}%")
                ->orWhere('unit', 'like', "%{$search}%");
        });
    }

    /**
     * Get formatted name with dose
     */
    public function getFullNameAttribute()
    {
        $name = $this->medicine_name;
        if ($this->dose) {
            $name .= ' ' . $this->dose;
        }
        if ($this->unit) {
            $name .= ' (' . $this->unit . ')';
        }
        return $name;
    }
}
