<?php

return [
    'common' => [
        'greeting' => '¡Hola, :name!',
        'generic_greeting' => '¡Hola!',
        'ignore' => 'Si no esperabas esta invitación, puedes ignorar este correo.',
        'not_requested' => 'Si no has solicitado esto, puedes ignorar este correo.',
    ],
    'household_invitation' => [
        'subject' => 'Te han invitado a un hogar en :app',
        'line' => ':inviter te ha invitado a unirte al hogar ":household".',
        'instructions' => 'Pulsa el botón para aceptar la invitación y unirte al hogar.',
        'action' => 'Aceptar invitación',
        'expires' => 'La invitación caduca el :date.',
    ],
    'new_user_household_invitation' => [
        'subject' => 'Te han invitado a unirte a :app',
        'line' => ':inviter te ha invitado a unirte al hogar ":household" en :app.',
        'instructions' => 'Crea una cuenta para acceder a la aplicación y unirte al hogar.',
        'action' => 'Crear cuenta',
    ],
    'user_invitation' => [
        'subject' => 'Invitación a :app',
        'line' => 'Te han invitado a unirte a :app.',
        'instructions' => 'Pulsa el enlace para establecer tu contraseña y activar tu cuenta.',
        'action' => 'Establecer contraseña',
    ],
    'verify_email' => [
        'subject' => 'Activa tu cuenta en :app',
        'line' => 'Tu cuenta ha sido creada. Pulsa el enlace para establecer tu contraseña y activar tu cuenta.',
        'action' => 'Verificar email',
    ],
    'reset_password' => [
        'subject' => 'Restablecer contraseña - :app',
        'line' => 'Hemos recibido una solicitud para restablecer tu contraseña.',
        'instructions' => 'Pulsa el botón para crear una contraseña nueva. El enlace caduca en 30 minutos.',
        'action' => 'Restablecer contraseña',
    ],
    'reset_otp' => [
        'subject' => 'Restablecer OTP - :app',
        'line' => 'Hemos recibido una solicitud para restablecer tu autenticación de dos factores.',
        'instructions' => 'Pulsa el botón para configurar un código OTP nuevo. El enlace caduca en 30 minutos.',
        'current_code' => 'Tu OTP actual seguirá funcionando hasta que configures uno nuevo.',
        'action' => 'Restablecer OTP',
    ],
];
