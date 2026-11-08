<?php

namespace Tests\Feature\Registration;

use App\Contracts\RegistrationBlockRepository;
use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Models\RegistrationBlock;
use App\Models\User;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers persistent identity blocks: idempotency, release history and the
 * single-active-row invariant.
 */
class RegistrationBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'registration.block.key' => 'dedicated-block-key',
            'registration.block.key_id' => 'v1',
        ]);
    }

    private function repository(): RegistrationBlockRepository
    {
        return app(RegistrationBlockRepository::class);
    }

    private function block(string $email = 'blocked@example.com', ?User $actor = null): RegistrationBlock
    {
        return $this->repository()->block(
            RegistrationIdentifier::EMAIL,
            $email,
            RegistrationBlockReason::RejectedRegistration,
            RegistrationBlockSource::Admin,
            $actor,
            'Rejected during review.',
        );
    }

    public function test_blocking_an_identity_marks_it_blocked(): void
    {
        $actor = User::factory()->create();

        $block = $this->block(actor: $actor);

        $this->assertTrue($this->repository()->isBlocked(RegistrationIdentifier::EMAIL, 'blocked@example.com'));
        $this->assertTrue($block->isActive());
        $this->assertSame(RegistrationBlockReason::RejectedRegistration, $block->reason_code);
        $this->assertSame(RegistrationBlockSource::Admin, $block->source);
        $this->assertSame('Rejected during review.', $block->reason_note);
        $this->assertSame($actor->id, $block->created_by);
        $this->assertSame('v1', $block->identifier_key_id);
        $this->assertSame($block->identifier_hash, $block->active_identifier_hash);
    }

    public function test_blocking_is_idempotent(): void
    {
        $first = $this->block();
        $second = $this->block();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, RegistrationBlock::query()->active()->count());
    }

    public function test_active_block_for_returns_null_when_the_identity_is_free(): void
    {
        $this->assertNull($this->repository()->activeBlockFor(RegistrationIdentifier::EMAIL, 'free@example.com'));
        $this->assertFalse($this->repository()->isBlocked(RegistrationIdentifier::EMAIL, 'free@example.com'));
    }

    public function test_release_keeps_history_and_unblocks_the_identity(): void
    {
        $creator = User::factory()->create();
        $releaser = User::factory()->create();
        $block = $this->block(actor: $creator);

        $released = $this->repository()->release($block, $releaser);

        $this->assertFalse($this->repository()->isBlocked(RegistrationIdentifier::EMAIL, 'blocked@example.com'));
        $this->assertSame(1, RegistrationBlock::query()->count(), 'History must be retained, not deleted.');
        $this->assertNotNull($released->released_at);
        $this->assertSame($releaser->id, $released->released_by);
        $this->assertNull($released->active_identifier_hash);
        $this->assertSame($creator->id, $released->created_by);
    }

    public function test_release_is_idempotent(): void
    {
        $releaser = User::factory()->create();
        $block = $this->block();

        $first = $this->repository()->release($block, $releaser);
        $second = $this->repository()->release($block->refresh(), $releaser);

        $this->assertSame($first->released_at->toIso8601String(), $second->released_at->toIso8601String());
        $this->assertSame(1, RegistrationBlock::query()->count());
        $this->assertSame($releaser->id, $second->released_by);
    }

    public function test_a_new_active_block_is_allowed_after_a_release(): void
    {
        $this->repository()->release($this->block());

        $again = $this->block();

        $this->assertSame(2, RegistrationBlock::query()->count());
        $this->assertSame(1, RegistrationBlock::query()->active()->count());
        $this->assertTrue($again->isActive());
    }

    public function test_release_for_identifier_returns_null_when_none_is_active(): void
    {
        $this->assertNull($this->repository()->releaseForIdentifier(RegistrationIdentifier::EMAIL, 'free@example.com'));

        $this->block();
        $released = $this->repository()->releaseForIdentifier(RegistrationIdentifier::EMAIL, 'blocked@example.com');

        $this->assertNotNull($released);
        $this->assertNull($this->repository()->releaseForIdentifier(RegistrationIdentifier::EMAIL, 'blocked@example.com'));
    }

    public function test_duplicate_active_rows_are_impossible(): void
    {
        $block = $this->block();
        $hash = app(RegistrationIdentifier::class)->hash(RegistrationIdentifier::EMAIL, 'blocked@example.com');

        $this->expectException(UniqueConstraintViolationException::class);

        RegistrationBlock::create([
            'identifier_type' => RegistrationIdentifier::EMAIL,
            'identifier_hash' => $hash,
            'reason_code' => RegistrationBlockReason::ManualBlock,
            'source' => RegistrationBlockSource::Admin,
        ]);
    }

    public function test_a_block_created_with_another_key_does_not_match_after_rotation(): void
    {
        $this->block();

        config(['registration.block.key' => 'rotated-block-key']);

        $this->assertFalse($this->repository()->isBlocked(RegistrationIdentifier::EMAIL, 'blocked@example.com'));
    }
}
