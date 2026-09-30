<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Household Modules Configuration
    |--------------------------------------------------------------------------
    |
    | Define los módulos disponibles para los hogares, sus dependencias
    | y las advertencias que se muestran al usuario al desactivar módulos.
    |
    */

    'modules' => [
        'shopping_lists' => [
            'label' => 'households.modules.shopping_lists_label',
            'description' => 'households.modules.shopping_lists_description',
            'icon' => 'ShoppingCart',
            'dependencies' => [],
            'features' => [
                'create_lists' => 'Crear listas',
                'add_items' => 'Agregar items',
                'share_lists' => 'Compartir listas',
            ],
        ],
        'recipes' => [
            'label' => 'households.modules.recipes_label',
            'description' => 'households.modules.recipes_description',
            'icon' => 'ChefHat',
            'dependencies' => [
                'shopping_lists' => [
                    'warning' => 'households.modules.missingShoppingLists',
                    'severity' => 'info',
                    'affected_features' => ['add_to_list', 'generate_list'],
                ],
            ],
            'features' => [
                'create_recipes' => 'Crear recetas',
                'add_to_list' => 'Añadir ingredientes a lista',
                'generate_list' => 'Generar lista desde receta',
                'ai_suggestions' => 'Sugerir ingredientes con IA',
                'ai_generate_recipes' => 'Generar recetas con IA',
            ],
        ],
        'economy' => [
            'label' => 'households.modules.economy_label',
            'description' => 'households.modules.economy_description',
            'icon' => 'Receipt',
            'dependencies' => [
                'shopping_lists' => [
                    'warning' => 'households.modules.missingShoppingListsEconomy',
                    'severity' => 'info',
                    'affected_features' => ['link_to_list'],
                ],
                'recipes' => [
                    'warning' => 'households.modules.missingRecipes',
                    'severity' => 'info',
                    'affected_features' => ['link_to_recipe'],
                ],
            ],
            'features' => [
                'scan_tickets' => 'Escanear tickets con IA',
                'categorize' => 'Categorizar gastos',
                'link_to_list' => 'Vincular gasto a lista',
                'link_to_recipe' => 'Asignar gasto a receta',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Predefined Tags
    |--------------------------------------------------------------------------
    |
    | Tags predefinidos disponibles para clasificar hogares.
    | El slug se genera automáticamente a partir del nombre.
    |
    */

    'predefined_tags' => [
        'Familia' => 'familia',
        'Compañeros de piso' => 'roommates',
        'Pareja' => 'pareja',
        'Individual' => 'individual',
        'Compartido' => 'compartido',
    ],

];
