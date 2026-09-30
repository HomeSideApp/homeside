<?php

use App\Enums\AppLocale;

return [
    'default' => env('APP_LOCALE', AppLocale::EnglishUnitedStates->value),
    'fallback' => env('APP_FALLBACK_LOCALE', AppLocale::EnglishUnitedStates->value),
    'console' => env('APP_CLI_LOCALE', AppLocale::EnglishUnitedStates->value),
    'supported' => AppLocale::values(),
];
