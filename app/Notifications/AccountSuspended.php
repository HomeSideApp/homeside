<?php

namespace App\Notifications;

use App\Enums\AccountStatus;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that their account was suspended or blocked.
 *
 * One notification carries both administrative conditions because the person
 * experiences the same outcome — they cannot get in — and the reason is an
 * internal one that is deliberately not disclosed. The condition decides the
 * wording, not a second class that would drift from this one.
 */
final class AccountSuspended extends Notification
{
    public function __construct(public AccountStatus $status) {}

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
        $condition = $this->status === AccountStatus::Blocked ? 'blocked' : 'suspended';

        return (new MailMessage)
            ->subject(__('app.registrations.notifications.account_'.$condition.'.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.greeting', ['name' => $notifiable->name]))
            ->line(__('app.registrations.notifications.account_'.$condition.'.line'))
            ->line(__('app.registrations.notifications.account_'.$condition.'.contact'));
    }
}
