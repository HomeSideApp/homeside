<?php

namespace Tests\Feature\Registration;

use App\Models\Permission;
use App\Models\RegistrationAudit;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RegistrationRejected;
use App\Notifications\RegistrationRequested;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the administrator-registration notification: recipients come from the
 * approval permission, the audit listener is registered, and the rejection mail
 * never carries administrative reasoning or internal identifiers.
 */
class RegistrationRequestedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_recipients_are_resolved_from_the_approve_permission_not_a_role(): void
    {
        Notification::fake();

        $approver = $this->userWithPermissions(['admin.registrations.approve']);
        $member = User::factory()->create();
        $member->assignRole('user');

        $applicant = User::factory()->create(['approval_status' => 'pending']);

        RegistrationRequested::sendToAdministrators($applicant);

        Notification::assertSentTo($approver, RegistrationRequested::class);
        Notification::assertNotSentTo($member, RegistrationRequested::class);
    }

    public function test_a_suspended_administrator_is_not_a_recipient(): void
    {
        Notification::fake();

        $suspended = $this->userWithPermissions(['admin.registrations.approve']);
        $suspended->forceFill(['account_status' => 'suspended'])->save();

        RegistrationRequested::sendToAdministrators(User::factory()->create(['approval_status' => 'pending']));

        Notification::assertNothingSent();
    }

    public function test_the_audit_listener_that_triggers_the_notification_is_registered(): void
    {
        $this->assertTrue(
            RegistrationAudit::getEventDispatcher()->hasListeners('eloquent.created: '.RegistrationAudit::class),
        );
    }

    public function test_the_rejection_mail_never_reveals_the_reason_or_internal_identifiers(): void
    {
        $user = User::factory()->create();

        $html = (new RegistrationRejected)->toMail($user)->render();

        $this->assertStringNotContainsString($user->id, $html);
        $this->assertStringNotContainsString('reason', strtolower($html));
        $this->assertStringNotContainsString('rejected by', strtolower($html));
    }

    /**
     * Create a user holding exactly the named route permissions.
     *
     * @param  list<string>  $routeNames
     */
    private function userWithPermissions(array $routeNames): User
    {
        $role = Role::create(['name' => 'Test role '.Str::random(6), 'slug' => 'test-role-'.Str::random(8)]);
        $role->syncPermissions(Permission::query()->whereIn('route_name', $routeNames)->get());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
