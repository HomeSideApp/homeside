<?php

/*
|--------------------------------------------------------------------------
| Capability Registry
|--------------------------------------------------------------------------
|
| Pure Community-owned data describing the product capabilities the optional
| commercial package renders and validates against. Nothing in this file
| references the package: it is only the source of truth the package derives
| its plan editor from.
|
| `permission_prefixes` are matched empirically against the real
| `permissions.route_name` rows, so adding a permission inside an existing
| area needs ZERO config changes here. The prefixes were derived from the
| live table; resolved counts at the time of writing:
|
|   lists                -> 13  (households.lists. = 13)
|   recipes              -> 19  (recipes. = 15 + households.recipes. = 4)
|   economy              -> 74  (economy. = 44 + households.economy. = 30)
|   contacts             -> 13  (contacts. = 13)
|   ai_image_recognition ->  0  (not permission-backed, it gates a metered operation)
|   ai_assistant         ->  0  (not permission-backed, it gates a metered operation)
|
| ZERO-MATCH WARNING: `households.contacts.` currently matches NO rows. It is
| kept deliberately as a forward-compatible prefix so a future route under
| that namespace is picked up without touching this file. Today it resolves
| to an empty list.
|
| `module` is a real value of App\Enums\HouseholdModule (shopping_lists,
| recipes, economy) or null. `contacts` is NOT a household module, and the two
| `ai_*` features are metered operations rather than modules.
|
| `metric` is non-null only for the two `ai_*` features and points at the
| quota key they meter.
|
*/

return [

    'features' => [
        'lists' => [
            'label' => 'capabilities.features.lists',
            'module' => 'shopping_lists',
            'metric' => null,
            'permission_prefixes' => ['households.lists.'],
        ],
        'recipes' => [
            'label' => 'capabilities.features.recipes',
            'module' => 'recipes',
            'metric' => null,
            'permission_prefixes' => ['recipes.', 'households.recipes.'],
        ],
        'economy' => [
            'label' => 'capabilities.features.economy',
            'module' => 'economy',
            'metric' => null,
            'permission_prefixes' => ['economy.', 'households.economy.'],
        ],
        'contacts' => [
            'label' => 'capabilities.features.contacts',
            'module' => null,
            'metric' => null,
            'permission_prefixes' => ['contacts.', 'households.contacts.'],
        ],
        'ai_image_recognition' => [
            'label' => 'capabilities.features.ai_image_recognition',
            'module' => null,
            'metric' => 'ai_image_monthly',
            'permission_prefixes' => [],
        ],
        'ai_assistant' => [
            'label' => 'capabilities.features.ai_assistant',
            'module' => null,
            'metric' => 'ai_assistant_monthly',
            'permission_prefixes' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotas
    |--------------------------------------------------------------------------
    |
    | Metered limits a plan can cap. `unit` is a stable token the package maps
    | to its own translation; it is not a user-facing string.
    |
    */

    'quotas' => [
        'households_own' => [
            'label' => 'capabilities.quotas.households_own',
            'unit' => 'households',
        ],
        'storage_mb' => [
            'label' => 'capabilities.quotas.storage_mb',
            'unit' => 'mb',
        ],
        'ai_image_monthly' => [
            'label' => 'capabilities.quotas.ai_image_monthly',
            'unit' => 'images',
        ],
        'ai_assistant_monthly' => [
            'label' => 'capabilities.quotas.ai_assistant_monthly',
            'unit' => 'requests',
        ],
    ],

];
