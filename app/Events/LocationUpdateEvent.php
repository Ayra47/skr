<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationUpdateEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $recipientId,
        public string $sessionUuid,
        public int $conversationId,
        public string $encryptedPayload,
        public bool $stopped = false,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.'.$this->recipientId);
    }

    public function broadcastAs(): string
    {
        return 'chat.location';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionUuid,
            'conversation_id' => $this->conversationId,
            'encrypted_payload' => $this->encryptedPayload,
            'stopped' => $this->stopped,
        ];
    }
}
