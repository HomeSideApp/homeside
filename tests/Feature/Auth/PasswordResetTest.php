<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertSessionHas('status');
    }

    public function test_reset_password_page_can_be_rendered_with_signed_url(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute('password.reset', now()->addMinutes(30), ['user' => $user->id]);

        $response = $this->get($url);

        $response->assertOk();
    }

    public function test_password_can_be_reset_with_signed_url(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute('password.update', now()->addMinutes(30), ['user' => $user->id]);

        $response = $this->post($url, [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_reset_password_rejects_unsigned_request(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update', $user->id), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(403);
    }
}
