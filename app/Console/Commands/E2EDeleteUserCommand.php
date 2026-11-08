<?php

namespace App\Console\Commands;

use App\Actions\Users\DeleteUser;
use App\Console\Commands\Concerns\GuardsE2EDatabase;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Deletes a fixture account on the E2E database through the production action.
 *
 * The rejection-persistence scenarios need an identity that has NO account row
 * left while the rejection's permanent block still stands. Deleting the row is
 * state PREPARATION for those scenarios (the verified step is the panel
 * rejection, which is always driven through the browser); it never replaces a
 * step the scenario is meant to prove. The delete therefore runs the real
 * DeleteUser action with `alsoBlock: false`, so it does not create a second
 * block.
 *
 * The acting administrator is required: the action authorises through the
 * UserPolicy, and the default actor is the seeded E2E administrator.
 */
class E2EDeleteUserCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:delete-user
        {email : The fixture email to delete}
        {--actor=admin@homeside.test : The administrator performing the deletion}';

    protected $description = 'Delete an E2E fixture account through the production DeleteUser action';

    /**
     * Execute the command.
     */
    public function handle(DeleteUser $delete): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        $actorEmail = mb_strtolower(trim((string) $this->option('actor')));

        return $this->runGuarded(function () use ($delete, $email, $actorEmail): void {
            $target = User::query()->where('email', $email)->first();
            $actor = User::query()->where('email', $actorEmail)->first();

            if ($actor === null) {
                throw new \RuntimeException(sprintf('No actor account exists for %s.', $actorEmail));
            }

            if ($target === null) {
                $this->line(json_encode(['deleted' => false, 'reason' => 'missing'], JSON_THROW_ON_ERROR));

                return;
            }

            $delete->execute($target, $actor, false);

            $this->line(json_encode(['deleted' => true], JSON_THROW_ON_ERROR));
        });
    }
}
