<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_options_create_the_user_with_the_requested_role_plus_the_user_base(): void
    {
        Notification::fake();

        $this->artisan('app:create-user', [
            '--name' => 'Catalog operator',
            '--email' => 'catalog@example.test',
            '--role' => 'catalog-manager',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'catalog@example.test')->firstOrFail();

        $this->assertSame('Catalog operator', $user->name);
        $this->assertSame(['catalog-manager', 'user'], $user->getRoleNames()->sort()->values()->all());
    }

    public function test_options_dispatch_the_registered_event(): void
    {
        Event::fake([Registered::class]);

        $this->artisan('app:create-user', [
            '--name' => 'Platform admin',
            '--email' => 'admin@example.test',
            '--role' => 'admin',
        ])->assertSuccessful();

        Event::assertDispatched(
            Registered::class,
            fn (Registered $event): bool => $event->user->email === 'admin@example.test',
        );
    }

    public function test_a_user_role_only_assigns_the_base_role_once(): void
    {
        Notification::fake();

        $this->artisan('app:create-user', [
            '--name' => 'Base user',
            '--email' => 'base@example.test',
            '--role' => 'user',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'base@example.test')->firstOrFail();

        $this->assertSame(['user'], $user->getRoleNames()->all());
    }

    public function test_unknown_role_fails_and_lists_the_available_slugs(): void
    {
        $available = implode(', ', Role::query()->orderBy('slug')->pluck('slug')->all());

        $this->artisan('app:create-user', [
            '--name' => 'Unknown role',
            '--email' => 'unknown-role@example.test',
            '--role' => 'does-not-exist',
        ])
            ->expectsOutputToContain(__('console.create_user.unknown_role', [
                'role' => 'does-not-exist',
                'roles' => $available,
            ]))
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'unknown-role@example.test']);
    }

    public function test_an_already_used_email_fails_and_creates_nothing(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->artisan('app:create-user', [
            '--name' => 'Duplicated',
            '--email' => 'taken@example.test',
            '--role' => 'admin',
        ])
            ->expectsOutputToContain(__('console.create_user.email_exists'))
            ->assertFailed();

        $this->assertSame(1, User::query()->where('email', 'taken@example.test')->count());
    }

    public function test_an_invalid_email_fails(): void
    {
        $this->artisan('app:create-user', [
            '--name' => 'Invalid email',
            '--email' => 'not-an-email',
            '--role' => 'admin',
        ])
            ->expectsOutputToContain(__('console.create_user.invalid_email'))
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['name' => 'Invalid email']);
    }

    public function test_a_partial_set_of_options_fails_naming_the_missing_ones(): void
    {
        $this->artisan('app:create-user', [
            '--name' => 'Partial',
        ])
            ->expectsOutputToContain(__('console.create_user.missing_options', [
                'options' => '--email, --role',
            ]))
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['name' => 'Partial']);
    }
}
