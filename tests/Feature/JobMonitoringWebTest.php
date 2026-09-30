<?php

namespace Tests\Feature;

use App\Jobs\SyncContactSourceJob;
use App\Models\JobRun;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobMonitoringWebTest extends TestCase
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
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('jobs-monitor.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_permission_gets_403_on_dashboard(): void
    {
        $role = Role::create(['name' => 'plain', 'slug' => 'plain']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('jobs-monitor.index'))->assertForbidden();
    }

    public function test_dashboard_renders_with_statistics_and_recent_jobs(): void
    {
        JobRun::factory()->processed()->count(2)->create();
        JobRun::factory()->failed()->count(1)->create();

        $response = $this->actingAs($this->user)->get(route('jobs-monitor.index'));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('jobs-monitor/Dashboard')
            ->where('filters.period', '24h')
            ->where('statistics.total', 3)
            ->where('statistics.processed', 2)
            ->where('statistics.failed', 1)
            ->has('recentJobs.data', 3)
            ->has('queueDepths')
            ->has('topFailingJobs'));
    }

    public function test_dashboard_respects_the_period_query_parameter(): void
    {
        $response = $this->actingAs($this->user)->get(route('jobs-monitor.index', ['period' => '7d']));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('jobs-monitor/Dashboard')
            ->where('filters.period', '7d'));
    }

    public function test_job_list_filters_by_status(): void
    {
        JobRun::factory()->failed()->count(2)->create();
        JobRun::factory()->processed()->count(1)->create();

        $response = $this->actingAs($this->user)->get(route('jobs-monitor.jobs', ['status' => 'failed']));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('jobs-monitor/Jobs')
            ->where('filters.status', 'failed')
            ->has('jobs.data', 2)
            ->has('queues'));
    }

    public function test_job_details_page_renders(): void
    {
        $job = JobRun::factory()->failed()->create();

        $response = $this->actingAs($this->user)->get(route('jobs-monitor.show', $job->id));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('jobs-monitor/Show')
            ->where('job.id', $job->id)
            ->where('job.status', 'failed'));
    }

    public function test_job_details_page_returns_404_for_unknown_job(): void
    {
        $this->actingAs($this->user)
            ->get(route('jobs-monitor.show', '00000000-0000-0000-0000-000000000000'))
            ->assertNotFound();
    }

    public function test_retry_rejects_jobs_that_are_not_failed(): void
    {
        $job = JobRun::factory()->processed()->create();

        $this->actingAs($this->user)
            ->from(route('jobs-monitor.jobs'))
            ->post(route('jobs-monitor.retry', $job->id))
            ->assertRedirect(route('jobs-monitor.jobs'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('job_runs', 1);
    }

    public function test_retry_rejects_failed_jobs_without_stored_payload(): void
    {
        $job = JobRun::factory()->failed()->create();

        $this->actingAs($this->user)
            ->from(route('jobs-monitor.jobs'))
            ->post(route('jobs-monitor.retry', $job->id))
            ->assertRedirect(route('jobs-monitor.jobs'))
            ->assertSessionHas('error');
    }

    public function test_retry_queues_a_failed_job_with_stored_payload(): void
    {
        Queue::fake();

        $job = JobRun::factory()->failed()->create([
            'payload' => ['data' => ['command' => serialize(new SyncContactSourceJob('source-1'))]],
        ]);

        $this->actingAs($this->user)
            ->from(route('jobs-monitor.jobs'))
            ->post(route('jobs-monitor.retry', $job->id))
            ->assertRedirect(route('jobs-monitor.jobs'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('job_runs', ['original_job_run_id' => $job->id]);
        Queue::assertPushed(SyncContactSourceJob::class);
    }

    public function test_retry_requires_the_retry_permission(): void
    {
        $role = Role::create(['name' => 'viewer', 'slug' => 'viewer']);
        $role->syncPermissions(['view job monitoring dashboard']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $job = JobRun::factory()->failed()->create();

        $this->actingAs($user)
            ->post(route('jobs-monitor.retry', $job->id))
            ->assertForbidden();
    }

    public function test_seeder_creates_job_monitoring_permissions_for_the_admin_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $routeNames = ['jobs-monitor.index', 'jobs-monitor.jobs', 'jobs-monitor.show', 'jobs-monitor.retry'];

        foreach ($routeNames as $routeName) {
            $this->assertDatabaseHas('permissions', ['route_name' => $routeName]);
        }

        $admin = Role::where('slug', 'admin')->firstOrFail();
        $adminRouteNames = $admin->permissions()->pluck('route_name');

        foreach ($routeNames as $routeName) {
            $this->assertTrue($adminRouteNames->contains($routeName), "Admin role is missing the {$routeName} permission.");
        }
    }
}
