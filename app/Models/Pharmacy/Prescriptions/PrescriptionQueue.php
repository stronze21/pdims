<?php

namespace App\Models\Pharmacy\Prescriptions;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Hospital\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Record\Patients\Patient;
use App\Models\Record\Prescriptions\Prescription;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PrescriptionQueue extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'webapp';
    protected $table = 'prescription_queues';

    protected $fillable = [
        'prescription_id',
        'enccode',
        'hpercode',
        'queue_number',
        'queue_prefix',
        'sequence_number',
        'location_code',
        'queue_status',
        'priority',
        'queued_at',
        'called_at',
        'preparing_at',
        'charging_at',
        'ready_at',
        'dispensed_at',
        'cancelled_at',
        'prepared_by',
        'charged_by',
        'dispensed_by',
        'cancelled_by',
        'charge_slip_no',
        'cancellation_reason',
        'remarks',
        'estimated_wait_minutes',
        'created_from',
        'assigned_window',
        'skip_count',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'called_at' => 'datetime',
        'preparing_at' => 'datetime',
        'charging_at' => 'datetime',
        'ready_at' => 'datetime',
        'dispensed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'estimated_wait_minutes' => 'integer',
        'sequence_number' => 'integer',
        'skip_count' => 'integer',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function prescription()
    {
        return $this->belongsTo(Prescription::class, 'prescription_id', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'hpercode', 'hpercode');
    }

    public function preparer()
    {
        return $this->belongsTo(Employee::class, 'prepared_by', 'employeeid');
    }

    public function charger()
    {
        return $this->belongsTo(Employee::class, 'charged_by', 'employeeid');
    }

    public function dispenser()
    {
        return $this->belongsTo(Employee::class, 'dispensed_by', 'employeeid');
    }

    public function canceller()
    {
        return $this->belongsTo(Employee::class, 'cancelled_by', 'employeeid');
    }

    public function logs()
    {
        return $this->hasMany(PrescriptionQueueLog::class, 'queue_id', 'id')->orderBy('created_at', 'desc');
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeForLocation($query, $locationCode)
    {
        return $query->where('location_code', $locationCode);
    }

    public function scopeWaiting($query)
    {
        return $query->where('queue_status', 'waiting');
    }

    public function scopePreparing($query)
    {
        return $query->where('queue_status', 'preparing');
    }

    public function scopeCharging($query)
    {
        return $query->where('queue_status', 'charging');
    }

    public function scopeReady($query)
    {
        return $query->where('queue_status', 'ready');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('queue_status', ['waiting', 'preparing', 'charging', 'ready']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('queued_at', today());
    }

    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeOrderByPriority($query)
    {
        return $query->orderByRaw("
            CASE priority
                WHEN 'stat' THEN 1
                WHEN 'urgent' THEN 2
                ELSE 3
            END
        ")->orderBy('queued_at', 'asc');
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    public function getStatusBadgeClass()
    {
        return match ($this->queue_status) {
            'waiting' => 'badge-warning',
            'preparing' => 'badge-info',
            'charging' => 'badge-secondary',
            'ready' => 'badge-success',
            'dispensed' => 'badge-ghost',
            'cancelled' => 'badge-error',
            default => 'badge-ghost',
        };
    }

    public function getPriorityBadgeClass()
    {
        return match ($this->priority) {
            'stat' => 'badge-error',
            'urgent' => 'badge-warning',
            'normal' => 'badge-ghost',
            default => 'badge-ghost',
        };
    }

    public function getWaitTimeMinutes()
    {
        $endTime = match ($this->queue_status) {
            'waiting' => now(),
            'preparing' => $this->preparing_at ?? now(),
            'charging' => $this->charging_at ?? now(),
            'ready' => $this->ready_at ?? now(),
            'dispensed' => $this->dispensed_at ?? now(),
            'cancelled' => $this->cancelled_at ?? now(),
            default => now(),
        };

        return floor($this->queued_at->diffInMinutes($endTime));
    }

    public function getProcessingTimeMinutes()
    {
        if (!$this->preparing_at || !$this->ready_at) return null;
        return floor($this->preparing_at->diffInMinutes($this->ready_at));
    }

    public function getTotalTimeMinutes()
    {
        if (!$this->queued_at || !$this->dispensed_at) return null;
        return floor($this->queued_at->diffInMinutes($this->dispensed_at));
    }

    public function isWaiting()
    {
        return $this->queue_status === 'waiting';
    }

    public function isPreparing()
    {
        return $this->queue_status === 'preparing';
    }

    public function isCharging()
    {
        return $this->queue_status === 'charging';
    }

    public function isReady()
    {
        return $this->queue_status === 'ready';
    }

    public function isDispensed()
    {
        return $this->queue_status === 'dispensed';
    }

    public function isCancelled()
    {
        return $this->queue_status === 'cancelled';
    }

    public function isActive()
    {
        return in_array($this->queue_status, ['waiting', 'preparing', 'charging', 'ready']);
    }

    public function getDisplayName()
    {
        if (!$this->patient) return 'Queue ' . $this->queue_number;

        return mb_substr($this->patient->patfirst, 0, 1) . '*** ' .
            mb_substr($this->patient->patlast, 0, 1) . '***';
    }

    // ==========================================
    // Static Methods
    // ==========================================

    public static function generateQueueNumber($locationCode, $prefix = 'RX', $priority = 'normal')
    {
        $date = today()->format('Ymd');
        $priorityPrefix = $priority === 'stat' ? 'S-' : '';

        $lastSequence = self::where('location_code', $locationCode)
            ->where('queue_prefix', $prefix)
            ->whereDate('queued_at', today())
            ->max('sequence_number') ?? 0;

        $nextSequence = $lastSequence + 1;
        $queueNumber = "{$prefix}{$priorityPrefix}" . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);

        return [
            'queue_number' => $queueNumber,
            'sequence_number' => $nextSequence,
        ];
    }

    public static function estimateWaitTime($locationCode, $priority = 'normal')
    {
        $waitingCount = self::forLocation($locationCode)
            ->waiting()
            ->whereDate('queued_at', today())
            ->count();

        $preparingCount = self::forLocation($locationCode)
            ->preparing()
            ->whereDate('queued_at', today())
            ->count();

        $chargingCount = self::forLocation($locationCode)
            ->charging()
            ->whereDate('queued_at', today())
            ->count();

        $avgProcessingTime = self::forLocation($locationCode)
            ->where('queue_status', 'dispensed')
            ->whereDate('queued_at', today())
            ->whereNotNull('dispensed_at')
            ->get()
            ->avg(function ($queue) {
                return $queue->getTotalTimeMinutes();
            }) ?? 15;

        $queueAhead = $waitingCount + $preparingCount + $chargingCount;
        $estimatedWait = ceil($queueAhead * $avgProcessingTime);

        if ($priority === 'stat') {
            $estimatedWait = ceil($estimatedWait * 0.3);
        } elseif ($priority === 'urgent') {
            $estimatedWait = ceil($estimatedWait * 0.6);
        }

        return max(5, min($estimatedWait, 180));
    }
}
