<?php

namespace App\Notifications;

use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class HouseholdInviteNewUserNotification extends Notification
{
    public function __construct(
        public HouseholdInvitation $invitation,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** @param User $notifiable */
    public function toMail(object $notifiable): MailMessage
    {
        $household = $this->invitation->household()->firstOrFail();
        $inviter = $this->invitation->inviter()->firstOrFail();
        $url = URL::temporarySignedRoute(
            'invitation.show',
            now()->addMinutes(30),
            ['user' => $notifiable->id],
        );
        $expiresAt = $this->invitation->expires_at
            ->locale(app()->getLocale())
            ->isoFormat('LL');

        return (new MailMessage)
            ->subject(__('notifications.new_user_household_invitation.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.generic_greeting'))
            ->line(__('notifications.new_user_household_invitation.line', [
                'inviter' => $inviter->name,
                'household' => $household->name,
                'app' => config('app.name'),
            ]))
            ->line(__('notifications.new_user_household_invitation.instructions'))
            ->action(__('notifications.new_user_household_invitation.action'), $url)
            ->line(__('notifications.household_invitation.expires', ['date' => $expiresAt]));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $household = $this->invitation->household()->firstOrFail();
        $inviter = $this->invitation->inviter()->firstOrFail();

        return [
            'household_id' => $this->invitation->household_id,
            'household_name' => $household->name,
            'inviter_name' => $inviter->name,
            'email' => $this->invitation->email,
        ];
    }
}
