<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
{
    public function __construct(private readonly string $resetUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Сброс пароля — skr')
            ->greeting('Привет!')
            ->line('Вы запросили сброс пароля для вашего аккаунта skr.')
            ->line('Нажмите кнопку ниже, чтобы задать новый пароль. Ссылка действует 1 час.')
            ->action('Сбросить пароль', $this->resetUrl)
            ->line('⚠️ Если вы не помните PIN-код защиты ключа (вводился при первом входе) или код восстановления — доступ к переписке будет потерян. Сброс пароля не восстанавливает ключ шифрования.')
            ->line('Если вы не запрашивали сброс — просто проигнорируйте это письмо.');
    }
}
