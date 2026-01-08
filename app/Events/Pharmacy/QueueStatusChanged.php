<?php

namespace App\Events\Pharmacy;

use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queue;
    public $oldStatus;
    public $newStatus;

    public function __construct(PrescriptionQueue $queue, $oldStatus, $newStatus)
    {
        $this->queue = $queue;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('pharmacy.location.' . $this->queue->location_code);
    }

    public function broadcastAs(): string
    {
        return 'queue.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'queue_id' => $this->queue->id,
            'queue_number' => $this->queue->queue_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'assigned_window' => $this->queue->assigned_window,
            'priority' => $this->queue->priority,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
