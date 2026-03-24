<?php

namespace App\Events\Portal;

use App\Models\Portal\TeleconsultSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeleconsultStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TeleconsultSession $session
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('teleconsult.' . $this->session->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'teleconsult.started';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'appointment_id' => $this->session->appointment_id,
            'doctor_name' => $this->session->doctor_name,
            'status' => $this->session->status,
            'started_at' => $this->session->started_at?->toISOString(),
        ];
    }
}
