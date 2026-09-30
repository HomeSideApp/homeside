<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SyncContactSourceJob;
use App\Models\JobRun;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Job Monitoring']);
        Permission::create(['name' => 'view job monitoring dashboard', 'route_name' => 'jobs-monitor.index', 'description' => 'Ver dashboard de job monitoring', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view job runs', 'route_name' => 'jobs-monitor.jobs', 'description' => 'Ver listado de ejecuciones de jobs', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view job run', 'route_name' => 'jobs-monitor.show', 'description' => 'Ver detalle de una ejecución de job', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'retry failed jobs', 'route_name' => 'jobs-monitor.retry', 'description' => 'Reintentar jobs fallidos', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(Permission::all());

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_list_job_runs(): void
    {
        JobRun::factory()->count(3)->create();

        $response = $this->getJson(route('api.v1.jobs-monitor.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'uuid', 'job_class', 'full_job_class', 'queue', 'status', 'duration', 'created_at'],
                ],
            ]);
    }

    public function test_job_runs_can_be_filtered_by_status(): void
    {
        JobRun::factory()->failed()->count(2)->create();
        JobRun::factory()->processed()->count(1)->create();

        $response = $this->getJson(route('api.v1.jobs-monitor.index', ['status' => 'failed']));

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_user_can_show_a_job_run(): void
    {
        $job = JobRun::factory()->failed()->create();

        $this->getJson(route('api.v1.jobs-monitor.show', $job->id))
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_showing_an_unknown_job_run_returns_404(): void
    {
        $this->getJson(route('api.v1.jobs-monitor.show', '00000000-0000-0000-0000-000000000000'))
            ->assertNotFound();
    }

    public function test_statistics_endpoint_returns_aggregated_counters(): void
    {
        JobRun::factory()->processed()->count(2)->create();
        JobRun::factory()->failed()->count(1)->create();

        $this->getJson(route('api.v1.jobs-monitor.statistics'))
            ->assertOk()
            ->assertJsonStructure(['total', 'processed', 'failed', 'processing', 'success_rate'])
            ->assertJsonPath('total', 3)
            ->assertJsonPath('failed', 1);
    }

    public function test_queue_depth_endpoint_returns_an_array(): void
    {
        $this->getJson(route('api.v1.jobs-monitor.queue-depth'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_failed_endpoint_returns_only_failed_jobs(): void
    {
        JobRun::factory()->failed()->count(2)->create();
        JobRun::factory()->processed()->count(1)->create();

        $response = $this->getJson(route('api.v1.jobs-monitor.failed'));

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_jobs_by_tag_endpoint_filters_by_tag(): void
    {
        JobRun::factory()->create(['tags' => [['tag' => 'orders']]]);
        JobRun::factory()->create(['tags' => null]);

        $response = $this->getJson(route('api.v1.jobs-monitor.jobs-by-tag', 'orders'));

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_statistics_endpoint_resolves_the_index_permission_alias(): void
    {
        $role = Role::create(['name' => 'dashboard-only', 'slug' => 'dashboard-only']);
        $role->syncPermissions(['view job monitoring dashboard']);
        $user = User::factory()->create();
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.jobs-monitor.statistics'))->assertOk();
        $this->getJson(route('api.v1.jobs-monitor.queue-depth'))->assertOk();
        $this->getJson(route('api.v1.jobs-monitor.failed'))->assertForbidden();
    }

    public function test_user_without_permissions_gets_403(): void
    {
        $role = Role::create(['name' => 'plain', 'slug' => 'plain']);
        $user = User::factory()->create();
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.jobs-monitor.index'))->assertForbidden();
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(route('api.v1.jobs-monitor.index'))->assertUnauthorized();
    }

    public function test_retry_rejects_jobs_that_are_not_failed(): void
    {
        $job = JobRun::factory()->processed()->create();

        $this->postJson(route('api.v1.jobs-monitor.retry', $job->id))
            ->assertStatus(422);

        $this->assertDatabaseCount('job_runs', 1);
    }

    public function test_retry_queues_a_failed_job_with_stored_payload(): void
    {
        Queue::fake();

        $job = JobRun::factory()->failed()->create([
            'payload' => ['data' => ['command' => serialize(new SyncContactSourceJob('source-1'))]],
        ]);

        $this->postJson(route('api.v1.jobs-monitor.retry', $job->id))
            ->assertOk()
            ->assertJsonPath('message', 'Job queued for retry successfully');

        $this->assertDatabaseHas('job_runs', ['original_job_run_id' => $job->id]);
        Queue::assertPushed(SyncContactSourceJob::class);
    }
}
