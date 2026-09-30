<?php

return [
    'common' => [
        'greeting' => 'Hello :name!',
        'generic_greeting' => 'Hello!',
        'ignore' => 'If you were not expecting this invitation, you can ignore this email.',
        'not_requested' => 'If you did not request this, you can ignore this email.',
    ],
    'household_invitation' => [
        'subject' => 'You have been invited to a household on :app',
        'line' => ':inviter invited you to join the household ":household".',
        'instructions' => 'Select the button to accept the invitation and join the household.',
        'action' => 'Accept invitation',
        'expires' => 'The invitation expires on :date.',
    ],
    'new_user_household_invitation' => [
        'subject' => 'You have been invited to join :app',
        'line' => ':inviter invited you to join the household ":household" on :app.',
        'instructions' => 'Create an account to access the application and join the household.',
        'action' => 'Create account',
    ],
    'user_invitation' => [
        'subject' => 'Invitation to :app',
        'line' => 'You have been invited to join :app.',
        'instructions' => 'Select the link to set your password and activate your account.',
        'action' => 'Set password',
    ],
    'verify_email' => [
        'subject' => 'Activate your account on :app',
        'line' => 'Your account has been created. Select the link to set your password and activate your account.',
        'action' => 'Verify email',
    ],
    'reset_password' => [
        'subject' => 'Reset password - :app',
        'line' => 'We received a request to reset your password.',
        'instructions' => 'Select the button below to create a new password. The link expires in 30 minutes.',
        'action' => 'Reset password',
    ],
    'reset_otp' => [
        'subject' => 'Reset OTP - :app',
        'line' => 'We received a request to reset your two-factor authentication.',
        'instructions' => 'Select the button below to configure a new OTP code. The link expires in 30 minutes.',
        'current_code' => 'Your current OTP will continue to work until you configure a new one.',
        'action' => 'Reset OTP',
    ],
];
