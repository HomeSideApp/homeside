<?php

namespace Tests\Feature\Auth;

use App\Models\HouseholdInviteLink;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

/**
 * The invitation landing page only offers Google when it can actually start it.
 *
 * The set-password page is shared with the reusable invite link surface, so the
 * Google affordance travels as an optional prop: the operator's Google client id
 * decides whether it exists at all, and the URL has to carry a valid signature
 * because the start route is behind the `signed` middleware.
 */
final class InvitationShowGoogleUrlTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_show_page_exposes_a_usable_signed_google_start_url_when_google_is_configured(): void
    {
        config(['services.google.client_id' => 'configured-client-id']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-invitee',
            'email' => 'invited@example.com',
            'email_verified' => true,
        ]));
        $invitee = $this->pendingInvitee();

        $googleUrl = $this->googleUrlFromShowPage($invitee);

        $this->assertIsString($googleUrl);
        $this->assertNotSame('', $googleUrl);

        $signedRequest = Request::create($googleUrl);
        $this->assertTrue(URL::hasValidSignature($signedRequest));
        $this->assertSame(
            parse_url(route('invitation.google.redirect', ['user' => $invitee->getRouteKey()]), PHP_URL_PATH),
            $signedRequest->getPathInfo(),
        );

        // The exposed URL must be the one that actually starts the flow.
        $this->get($googleUrl)->assertRedirect('https://socialite.fake/google/authorize');
        $this->assertNotNull(session('invitation.google.pending'));
        $this->assertNull($invitee->fresh()->email_verified_at);
    }

    public function test_show_page_passes_a_null_google_url_when_no_client_id_is_configured(): void
    {
        config(['services.google.client_id' => null]);
        $invitee = $this->pendingInvitee();

        $this->get($this->showUrl($invitee))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/SetPassword')
                ->where('googleUrl', null));
    }

    public function test_show_page_passes_a_null_google_url_when_the_client_id_is_blank(): void
    {
        config(['services.google.client_id' => '']);
        $invitee = $this->pendingInvitee();

        $this->get($this->showUrl($invitee))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/SetPassword')
                ->where('googleUrl', null));
    }

    public function test_invite_link_page_still_renders_without_the_google_prop(): void
    {
        config(['services.google.client_id' => 'configured-client-id']);
        $owner = User::factory()->create();
        $household = $this->createHouseholdForUser($owner);
        $link = HouseholdInviteLink::create([
            'household_id' => $household->id,
            'created_by' => $owner->id,
            'token' => 'token-'.str()->random(40),
        ]);

        $this->get(route('invite-links.show', $link->token))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/SetPassword')
                ->where('mode', 'link')
                ->missing('googleUrl'));
    }

    /**
     * Read the `googleUrl` prop the invitation page exposes.
     *
     * @param  User  $invitee  The pending invited account.
     * @return mixed The exposed prop value.
     */
    private function googleUrlFromShowPage(User $invitee): mixed
    {
        $googleUrl = null;

        $this->get($this->showUrl($invitee))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$googleUrl): void {
                $page->component('auth/SetPassword')
                    ->where('googleUrl', function (mixed $url) use (&$googleUrl): bool {
                        $googleUrl = $url;

                        return true;
                    });
            });

        return $googleUrl;
    }

    /**
     * Build the signed invitation landing URL for an invitee.
     *
     * @param  User  $user  The invited account.
     * @return string The signed URL.
     */
    private function showUrl(User $user): string
    {
        return URL::temporarySignedRoute('invitation.show', now()->addMinutes(30), ['user' => $user->getRouteKey()]);
    }

    /**
     * Create a pending, administrator-invited account.
     *
     * @return User The pending invitee.
     */
    private function pendingInvitee(): User
    {
        return User::factory()->unverified()->create([
            'name' => 'invited@example.com',
            'email' => 'invited@example.com',
        ]);
    }
}
