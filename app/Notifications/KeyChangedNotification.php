<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class KeyChangedNotification extends Notification implements ShouldBroadcast
{
    use InteractsWithSockets, Queueable;

    public function __construct(
        public User $changedUser,
        public int $recipientId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'key',
            'changed_user_id' => $this->changedUser->id,
            'changed_user_login' => $this->changedUser->feedName(),
            'title' => "Ключ {$this->changedUser->feedName()} изменился",
            'body' => 'Рекомендуем верифицировать заново',
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->recipientId);
    }

    public function broadcastAs(): string
    {
        return 'notification';
    }
}
