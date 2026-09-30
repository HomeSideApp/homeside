<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $user,
        private readonly string $resetUrl,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.reset_password.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.greeting', ['name' => $this->user->name]))
            ->line(__('notifications.reset_password.line'))
            ->line(__('notifications.reset_password.instructions'))
            ->action(__('notifications.reset_password.action'), $this->resetUrl)
            ->line(__('notifications.common.not_requested'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
