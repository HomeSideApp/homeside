<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** @param User $notifiable */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/invitation/'.$this->token);

        return (new MailMessage)
            ->subject(__('notifications.verify_email.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.verify_email.line'))
            ->action(__('notifications.user_invitation.action'), $url)
            ->line(__('notifications.common.ignore'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['token' => $this->token];
    }
}
