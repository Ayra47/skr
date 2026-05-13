<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $senderId,
        public int $recipientId,
        public int $conversationId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.'.$this->recipientId);
    }

    public function broadcastAs(): string
    {
        return 'chat.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'sender_id' => $this->senderId,
            'conversation_id' => $this->conversationId,
        ];
    }
}
