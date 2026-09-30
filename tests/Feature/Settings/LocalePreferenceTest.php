<?php

namespace Tests\Feature\Settings;

use App\Enums\AppLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.fallback_locale', 'en-US');
    }

    /** @return array<string, array{string}> */
    public static function supportedLocales(): array
    {
        return [
            'English (United States)' => ['en-US'],
            'Spanish (Spain)' => ['es-ES'],
        ];
    }

    #[DataProvider('supportedLocales')]
    public function test_user_can_update_their_regional_locale(string $locale): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $locale,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame($locale, $user->refresh()->locale);
    }

    #[DataProvider('unsupportedLocales')]
    public function test_profile_rejects_unsupported_locales(string $locale): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $locale,
            ])
            ->assertSessionHasErrors('locale');
    }

    /** @return array<string, array{string}> */
    public static function unsupportedLocales(): array
    {
        return [
            'Arabic is not Argentina' => ['ar'],
            'generic Spanish' => ['es'],
            'unsupported region' => ['es-MX'],
            'removed regional variant' => ['es-AR'],
            'incorrect casing' => ['es-ar'],
            'empty locale' => [''],
        ];
    }

    public function test_profile_page_shares_localization_options(): void
    {
        $user = User::factory()->create(['locale' => 'es-ES']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('auth.user.locale', 'es-ES')
                ->where('localization.locale', 'es-ES')
                ->where('localization.fallbackLocale', 'en-US')
                ->where('localization.supportedLocales', AppLocale::options())
            );
    }

    public function test_new_users_default_to_american_english(): void
    {
        $user = User::factory()->create();

        $this->assertSame('en-US', $user->locale);
        $this->assertSame('en-US', $user->preferredLocale());
    }
}
