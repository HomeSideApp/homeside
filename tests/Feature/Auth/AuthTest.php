<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_auth_using_login_page(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
    }

    public function test_users_cannot_auth_with_invalid_password(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_invitation_show_renders_set_password_page(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $url = URL::temporarySignedRoute('invitation.show', now()->addMinutes(30), ['user' => $user->id]);
        $response = $this->get($url);

        $response->assertOk();
    }

    public function test_invitation_store_redirects_to_otp_setup(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $url = URL::temporarySignedRoute('invitation.store', now()->addMinutes(30));
        $response = $this->post($url, [
            'token' => $user->id,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('otp/setup', $response->headers->get('Location'));
        $this->assertTrue($user->fresh()->email_verified_at !== null);
    }

    public function test_invitation_store_updates_the_real_name_when_provided(): void
    {
        $user = User::create([
            'name' => 'invited@example.com',
            'email' => 'invited@example.com',
            'password' => 'password',
        ]);

        $url = URL::temporarySignedRoute('invitation.store', now()->addMinutes(30));
        $this->post($url, [
            'token' => $user->id,
            'name' => 'Rafael Ortega',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect();

        $this->assertSame('Rafael Ortega', $user->fresh()->name);
    }

    public function test_invitation_store_keeps_the_email_fallback_when_name_is_empty(): void
    {
        $user = User::create([
            'name' => 'invited@example.com',
            'email' => 'invited@example.com',
            'password' => 'password',
        ]);

        $url = URL::temporarySignedRoute('invitation.store', now()->addMinutes(30));
        $this->post($url, [
            'token' => $user->id,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect();

        $this->assertSame('invited@example.com', $user->fresh()->name);
    }

    public function test_invitation_store_rejects_unsigned_post(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('invitation.store'), [
            'token' => $user->id,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(403);
    }

    public function test_otp_setup_renders_with_signed_url(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('otp.setup', now()->addMinutes(30), ['user' => $user->id]);
        $response = $this->get($url);

        $response->assertOk();
    }

    public function test_otp_setup_rejects_unsigned_request(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $response = $this->get(route('otp.setup', $user->id));

        $response->assertStatus(403);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHas('status');
    }

    public function test_reset_password_page_can_be_rendered(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('password.reset', now()->addMinutes(30), ['user' => $user->id]);
        $response = $this->get($url);

        $response->assertOk();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('password.update', now()->addMinutes(30), ['user' => $user->id]);
        $response = $this->post($url, [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
