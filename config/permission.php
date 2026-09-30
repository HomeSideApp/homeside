<?php

use App\Models\Permission;
use App\Models\Role;

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Configuration
    |--------------------------------------------------------------------------
    |
    | Custom permission system configuration.
    |
    */

    'models' => [
        'permission' => Permission::class,
        'role' => Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'roles_permissions' => 'roles_permissions',
        'role_user' => 'role_user',
    ],

];
