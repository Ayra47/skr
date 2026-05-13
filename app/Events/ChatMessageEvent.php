<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public int $recipientId,
        public string $senderLogin = '',
        public ?string $encryptedPayload = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.'.$this->recipientId);
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        $conversation = $this->message->conversation;

        return [
            'id' => $this->message->id,
            'type' => $this->message->type,
            'conversation_id' => $this->message->conversation_id,
            'conversation_type' => $conversation?->type ?? Conversation::TYPE_DIRECT,
            'conversation_title' => $conversation?->title,
            'sender_id' => $this->message->sender_id,
            'sender_login' => $this->senderLogin,
            'encrypted_payload' => $this->message->type === Message::TYPE_SYSTEM ? '' : ($this->encryptedPayload ?? $this->message->encrypted_payload),
            'system_payload' => $this->message->system_payload,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
