<?php

return [
    'create_user' => [
        'name' => 'User name',
        'email' => 'User email',
        'invalid_email' => 'The email address is invalid.',
        'email_exists' => 'A user with this email address already exists.',
        'role' => 'Role',
        'summary' => 'Summary:',
        'summary_name' => '  Name: :name',
        'summary_email' => '  Email: :email',
        'summary_role' => '  Role: :role',
        'confirm' => 'Create user?',
        'cancelled' => 'Operation cancelled.',
        'created' => 'User created and verification email sent.',
    ],
    'economy' => [
        'recurring' => [
            'description' => 'Generate due recurring economic transactions.',
            'generated' => 'Generated :count recurring transaction.|Generated :count recurring transactions.',
            'invalid_date' => 'The supplied generation date is invalid.',
        ],
    ],
];
