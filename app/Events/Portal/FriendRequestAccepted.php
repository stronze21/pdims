<?php

namespace App\Events\Portal;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $toUserId;
    public array $friendData;

    public function __construct(int $toUserId, array $friendData)
    {
        $this->toUserId = $toUserId;
        $this->friendData = $friendData;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('friends.' . $this->toUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'friend.request.accepted';
    }

    public function broadcastWith(): array
    {
        return $this->friendData;
    }
}
