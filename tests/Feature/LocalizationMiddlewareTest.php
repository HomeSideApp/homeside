<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_requests_use_the_default_locale(): void
    {
        $this->get(route('login'))->assertOk();

        $this->assertSame('en-US', app()->getLocale());
    }

    public function test_authenticated_requests_use_the_users_regional_locale(): void
    {
        $user = User::factory()->create(['locale' => 'es-ES']);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        $this->assertSame('es-ES', app()->getLocale());
    }

    public function test_an_invalid_stored_locale_falls_back_to_american_english(): void
    {
        $user = User::factory()->create(['locale' => 'invalid']);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        $this->assertSame('en-US', app()->getLocale());
        $this->assertSame('en-US', $user->preferredLocale());
    }
}
