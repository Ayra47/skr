<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('chat:prune')]
#[Description('Delete expired messages (older than 3 months) and enforce 10k per conversation limit')]
class PruneChatMessages extends Command
{
    public function handle(): int
    {
        $expired = Message::where('expires_at', '<=', now())->delete();
        $this->info("Deleted {$expired} expired messages.");

        $pruned = 0;
        Conversation::withCount('messages')
            ->having('messages_count', '>', 10000)
            ->get()
            ->each(function (Conversation $conversation) use (&$pruned) {
                $cutoffId = Message::where('conversation_id', $conversation->id)
                    ->orderByDesc('created_at')
                    ->skip(10000)
                    ->value('id');

                if ($cutoffId) {
                    $pruned += Message::where('conversation_id', $conversation->id)
                        ->where('id', '<=', $cutoffId)
                        ->delete();
                }
            });

        $this->info("Pruned {$pruned} messages over the 10k limit.");

        return self::SUCCESS;
    }
}
