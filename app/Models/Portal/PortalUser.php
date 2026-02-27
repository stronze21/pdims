<?php

namespace App\Models\Portal;

use App\Models\Record\Patients\Patient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class PortalUser extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $connection = 'portal';
    protected $table = 'portal_users';

    protected $fillable = [
        'hpercode',
        'hospital_no',
        'patlast',
        'patfirst',
        'patmiddle',
        'patsuffix',
        'email',
        'contact_no',
        'password',
        'patbdate',
        'patsex',
        'status',
        'verified_by',
        'verified_at',
        'reject_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'patbdate' => 'date',
            'verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'hpercode', 'hpercode');
    }

    public function getFullnameAttribute(): string
    {
        $suffix = $this->patsuffix ? ' ' . $this->patsuffix : '';
        $middle = $this->patmiddle ? ' ' . mb_substr($this->patmiddle, 0, 1) . '.' : '';

        return trim("{$this->patlast}, {$this->patfirst}{$middle}{$suffix}");
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
