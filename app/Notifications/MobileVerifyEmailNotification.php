<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MobileVerifyEmailNotification extends Notification
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** @param User $notifiable */
    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.mobile_url'), '/').'/email/verification?'.http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);

        return (new MailMessage)
            ->subject(__('notifications.verify_email.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.verify_email.line'))
            ->action(__('notifications.verify_email.action'), $url)
            ->line(__('notifications.common.ignore'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
