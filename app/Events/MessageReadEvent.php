<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReadEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int>  $messageIds
     */
    public function __construct(
        public int $senderId,
        public int $conversationId,
        public array $messageIds,
        public string $readAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.'.$this->senderId);
    }

    public function broadcastAs(): string
    {
        return 'chat.read';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_ids' => $this->messageIds,
            'read_at' => $this->readAt,
        ];
    }
}
