<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoogleAccessRequested extends Notification
{
    public function __construct(public User $applicant) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva solicitud de acceso con Google')
            ->line($this->applicant->name.' ('.$this->applicant->email.') solicita acceso.')
            ->action('Revisar usuarios', route('admin.users', ['approval' => 'pending']));
    }
}
