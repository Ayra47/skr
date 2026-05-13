<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $recipientId,
        public readonly string $senderLogin,
        public readonly int $senderId,
        public readonly int $conversationId,
        public readonly string $encryptedPayload = '',
    ) {}

    public function handle(): void
    {
        $subscriptions = PushSubscription::where('user_id', $this->recipientId)->get();

        if ($subscriptions->isEmpty()) {
            Log::info("EMPTY 1");
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('app.vapid_subject'),
                'publicKey' => config('app.vapid_public_key'),
                'privateKey' => config('app.vapid_private_key'),
            ],
        ]);

        $payload = json_encode([
            'title' => 'skr',
            'body' => 'Новое сообщение от ' . $this->senderLogin,
            'tag' => 'msg-' . $this->senderId,
            'url' => '/chats?with=' . $this->senderId . '&login=' . rawurlencode($this->senderLogin),
            'conversation_id' => $this->conversationId,
            'sender_id' => $this->senderId,
            'sender_login' => $this->senderLogin,
            'recipient_id' => $this->recipientId,
            'encrypted_payload' => $this->encryptedPayload,
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => [
                        'p256dh' => $sub->p256dh,
                        'auth' => $sub->auth,
                    ],
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                Log::info("Push success", [
                    'endpoint' => $endpoint,
                ]);
            } else {
                Log::error("Push failed", [
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                ]);
            }

            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $endpoint)->delete();
            }
        }
    }
}
