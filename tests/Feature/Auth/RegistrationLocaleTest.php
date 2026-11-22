<?php

namespace Tests\Feature\Auth;

use App\Enums\AppLocale;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Services\Auth\RegistrationSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The signup form offers the supported languages and starts in English.
 *
 * A new account must never be born in a language nobody chose, so the default
 * is the application default rather than the interface language of whoever
 * happens to render the page first.
 */
class RegistrationLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_offers_every_supported_locale_and_defaults_to_english(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/Register')
                ->where('defaultLocale', AppLocale::EnglishUnitedStates->value)
                ->where('localeOptions', AppLocale::options())
                ->where('localeOptions.1.code', AppLocale::SpanishSpain->value)
            );
    }

    public function test_precognition_validates_the_signup_without_reaching_the_use_case(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);

        // Un lote precognitivo sólo valida: 204 y ninguna cuenta creada.
        $this->postJson('/register', ['name' => '', 'email' => 'no-es-un-email'], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'name,email',
        ])->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }
}
