<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\NewMessageEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNewMessageEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $recipientId,
        private readonly string $senderLogin,
    ) {}

    public function handle(): void
    {
        $recipient = User::find($this->recipientId);

        if (! $recipient || ! $recipient->email) {
            return;
        }

        $userKey = $recipient->userKey;

        if (! $userKey || ! $userKey->notify_email) {
            return;
        }

        $recipient->notify(new NewMessageEmailNotification(
            senderLogin: $this->senderLogin,
            includeText: $userKey->notify_email_text,
        ));
    }
}
