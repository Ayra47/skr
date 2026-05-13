<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageEmailNotification extends Notification
{
    public function __construct(
        private readonly string $senderLogin,
        private readonly bool $includeText,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->includeText
            ? 'Новое сообщение от '.$this->senderLogin.' — skr'
            : 'Новое сообщение — skr';

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Привет!')
            ->line($this->includeText
                ? 'Пользователь **'.$this->senderLogin.'** написал вам сообщение.'
                : 'У вас новое зашифрованное сообщение.')
            ->action('Открыть skr', url('/'))
            ->line('Чтобы отключить email-уведомления, зайдите в Настройки → Уведомления.');

        return $mail;
    }
}
