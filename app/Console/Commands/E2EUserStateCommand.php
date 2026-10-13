<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use App\Contracts\RegistrationBlockRepository;
use App\Enums\AccountStatus;
use App\Models\RegistrationAudit;
use App\Models\User;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * Prints the backend state of one registration as JSON.
 *
 * This is an ASSERTION helper, never a shortcut: a scenario that is meant to
 * exercise a browser step must reach the state through the UI, and use this
 * only to confirm what the server actually persisted afterwards (for example
 * that an approval recorded the administrator's identity).
 */
class E2EUserStateCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:user-state {email : The fixture email to inspect}';

    protected $description = 'Print the persisted registration state for an E2E fixture account';

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->line(json_encode(['email' => $email, 'exists' => false]));

            return SymfonyCommand::SUCCESS;
        }

        $approved = RegistrationAudit::query()
            ->where('user_id', $user->id)
            ->where('action', 'approved')
            ->latest('created_at')
            ->first();

        $state = [
            'email' => $user->email,
            'exists' => true,
            'name' => $user->name,
            'approval_status' => $user->approval_status,
            'account_status' => $user->account_status instanceof AccountStatus
                ? $user->account_status->value
                : (string) $user->account_status,
            'email_verified' => $user->email_verified_at !== null,
            'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'roles' => $user->roles()->pluck('slug')->sort()->values()->all(),
            'active_identity_block' => app(RegistrationBlockRepository::class)
                ->isBlocked(RegistrationIdentifier::EMAIL, $user->email),
            'approved_by' => $approved?->actor_id !== null
                ? User::query()->whereKey($approved->actor_id)->value('email')
                : null,
            'approved_at' => $approved?->created_at?->toIso8601String(),
        ];

        $this->line(json_encode($state, JSON_THROW_ON_ERROR));

        return SymfonyCommand::SUCCESS;
    }
}
