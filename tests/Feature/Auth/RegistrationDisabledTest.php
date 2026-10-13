<?php

namespace Tests\Feature\Auth;

use App\Enums\RegistrationPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the disabled web registration channel.
 *
 * The tests keep their historical names (the old 404 assertions) on purpose:
 * they are the same cases, rewritten for the new capability-driven contract so
 * the change is reviewable as a modification rather than a deletion.
 */
class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GET /register answers 200 without an operable form when the channel is disabled.
     *
     * Historical name: this used to assert a 404 because registration had no
     * route at all. The spec revives GET /register as a REAL, capability-driven
     * page (E2E-01 steps 2-3): when web registration is disabled it must render
     * an explanation and a working link back to login, while offering NO usable
     * signup form. A 200 without an operable form is the required behaviour, so
     * the 404 assertion is obsolete.
     *
     * @return void This test method does not return a value.
     */
    public function test_registration_route_does_not_exist(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/Register')
                // Register.vue renders the whole <Form> -- the name, email,
                // password and password_confirmation fields plus the submit
                // control -- only when `capabilities.web.available` is true.
                // Publishing `false` is the server-visible proof that none of
                // those form fields or controls are operable.
                ->where('capabilities.web.available', false)
                ->where('capabilities.web.policy', RegistrationPolicy::Disabled->value)
            );

        // The page keeps a functional login link: Register.vue always renders
        // the "back to login" TextLink, and its target resolves to this route.
        $this->get(route('login'))->assertOk();
    }

    /**
     * POST /register is refused with an error instead of a 404 when disabled.
     *
     * Historical name: this used to assert a 404 because the POST endpoint had
     * no route at all. The disabled route stays alive so the operator can
     * re-enable it from the admin panel, so the repo's web convention applies:
     * redirect back with an error. The real contract is that a disabled channel
     * creates nothing, leaves the visitor on the registration screen and never
     * opens an authenticated session.
     *
     * @return void This test method does not return a value.
     */
    public function test_registration_post_returns_404(): void
    {
        $usersBefore = User::query()->count();

        $response = $this->from(route('register'))->post('/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Refused, not accepted: the visitor is sent back to the registration
        // screen with a surfaced error, never a 404 and never a dashboard.
        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'email' => __('app.errors.registration_unavailable'),
        ]);

        // A refused registration must not grow the user table ...
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);

        // ... nor carry an authenticated session: nobody is signed in.
        $this->assertGuest();
    }
}
