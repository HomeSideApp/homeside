<?php

namespace App\Notifications;

use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Tells the administrators who can approve that a registration is waiting.
 *
 * Recipients are resolved from the `admin.registrations.approve` permission,
 * never from a role slug: a role is a container an operator may repurpose, while
 * the permission is the authority that actually unlocks the approve action.
 * Only approved, active administrators are notified, so a suspended account
 * cannot be used to observe the queue.
 */
final class RegistrationRequested extends Notification
{
    public function __construct(public User $applicant) {}

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
     * Notify every administrator allowed to approve a registration.
     *
     * Intended to run AFTER the registration transaction commits, so a failed
     * mail can never roll the registration back and the notification can be
     * retried without repeating the decision.
     *
     * @param  User  $applicant  The account awaiting approval.
     */
    public static function sendToAdministrators(User $applicant): void
    {
        $recipients = self::administrators();

        if ($recipients->isEmpty()) {
            return;
        }

        NotificationFacade::send($recipients, new self($applicant));
    }

    /**
     * Resolve the administrators who hold the approve permission.
     *
     * @return Collection<int, User> The recipients.
     */
    public static function administrators(): Collection
    {
        return User::query()
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->where('account_status', AccountStatus::Active->value)
            ->whereHas('roles.permissions', fn ($permissions) => $permissions->where('route_name', 'admin.registrations.approve'))
            ->get();
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
            ->subject(__('app.registrations.notifications.requested.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.common.greeting', ['name' => $notifiable->name]))
            ->line(__('app.registrations.notifications.requested.line', [
                'name' => $this->applicant->name,
                'email' => $this->applicant->email,
            ]))
            ->action(__('app.registrations.notifications.requested.action'), route('admin.registrations.index'))
            ->line(__('app.registrations.notifications.requested.ignore'));
    }
}
