<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification
{
    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Код подтверждения входа — skr')
            ->greeting('Привет!')
            ->line('Для входа в аккаунт введите код ниже. Он действует 10 минут.')
            ->line('**'.$this->code.'**')
            ->line('Если вы не входили в skr — немедленно смените пароль.');
    }
}
