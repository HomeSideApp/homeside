<?php

namespace Tests\Feature\Economy;

use App\Enums\EconomicImportStatus;
use App\Enums\HouseholdRole;
use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EconomicImportIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('user');
        $this->member = User::factory()->create();
        $this->member->assignRole('user');

        $this->household = Household::factory()->create(['created_by' => $this->owner->id]);
        HouseholdMember::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'role' => HouseholdRole::Admin,
        ]);
        HouseholdMember::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $this->member->id,
            'role' => HouseholdRole::Member,
        ]);
    }

    private function importFor(?User $user, ?Household $household, EconomicImportStatus $status): EconomicImport
    {
        $document = EconomicDocument::factory()->create([
            'household_id' => $household?->id,
            'uploaded_by' => ($user ?? $this->owner)->id,
        ]);

        return EconomicImport::factory()->create([
            'household_id' => $household?->id,
            'created_by' => ($user ?? $this->owner)->id,
            'document_id' => $document->id,
            'status' => $status,
        ]);
    }

    public function test_private_index_lists_only_the_users_imports(): void
    {
        $mine = $this->importFor($this->owner, null, EconomicImportStatus::ReadyForReview);
        $other = User::factory()->create();
        $other->assignRole('user');
        $theirs = $this->importFor($other, null, EconomicImportStatus::ReadyForReview);

        $response = $this->actingAs($this->owner)
            ->get(route('economy.me.imports.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('economy/imports/Index')
                ->has('imports.data', 1)
                ->where('imports.data.0.id', $mine->id)
                ->where('pendingCount', 1)
                ->missing('imports.data.1')
        );

        $this->assertNotSame($mine->id, $theirs->id);
    }

    public function test_household_index_is_visible_to_every_member(): void
    {
        $import = $this->importFor($this->owner, $this->household, EconomicImportStatus::Processing);

        $response = $this->actingAs($this->member)
            ->get(route('households.economy.imports.index', $this->household));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->has('imports.data', 1)
                ->where('imports.data.0.id', $import->id)
                ->where('pendingCount', 1)
        );
    }

    public function test_household_index_never_exposes_private_imports(): void
    {
        $this->importFor($this->owner, null, EconomicImportStatus::ReadyForReview);

        $response = $this->actingAs($this->member)
            ->get(route('households.economy.imports.index', $this->household));

        $response->assertInertia(
            fn ($page) => $page->has('imports.data', 0),
        );
    }

    public function test_status_filter_narrows_the_listing(): void
    {
        $this->importFor($this->owner, null, EconomicImportStatus::ReadyForReview);
        $failed = $this->importFor($this->owner, null, EconomicImportStatus::Failed);

        $response = $this->actingAs($this->owner)
            ->get(route('economy.me.imports.index', ['status' => 'failed']));

        $response->assertInertia(
            fn ($page) => $page
                ->has('imports.data', 1)
                ->where('imports.data.0.id', $failed->id)
                ->where('filters.status', 'failed')
        );
    }

    public function test_history_filter_lists_confirmed_and_discarded_imports(): void
    {
        $this->importFor($this->owner, null, EconomicImportStatus::ReadyForReview);
        $confirmed = $this->importFor($this->owner, null, EconomicImportStatus::Confirmed);
        $discarded = $this->importFor($this->owner, null, EconomicImportStatus::Discarded);

        $response = $this->actingAs($this->owner)
            ->get(route('economy.me.imports.index', ['status' => 'history']));

        $response->assertInertia(
            fn ($page) => $page
                ->has('imports.data', 2)
                ->where('imports.data.0.id', $discarded->id)
                ->where('imports.data.1.id', $confirmed->id)
        );
    }

    public function test_member_cannot_open_the_review_of_a_private_import(): void
    {
        $private = $this->importFor($this->owner, null, EconomicImportStatus::ReadyForReview);

        $this->actingAs($this->member)
            ->get(route('economy.me.imports.show', $private))
            ->assertForbidden();
    }

    public function test_prune_command_marks_stale_imports_as_failed(): void
    {
        $stale = $this->importFor($this->owner, null, EconomicImportStatus::Pending);
        $stale->forceFill(['created_at' => now()->subDays(2)])->save();

        $fresh = $this->importFor($this->owner, null, EconomicImportStatus::Pending);

        $this->artisan('economy:prune-imports')->assertExitCode(0);

        $this->assertSame(EconomicImportStatus::Failed, $stale->refresh()->status);
        $this->assertSame('stale', $stale->error_code);
        $this->assertSame(EconomicImportStatus::Pending, $fresh->refresh()->status);
    }
}
