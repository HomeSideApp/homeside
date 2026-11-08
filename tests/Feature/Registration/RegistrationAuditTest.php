<?php

namespace Tests\Feature\Registration;

use App\Enums\RegistrationAuditAction;
use App\Models\RegistrationAudit;
use App\Models\User;
use App\Support\Registration\RegistrationAuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the append-only registration audit trail.
 */
class RegistrationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_append_records_the_expected_transition(): void
    {
        $user = User::factory()->create(['approval_status' => 'pending']);
        $actor = User::factory()->create();

        $audit = app(RegistrationAuditRecorder::class)->append(
            RegistrationAuditAction::Approved,
            $user,
            $actor,
            previousStatus: 'pending',
            newStatus: 'approved',
            channel: 'api',
            origin: 'web',
            invitationReference: 'invitation-123',
        );

        $this->assertSame(RegistrationAuditAction::Approved, $audit->action);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame('pending', $audit->previous_status);
        $this->assertSame('approved', $audit->new_status);
        $this->assertSame('api', $audit->channel);
        $this->assertSame('web', $audit->origin);
        $this->assertSame('invitation-123', $audit->invitation_reference);
        $this->assertNotNull($audit->created_at);
        $this->assertDatabaseHas('registration_audits', [
            'id' => $audit->id,
            'action' => 'approved',
            'user_id' => $user->id,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_audit_history_survives_user_deletion(): void
    {
        $user = User::factory()->create();
        $audit = app(RegistrationAuditRecorder::class)->append(
            RegistrationAuditAction::Suspended,
            $user,
            null,
            'active',
            'suspended',
        );

        $user->delete();

        $audit->refresh();
        $this->assertNull($audit->user_id);
        $this->assertDatabaseHas('registration_audits', ['id' => $audit->id, 'action' => 'suspended']);
    }

    public function test_audit_rows_never_carry_credentials_and_are_append_only(): void
    {
        $user = User::factory()->create();

        $audit = app(RegistrationAuditRecorder::class)->append(
            RegistrationAuditAction::RegistrationCreated,
            $user,
        );

        $keys = array_keys($audit->getAttributes());

        foreach (['token', 'password', 'secret', 'two_factor_secret', 'recovery_code', 'challenge'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys);
        }

        $this->assertNotContains('updated_at', $keys);
        $this->assertSame(RegistrationAudit::UPDATED_AT, null);
    }
}
