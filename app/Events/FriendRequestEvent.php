<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $receiverId,
        public int $senderId,
        public string $senderLogin,
        public int $friendRequestId
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->receiverId);
    }

    public function broadcastAs(): string
    {
        return 'friend.request';
    }

    public function broadcastWith(): array
    {
        return [
            'sender_id' => $this->senderId,
            'sender_login' => $this->senderLogin,
            'friend_request_id' => $this->friendRequestId,
            'message' => "{$this->senderLogin} отправил вам запрос в друзья",
        ];
    }
}
