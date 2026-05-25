<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class FriendRequestAccepted extends Notification implements ShouldBroadcast
{
    use InteractsWithSockets, Queueable;

    public function __construct(
        public User $receiver
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'receiver_id' => $this->receiver->id,
            'receiver_login' => $this->receiver->login,
            'message' => "{$this->receiver->login} принял ваш запрос в друзья",
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->receiver->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'friend.accepted';
    }

    public function broadcastWith(): array
    {
        return [
            'receiver_id' => $this->receiver->id,
            'receiver_login' => $this->receiver->login,
            'message' => "{$this->receiver->login} принял ваш запрос в друзья",
        ];
    }
}
