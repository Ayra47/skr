<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestAcceptedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $senderId,
        public int $receiverId,
        public string $receiverLogin
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->senderId);
    }

    public function broadcastAs(): string
    {
        return 'friend.accepted';
    }

    public function broadcastWith(): array
    {
        return [
            'receiver_id' => $this->receiverId,
            'receiver_login' => $this->receiverLogin,
            'message' => "{$this->receiverLogin} принял ваш запрос в друзья",
        ];
    }
}
