<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\GenerateProductImagesJob;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_index_returns_paginated_resources(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $this->getJson(route('api.v1.admin.users.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $admin->id)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_non_admin_permission_returns_403(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.admin.users.index'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_image_generation_is_queued_and_returns_a_pollable_run(): void
    {
        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::factory()->pending()->create();
        Sanctum::actingAs($admin);

        $response = $this->withHeader('Idempotency-Key', 'generate-product-images')
            ->postJson(route('api.v1.admin.products.images.generate', $product))
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued');

        $runId = $response->json('data.id');
        Queue::assertPushed(GenerateProductImagesJob::class, fn (GenerateProductImagesJob $job): bool => $job->runId === $runId);
    }
}
