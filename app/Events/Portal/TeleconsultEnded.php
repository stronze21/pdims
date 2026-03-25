<?php

namespace App\Events\Portal;

use App\Models\Portal\TeleconsultSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeleconsultEnded implements ShouldBroadcastNow
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
        return 'teleconsult.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'appointment_id' => $this->session->appointment_id,
            'status' => $this->session->status,
            'ended_at' => $this->session->ended_at?->toISOString(),
            'duration_minutes' => $this->session->duration_minutes,
        ];
    }
}
