<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that their registration was rejected.
 *
 * The message is deliberately generic: it MUST NOT reveal the administrative
 * reasoning, the acting administrator, the internal user id, the audit id or
 * the block id. The person learns the outcome, not the file.
 */
final class RegistrationRejected extends Notification
{
    /**
     * Get the delivery channels of the notification.
     *
     * @param  object  $notifiable  The recipient.
     * @return list<string> The channel names.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  object  $notifiable  The recipient.
     * @return MailMessage The mail message.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.registrations.notifications.rejected.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.greeting', ['name' => $notifiable->name]))
            ->line(__('app.registrations.notifications.rejected.line'))
            ->line(__('app.registrations.notifications.rejected.contact'));
    }
}
