<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DetachEmailVerification extends Notification
{
    public function __construct(private readonly string $confirmationUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Подтвердите отвязку email — skr')
            ->greeting('Привет!')
            ->line('Вы запросили удаление адреса email из вашего аккаунта skr.')
            ->line('Нажмите кнопку ниже, чтобы подтвердить. Ссылка действует 1 час.')
            ->action('Подтвердить отвязку email', $this->confirmationUrl)
            ->line('Если вы не запрашивали это — просто проигнорируйте письмо, email останется привязан.');
    }
}
