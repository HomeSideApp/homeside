<?php

/*
|--------------------------------------------------------------------------
| Hogares — textos que el servidor resuelve fuera del frontend
|--------------------------------------------------------------------------
|
| `config/household_modules.php` apunta a estas claves para las etiquetas de
| módulo. Vivían sólo en `resources/js/locales/*.ts`, así que cualquier consumidor
| del servidor (la API, y por lo tanto la app móvil) recibía la clave cruda
| `households.modules.recipes_label` en vez de "Recetas".
|
*/

return [

    'modules' => [
        'shopping_lists_label' => 'Listas de compra',
        'shopping_lists_description' => 'Gestiona listas de la compra compartidas',
        'recipes_label' => 'Recetas',
        'recipes_description' => 'Gestiona recetas y genera listas desde ellas',
        'economy_label' => 'Economía',
        'economy_description' => 'Analiza tickets y gestiona gastos',
    ],

];
