<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El catálogo de módulos es lo que la app móvil usa para nombrar los módulos del
 * hogar. Si las etiquetas no resuelven, la API entrega la clave cruda
 * (`households.modules.recipes_label`) y el cliente muestra eso.
 *
 * Pasaba porque esas claves vivían sólo en `resources/js/locales/*.ts`.
 */
class HouseholdConfigurationApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(string $locale = 'en-US'): void
    {
        // Los tests no corren seeders: el rol se crea acá, como en el resto de
        // las pruebas de hogares.
        $user = User::factory()->create(['locale' => $locale]);
        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions([]);
        $user->assignRole($role);

        Sanctum::actingAs($user);
    }

    public function test_module_labels_resolve_in_spanish(): void
    {
        // El middleware `locale` usa el locale del usuario, no el header.
        $this->actingAsUser('es-ES');

        $response = $this->getJson(route('api.v1.households.configuration.show'));

        $response->assertOk();

        $labels = collect($response->json('data.modules'))->pluck('label', 'key');

        $this->assertSame('Listas de compra', $labels['shopping_lists']);
        $this->assertSame('Recetas', $labels['recipes']);
        $this->assertSame('Economía', $labels['economy']);
    }

    public function test_module_labels_resolve_in_english(): void
    {
        $this->actingAsUser('en-US');

        $response = $this->getJson(route('api.v1.households.configuration.show'));

        $response->assertOk();

        $labels = collect($response->json('data.modules'))->pluck('label', 'key');

        $this->assertSame('Shopping lists', $labels['shopping_lists']);
        $this->assertSame('Recipes', $labels['recipes']);

        // Y ninguna etiqueta viaja como clave de traducción.
        $this->assertFalse($labels->contains(fn (string $label): bool => str_contains($label, 'households.modules')));
    }
}
