<?php

namespace App\Notifications;

use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class FriendRequestNotification extends Notification implements ShouldBroadcast
{
    use InteractsWithSockets, Queueable;

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
            'sender_pseudonym' => $this->sender->feedName(),
            'friend_request_id' => $this->friendRequest->id,
            'source' => 'code',
            'message' => "{$this->sender->feedName()} отправил вам запрос в друзья",
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->friendRequest->receiver_id);
    }

    public function broadcastAs(): string
    {
        return 'friend.request';
    }
}
