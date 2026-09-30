<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoogleAccessResolved extends Notification
{
    public function __construct(public bool $approved) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->approved ? 'Acceso aprobado' : 'Solicitud de acceso rechazada')
            ->line($this->approved
                ? 'Tu cuenta ya está aprobada. Puedes entrar con Google y configurar el segundo factor.'
                : 'Tu solicitud de acceso con Google ha sido rechazada.')
            ->action('Ir al inicio de sesión', route('login'));
    }
}
