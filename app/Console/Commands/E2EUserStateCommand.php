<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use App\Contracts\RegistrationBlockRepository;
use App\Enums\AccountStatus;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\RegistrationAudit;
use App\Models\RegistrationBlock;
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
            $this->line(json_encode([
                'email' => $email,
                'exists' => false,
                'active_identity_block' => app(RegistrationBlockRepository::class)
                    ->isBlocked(RegistrationIdentifier::EMAIL, $email),
                'google_linked' => false,
                ...$this->blockState($email),
            ], JSON_THROW_ON_ERROR));

            return SymfonyCommand::SUCCESS;
        }

        $approved = RegistrationAudit::query()
            ->where('user_id', $user->id)
            ->where('action', 'approved')
            ->latest('created_at')
            ->first();

        $released = RegistrationAudit::query()
            ->where('user_id', $user->id)
            ->where('action', 'rejection_released')
            ->latest('created_at')
            ->first();

        $state = [
            'email' => $user->email,
            'id' => $user->id,
            'exists' => true,
            'name' => $user->name,
            'approval_status' => $user->approval_status,
            'account_status' => $user->account_status instanceof AccountStatus
                ? $user->account_status->value
                : (string) $user->account_status,
            'email_verified' => $user->email_verified_at !== null,
            'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'google_linked' => $user->googleIdentity()->exists(),
            'roles' => $user->roles()->pluck('slug')->sort()->values()->all(),
            'active_identity_block' => app(RegistrationBlockRepository::class)
                ->isBlocked(RegistrationIdentifier::EMAIL, $user->email),
            ...$this->blockState($user->email),
            'approved_by' => $approved?->actor_id !== null
                ? User::query()->whereKey($approved->actor_id)->value('email')
                : null,
            'approved_at' => $approved?->created_at?->toIso8601String(),
            'rejection_released_by' => $released?->actor_id !== null
                ? User::query()->whereKey($released->actor_id)->value('email')
                : null,
            'rejection_released_at' => $released?->created_at?->toIso8601String(),
            'active_household' => $user->active_household_id !== null
                ? Household::query()->whereKey($user->active_household_id)->value('name')
                : null,
            'household_access' => HouseholdMember::query()
                ->where('user_id', $user->id)
                ->with('household')
                ->get()
                ->mapWithKeys(fn (HouseholdMember $member): array => [
                    (string) $member->household?->name => [
                        'profile' => $member->access_profile?->value,
                        'module_access' => $member->permissions(),
                    ],
                ])
                ->all(),
        ];

        $this->line(json_encode($state, JSON_THROW_ON_ERROR));

        return SymfonyCommand::SUCCESS;
    }

    /**
     * Read the identity block's current and most recent release state.
     *
     * A release is recorded on the BLOCK row (released_at/released_by), which is
     * a different record from the `rejection_released` audit entry. Exposing it
     * here lets a scenario assert which administrator lifted a block without
     * reading the database directly.
     *
     * @param  string  $email  The identity whose block state is read.
     * @return array{active_identity_block_id: string|null, block_released_by: string|null, block_released_at: string|null} The block state.
     */
    private function blockState(string $email): array
    {
        $active = app(RegistrationBlockRepository::class)
            ->activeBlockFor(RegistrationIdentifier::EMAIL, $email);

        $released = RegistrationBlock::query()
            ->where('identifier_type', RegistrationIdentifier::EMAIL)
            ->where('identifier_hash', app(RegistrationIdentifier::class)->hash(RegistrationIdentifier::EMAIL, $email))
            ->whereNotNull('released_at')
            ->latest('released_at')
            ->first();

        return [
            'active_identity_block_id' => $active?->id,
            'block_released_by' => $released?->released_by !== null
                ? User::query()->whereKey($released->released_by)->value('email')
                : null,
            'block_released_at' => $released?->released_at?->toIso8601String(),
        ];
    }
}
