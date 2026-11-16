<?php

return [
    'create_user' => [
        'name' => 'Nombre del usuario',
        'email' => 'Email del usuario',
        'invalid_email' => 'El email no es válido.',
        'email_exists' => 'Ya existe un usuario con este email.',
        'role' => 'Rol',
        'summary' => 'Resumen:',
        'summary_name' => '  Nombre: :name',
        'summary_email' => '  Email: :email',
        'summary_role' => '  Rol: :role',
        'confirm' => '¿Crear usuario?',
        'cancelled' => 'Operación cancelada.',
        'created' => 'Usuario creado y email de verificación enviado.',
        'missing_options' => 'El modo no interactivo necesita --name, --email y --role juntos. Faltan: :options.',
        'unknown_role' => 'El rol ":role" no existe. Usa uno de los roles disponibles: :roles.',
    ],
    'economy' => [
        'recurring' => [
            'description' => 'Genera las transacciones económicas recurrentes pendientes.',
            'generated' => 'Se ha generado :count transacción recurrente.|Se han generado :count transacciones recurrentes.',
            'invalid_date' => 'La fecha de generación indicada no es válida.',
        ],
    ],
];
