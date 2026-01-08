<?php

namespace App\Events\Pharmacy;

use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueCalled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queue;
    public $calledFor; // 'pharmacy' or 'cashier'

    public function __construct(PrescriptionQueue $queue, $calledFor = 'pharmacy')
    {
        $this->queue = $queue;
        $this->calledFor = $calledFor;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('pharmacy.location.' . $this->queue->location_code);
    }

    public function broadcastAs(): string
    {
        return 'queue.called';
    }

    public function broadcastWith(): array
    {
        return [
            'queue_id' => $this->queue->id,
            'queue_number' => $this->queue->queue_number,
            'called_for' => $this->calledFor,
            'assigned_window' => $this->queue->assigned_window,
            'priority' => $this->queue->priority,
            'patient_name' => $this->queue->patient
                ? mb_substr($this->queue->patient->patfirst, 0, 1) . '. ' . $this->queue->patient->patlast
                : null,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
