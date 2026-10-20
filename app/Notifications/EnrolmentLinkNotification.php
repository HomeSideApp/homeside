<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mails the single-use link that completes a two-factor enrolment.
 *
 * The link is the only credential in the message: it is the same grant the web
 * redirect and the mobile API response carry, bound to the user, bound to the
 * enrolment purpose and redeemable exactly once. That is why the mail states
 * its lifetime and says plainly that an unrequested message can be ignored —
 * an enrolment link is a bearer credential, and a person who did not just
 * register has no reason to open it.
 */
class EnrolmentLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $user,
        private readonly string $enrolUrl,
        private readonly int $ttlMinutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.enrolment_link.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.greeting', ['name' => $this->user->name]))
            ->line(__('notifications.enrolment_link.line'))
            ->line(__('notifications.enrolment_link.instructions'))
            ->line($this->lifetimeLine())
            ->action(__('notifications.enrolment_link.action'), $this->enrolUrl)
            ->line(__('notifications.enrolment_link.not_you'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }

    /**
     * State how long the link is useful in the unit that reads naturally.
     *
     * The lifetime is deployment configuration, so it is never hard-coded in
     * the template: a 12 hour default reads as "12 hours" and a 30 minute
     * override reads as "30 minutes". The singular forms are explicit because
     * a configured lifetime of 60 minutes is a legitimate value.
     *
     * @return string The translated lifetime line.
     */
    private function lifetimeLine(): string
    {
        if ($this->ttlMinutes % 60 === 0) {
            $hours = intdiv($this->ttlMinutes, 60);

            return $hours === 1
                ? __('notifications.enrolment_link.valid_hour')
                : __('notifications.enrolment_link.valid_hours', ['hours' => $hours]);
        }

        return $this->ttlMinutes === 1
            ? __('notifications.enrolment_link.valid_minute')
            : __('notifications.enrolment_link.valid_minutes', ['minutes' => $this->ttlMinutes]);
    }
}
