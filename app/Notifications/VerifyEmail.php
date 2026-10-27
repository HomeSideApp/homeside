<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Proof-of-ownership mail for an address that still has to be verified.
 *
 * The link is a temporary signed URL to the web verification route, which ALSO
 * requires the visitor to be authenticated as that same user: the signature
 * proves we issued the link, and the session proves who is presenting it. A
 * link alone cannot verify an address, so forwarding the mail is not enough.
 *
 * Deliberately not queued. A verification mail that sits in the queue behind a
 * worker nobody is running is indistinguishable from one that was never sent,
 * and the account it belongs to stays stuck behind the verification gate. The
 * other authentication notifications in this application send synchronously for
 * the same reason.
 */
class VerifyEmail extends Notification
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
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 1440)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

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
