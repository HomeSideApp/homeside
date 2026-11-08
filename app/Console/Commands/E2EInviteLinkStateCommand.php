<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use App\Models\HouseholdInviteLink;
use Illuminate\Console\Command;

/**
 * Prints the persisted state of a household invitation link.
 *
 * The rejection-persistence scenarios assert that a blocked identity never
 * consumes a use of a freshly created VALID link. Reading the counter
 * afterwards is an assertion helper: the registration attempt itself is always
 * driven through the browser or a direct request, never here.
 */
class E2EInviteLinkStateCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:invite-link-state {token : The invitation link token}';

    protected $description = 'Print the persisted state of an E2E household invitation link';

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
        }

        $token = (string) $this->argument('token');
        $link = HouseholdInviteLink::query()->where('token', $token)->with('household')->first();

        if ($link === null) {
            $this->line(json_encode(['exists' => false], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->line(json_encode([
            'exists' => true,
            'household' => $link->household?->name,
            'usable' => $link->isUsable(),
            'uses_count' => $link->uses_count,
            'max_uses' => $link->max_uses,
            'revoked' => $link->revoked_at !== null,
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
