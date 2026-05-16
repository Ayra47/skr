<?php

namespace App\Notifications;

use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class FeedVoteNotification extends Notification implements ShouldBroadcast
{
    use InteractsWithSockets, Queueable;

    public function __construct(
        public User $voter,
        public FeedPost $post,
        public string $value,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $emoji = $this->value === 'up' ? '👍' : '👎';

        return [
            'type' => 'reaction',
            'post_id' => $this->post->id,
            'voter_id' => $this->voter->id,
            'voter_login' => $this->voter->feedName(),
            'value' => $this->value,
            'title' => "{$this->voter->feedName()} поставил {$emoji} вашему посту",
            'body' => mb_strimwidth($this->post->body ?? 'вложение', 0, 60, '…'),
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->post->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification';
    }
}
