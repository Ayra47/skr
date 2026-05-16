<?php

namespace App\Notifications;

use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class FeedCommentNotification extends Notification implements ShouldBroadcast
{
    use InteractsWithSockets, Queueable;

    public function __construct(
        public User $commenter,
        public FeedPost $post,
        public ?FeedComment $comment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $isReply = $this->comment?->parent_id !== null;
        $snippet = mb_strimwidth($this->comment?->body ?? '', 0, 60, '…');

        return [
            'type' => 'reply',
            'post_id' => $this->post->id,
            'comment_id' => $this->comment?->id,
            'commenter_id' => $this->commenter->id,
            'commenter_login' => $this->commenter->feedName(),
            'title' => $isReply
                ? "{$this->commenter->feedName()} ответил на ваш комментарий"
                : "{$this->commenter->feedName()} прокомментировал ваш пост",
            'body' => $snippet,
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
