<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Class CreateUser
 *
 * This command creates a new user interactively and sends the invitation email.
 */
class CreateUser extends Command
{
    protected $signature = 'app:create-user
                            {--name= : The name of the new user}
                            {--email= : The email address of the new user}
                            {--role= : The slug of the role assigned to the new user}';

    protected $description = 'Create a new user and send invitation email';

    /**
     * Execute the command.
     *
     * @return int The integer result.
     */
    public function handle(): int
    {
        if ($this->hasAnyOption()) {
            return $this->handleNonInteractive();
        }

        $name = text(
            label: __('console.create_user.name'),
            required: true,
        );

        $email = text(
            label: __('console.create_user.email'),
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => __('console.create_user.invalid_email'),
                User::where('email', $value)->exists() => __('console.create_user.email_exists'),
                default => null,
            },
        );

        $roles = Role::pluck('slug')->toArray();
        $role = (string) select(
            label: __('console.create_user.role'),
            options: $roles,
        );

        $this->info('');
        $this->line(__('console.create_user.summary'));
        $this->line(__('console.create_user.summary_name', ['name' => $name]));
        $this->line(__('console.create_user.summary_email', ['email' => $email]));
        $this->line(__('console.create_user.summary_role', ['role' => $role]));
        $this->info('');

        if (! confirm(label: __('console.create_user.confirm'), default: true)) {
            $this->warn(__('console.create_user.cancelled'));

            return self::SUCCESS;
        }

        $this->createUser($name, $email, $role);

        return self::SUCCESS;
    }

    /**
     * Determine whether any of the non-interactive options was supplied.
     *
     * @return bool True when at least one option carries a value.
     */
    private function hasAnyOption(): bool
    {
        foreach ($this->suppliedOptions() as $value) {
            if ($this->isFilled($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the user from the command options without any prompt.
     *
     * @return int The integer result.
     */
    private function handleNonInteractive(): int
    {
        $missing = [];
        foreach ($this->suppliedOptions() as $option => $value) {
            if (! $this->isFilled($value)) {
                $missing[] = '--'.$option;
            }
        }

        if ($missing !== []) {
            $this->error(__('console.create_user.missing_options', ['options' => implode(', ', $missing)]));

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        $email = trim((string) $this->option('email'));
        $role = trim((string) $this->option('role'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error(__('console.create_user.invalid_email'));

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error(__('console.create_user.email_exists'));

            return self::FAILURE;
        }

        $availableRoles = Role::query()->orderBy('slug')->pluck('slug')->all();

        if (! in_array($role, $availableRoles, true)) {
            $this->error(__('console.create_user.unknown_role', [
                'role' => $role,
                'roles' => implode(', ', $availableRoles),
            ]));

            return self::FAILURE;
        }

        $this->createUser($name, $email, $role);

        return self::SUCCESS;
    }

    /**
     * Create the user, assign the roles and fire the registration event.
     *
     * @param  string  $name  The user name.
     * @param  string  $email  The user email address.
     * @param  string  $role  The slug of the role assigned to the user.
     * @return User The created user.
     */
    private function createUser(string $name, string $email, string $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->assignRole($role);
        if ($role !== 'user') {
            $user->assignRole('user');
        }

        event(new Registered($user));

        $this->info('');
        $this->info(__('console.create_user.created'));
        $this->info('');

        return $user;
    }

    /**
     * Map the non-interactive option names to their raw values.
     *
     * @return array<string, mixed> The option values keyed by option name.
     */
    private function suppliedOptions(): array
    {
        return [
            'name' => $this->option('name'),
            'email' => $this->option('email'),
            'role' => $this->option('role'),
        ];
    }

    /**
     * Determine whether an option value was actually supplied.
     *
     * @param  mixed  $value  The raw option value.
     * @return bool True when the value is a non-empty string.
     */
    private function isFilled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
