<?php

namespace App\Events\Portal;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $toUserId;
    public array $requestData;

    public function __construct(int $toUserId, array $requestData)
    {
        $this->toUserId = $toUserId;
        $this->requestData = $requestData;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('friends.' . $this->toUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'friend.request.received';
    }

    public function broadcastWith(): array
    {
        return $this->requestData;
    }
}
