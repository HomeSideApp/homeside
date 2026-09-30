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
    protected $signature = 'app:create-user';

    protected $description = 'Create a new user and send invitation email';

    /**
     * Execute the command.
     *
     * @return int The integer result.
     */
    public function handle(): int
    {
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

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->assignRole($role);

        event(new Registered($user));

        $this->info('');
        $this->info(__('console.create_user.created'));
        $this->info('');

        return self::SUCCESS;
    }
}
