<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'locale' => 'en-US',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(
                ['slug' => 'admin'],
                ['name' => 'Admin', 'slug' => 'admin']
            );
            $permissions = Permission::all();
            if ($permissions->isNotEmpty()) {
                $role->syncPermissions($permissions);
            }
            $user->assignRole($role);
        });
    }

    public function user(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(
                ['slug' => 'user'],
                ['name' => 'User', 'slug' => 'user']
            );
            $user->assignRole($role);
        });
    }

    public function readonly(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(
                ['slug' => 'readonly'],
                ['name' => 'Readonly', 'slug' => 'readonly']
            );
            $user->assignRole($role);
        });
    }
}
