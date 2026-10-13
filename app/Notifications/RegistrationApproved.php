<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that their registration was approved.
 *
 * Dispatched only after the approval transaction commits, so a mail failure
 * cannot roll back the administrative decision; the notification can be retried
 * on its own.
 */
final class RegistrationApproved extends Notification
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
            ->subject(__('app.registrations.notifications.approved.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.greeting', ['name' => $notifiable->name]))
            ->line(__('app.registrations.notifications.approved.line'))
            ->action(__('app.registrations.notifications.approved.action'), route('login'));
    }
}
