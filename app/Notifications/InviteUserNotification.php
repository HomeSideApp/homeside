<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class InviteUserNotification extends Notification
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
        $url = URL::signedRoute('invitation.show', [
            'user' => $notifiable->getRouteKey(),
        ]);

        return (new MailMessage)
            ->subject(__('notifications.user_invitation.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.user_invitation.line', ['app' => config('app.name')]))
            ->line(__('notifications.user_invitation.instructions'))
            ->action(__('notifications.user_invitation.action'), $url)
            ->line(__('notifications.common.ignore'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
