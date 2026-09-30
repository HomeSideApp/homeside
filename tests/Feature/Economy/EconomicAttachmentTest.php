<?php

namespace Tests\Feature\Economy;

use App\Enums\HouseholdRole;
use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionAttachment;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EconomicAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
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

    public function test_user_can_attach_images_to_a_private_transaction(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->owner)->create();

        foreach (range(1, 3) as $index) {
            $this->actingAs($this->owner)
                ->post(route('economy.me.attachments.store', $transaction), [
                    'image' => UploadedFile::fake()
                        ->image("ticket-{$index}.jpg", 800, 600 + $index)
                        ->size(50 + $index),
                ])
                ->assertRedirect();
        }

        $this->assertSame(3, EconomicTransactionAttachment::query()
            ->where('transaction_id', $transaction->id)
            ->count());

        $response = $this->actingAs($this->owner)
            ->get(route('economy.me.show', $transaction));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->has('transaction.attachments', 3)
                ->where('transaction.attachments.0.is_mine', true)
        );
    }

    public function test_attachment_limit_is_enforced(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->owner)->create();

        EconomicTransactionAttachment::factory()->count(5)->create([
            'transaction_id' => $transaction->id,
            'uploaded_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('economy.me.attachments.store', $transaction), [
                'image' => UploadedFile::fake()->image('extra.jpg'),
            ])
            ->assertSessionHasErrors('attachments');
    }

    public function test_pdf_and_oversized_images_are_rejected(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->owner)->create();

        $this->actingAs($this->owner)
            ->post(route('economy.me.attachments.store', $transaction), [
                'image' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('image');

        $this->actingAs($this->owner)
            ->post(route('economy.me.attachments.store', $transaction), [
                'image' => UploadedFile::fake()->create('huge.jpg', 11000, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('image');
    }

    public function test_deleting_an_attachment_removes_the_file_from_disk(): void
    {
        $transaction = EconomicTransaction::factory()->private()->createdBy($this->owner)->create();

        $this->actingAs($this->owner)->post(route('economy.me.attachments.store', $transaction), [
            'image' => UploadedFile::fake()->image('ticket.jpg'),
        ]);

        $attachment = EconomicTransactionAttachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($this->owner)
            ->delete(route('economy.me.attachments.destroy', [$transaction, $attachment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('economic_transaction_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_member_cannot_delete_another_users_attachment_in_a_shared_transaction(): void
    {
        $transaction = EconomicTransaction::factory()
            ->forHousehold($this->household)
            ->createdBy($this->owner)
            ->shared()
            ->create();

        $attachment = EconomicTransactionAttachment::factory()->create([
            'transaction_id' => $transaction->id,
            'uploaded_by' => $this->owner->id,
        ]);

        $this->actingAs($this->member)
            ->delete(route('households.economy.attachments.destroy', [
                $this->household,
                $transaction,
                $attachment,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('economic_transaction_attachments', ['id' => $attachment->id]);
    }

    public function test_non_member_cannot_download_an_attachment(): void
    {
        $transaction = EconomicTransaction::factory()
            ->forHousehold($this->household)
            ->createdBy($this->owner)
            ->shared()
            ->create();

        $attachment = EconomicTransactionAttachment::factory()->create([
            'transaction_id' => $transaction->id,
            'uploaded_by' => $this->owner->id,
        ]);

        Storage::disk('local')->put($attachment->path, 'image-bytes');

        $intruder = User::factory()->create();
        $intruder->assignRole('user');

        $this->actingAs($intruder)
            ->get(route('households.economy.attachments.file', [
                $this->household,
                $transaction,
                $attachment,
            ]))
            ->assertRedirect();

        $this->actingAs($this->member)
            ->get(route('households.economy.attachments.file', [
                $this->household,
                $transaction,
                $attachment,
            ]))
            ->assertOk();
    }

    public function test_member_cannot_download_attachments_of_a_personal_transaction(): void
    {
        $transaction = EconomicTransaction::factory()
            ->forHousehold($this->household)
            ->createdBy($this->owner)
            ->create();

        $attachment = EconomicTransactionAttachment::factory()->create([
            'transaction_id' => $transaction->id,
            'uploaded_by' => $this->owner->id,
        ]);
        Storage::disk('local')->put($attachment->path, 'image-bytes');

        $this->actingAs($this->member)
            ->get(route('households.economy.attachments.file', [
                $this->household,
                $transaction,
                $attachment,
            ]))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->get(route('households.economy.attachments.file', [
                $this->household,
                $transaction,
                $attachment,
            ]))
            ->assertOk();
    }
}
