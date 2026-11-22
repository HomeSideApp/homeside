<?php

namespace Tests\Feature\Registration;

use App\Actions\Auth\ResolveGoogleIdentity;
use App\Enums\AppLocale;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The language a Google account is born with.
 *
 * Google reports a language tag (`locale`) and it is an **optional** claim: it
 * comes when the account publishes it and it is absent otherwise. The rule is
 * therefore "use the account's language when it is one we support, English
 * otherwise", never "guess from the browser".
 */
class GoogleLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Google, RegistrationPolicy::Open);
    }

    private function register(?string $locale): User
    {
        $outcome = app(ResolveGoogleIdentity::class)->resolve(
            'google-subject',
            'applicant@example.test',
            'Google Applicant',
            true,
            $locale,
        );

        $this->assertNotNull($outcome->user);

        return $outcome->user->refresh();
    }

    public function test_a_spanish_account_is_registered_in_spanish(): void
    {
        $this->assertSame(AppLocale::SpanishSpain->value, $this->register('es')->locale);
    }

    public function test_a_regional_spanish_tag_is_understood(): void
    {
        $this->assertSame(AppLocale::SpanishSpain->value, $this->register('es-419')->locale);
    }

    public function test_an_english_tag_is_registered_in_english(): void
    {
        $this->assertSame(AppLocale::EnglishUnitedStates->value, $this->register('en-GB')->locale);
    }

    public function test_a_missing_locale_falls_back_to_english(): void
    {
        $this->assertSame(AppLocale::EnglishUnitedStates->value, $this->register(null)->locale);
    }

    public function test_an_unsupported_language_falls_back_to_english(): void
    {
        // No inventamos un idioma que la app no tiene: mejor inglés que una
        // interfaz rota o en un idioma que el usuario no pidió.
        $this->assertSame(AppLocale::EnglishUnitedStates->value, $this->register('pt-BR')->locale);
    }
}
