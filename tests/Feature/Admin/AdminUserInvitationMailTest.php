<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\InviteUserNotification;
use App\Notifications\VerifyEmail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * An admin-created account must receive the invitation mail, not a web
 * verification link it cannot use.
 *
 * `verification.verify` sits behind `auth` and the admin path stores a random,
 * unknown password, so the verification mail stranded every invited person: the
 * address-ownership proof required a session nobody could obtain. The signed
 * `/invitation/{user}` surface is the one they can actually complete, so both
 * admin creation paths must mail it and must not mail a verification link.
 */
final class AdminUserInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    public function test_the_web_admin_path_mails_a_signed_invitation_and_no_verification_link(): void
    {
        $response = $this->actingAs($this->administrator())->post(route('admin.users.store'), [
            'name' => 'Web Invitee',
            'email' => 'web-invitee@example.test',
            'role' => 'user',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users'));

        $invitee = User::query()->where('email', 'web-invitee@example.test')->firstOrFail();

        Notification::assertSentToTimes($invitee, InviteUserNotification::class, 1);
        Notification::assertNotSentTo($invitee, VerifyEmail::class);
        $this->assertInvitationUrlIsSignedFor($invitee);
    }

    public function test_the_api_admin_path_mails_a_signed_invitation_and_no_verification_link(): void
    {
        Sanctum::actingAs($this->administrator());

        $response = $this->postJson(route('api.v1.admin.users.store'), [
            'name' => 'Api Invitee',
            'email' => 'api-invitee@example.test',
            'locale' => 'en-US',
            'role' => 'user',
        ]);

        $response->assertCreated();

        $invitee = User::query()->where('email', 'api-invitee@example.test')->firstOrFail();

        Notification::assertSentToTimes($invitee, InviteUserNotification::class, 1);
        Notification::assertNotSentTo($invitee, VerifyEmail::class);
        $this->assertInvitationUrlIsSignedFor($invitee);
    }

    public function test_a_rejected_admin_creation_mails_nobody(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($this->administrator())
            ->post(route('admin.users.store'), [
                'name' => 'Duplicate Invitee',
                'email' => 'taken@example.test',
                'role' => 'user',
            ])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    /**
     * Assert the mailed action URL is a valid signed `invitation.show` URL.
     */
    private function assertInvitationUrlIsSignedFor(User $invitee): void
    {
        $url = null;

        Notification::assertSentTo(
            $invitee,
            InviteUserNotification::class,
            function (InviteUserNotification $notification) use ($invitee, &$url): bool {
                $url = $notification->toMail($invitee)->actionUrl;

                return is_string($url);
            },
        );

        $this->assertIsString($url);

        $signedRequest = Request::create($url);

        $this->assertTrue(URL::hasValidSignature($signedRequest));
        $this->assertSame(
            parse_url(route('invitation.show', ['user' => $invitee->getRouteKey()]), PHP_URL_PATH),
            $signedRequest->getPathInfo(),
        );
    }

    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
