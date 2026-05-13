<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeVerification extends Notification
{
    public function __construct(private readonly string $verificationUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Подтвердите смену пароля — skr')
            ->greeting('Привет!')
            ->line('Вы запросили смену пароля в skr.')
            ->line('Нажмите кнопку ниже, чтобы подтвердить. Ссылка действует 1 час.')
            ->action('Подтвердить смену пароля', $this->verificationUrl)
            ->line('Если вы не меняли пароль — немедленно войдите в аккаунт и проверьте безопасность.');
    }
}
