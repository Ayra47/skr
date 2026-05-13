<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeVerification extends Notification
{
    public function __construct(private readonly string $verificationUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Подтвердите новый адрес email — skr')
            ->greeting('Привет!')
            ->line('Вы запросили смену адреса email в skr.')
            ->line('Нажмите кнопку ниже, чтобы подтвердить новый адрес. Ссылка действует 24 часа.')
            ->action('Подтвердить email', $this->verificationUrl)
            ->line('Если вы не меняли email — просто проигнорируйте это письмо.');
    }
}
