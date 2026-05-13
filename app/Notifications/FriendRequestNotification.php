<?php

namespace App\Notifications;

use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\PrivateChannel;

class FriendRequestNotification extends Notification implements ShouldBroadcast
{
    use Queueable, InteractsWithSockets;

    public function __construct(
        public User $sender,
        public FriendRequest $friendRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sender_id' => $this->sender->id,
            'sender_login' => $this->sender->login,
            'friend_request_id' => $this->friendRequest->id,
            'message' => "{$this->sender->login} отправил вам запрос в друзья",
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->friendRequest->receiver_id);
    }

    public function broadcastAs(): string
    {
        return 'friend.request';
    }
}
