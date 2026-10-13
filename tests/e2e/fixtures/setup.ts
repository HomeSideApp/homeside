import { execFileSync } from 'node:child_process';
import { e2eEnv } from './env';

/**
 * Orchestration for the isolated E2E state.
 *
 * Every call shells out to `php artisan` with the E2E environment (APP_ENV and
 * the marked database), so the application's own guard is what decides whether
 * the state may be touched. Nothing here writes to the database directly.
 */

export function runArtisan(args: string[]): string {
    const php = process.env.E2E_PHP_BIN ?? 'php';

    return execFileSync(php, ['artisan', ...args], {
        cwd: process.cwd(),
        env: e2eEnv(),
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    });
}

export function seedE2EState(): void {
    runArtisan(['e2e:seed']);
}

export function resetE2EState(): void {
    runArtisan(['e2e:reset']);
}

export function setRegistrationPolicy(
    channel: 'web' | 'api' | 'google',
    policy: 'disabled' | 'approval_required' | 'open' | 'existing_only',
): void {
    runArtisan(['e2e:policy', channel, policy]);
}

export interface HouseholdAccessState {
    profile: string | null;
    module_access: Record<string, string>;
}

export interface RegistrationState {
    email: string;
    id?: string;
    exists: boolean;
    name?: string;
    approval_status: string;
    account_status: string;
    email_verified: boolean;
    two_factor_enabled: boolean;
    google_linked: boolean;
    roles: string[];
    active_identity_block: boolean;
    active_identity_block_id: string | null;
    block_released_by: string | null;
    block_released_at: string | null;
    approved_by: string | null;
    approved_at: string | null;
    rejection_released_by: string | null;
    rejection_released_at: string | null;
    active_household: string | null;
    household_access: Record<string, HouseholdAccessState>;
}

/**
 * Backend check for a registration's persisted state.
 *
 * Use it to ASSERT, never to shortcut a step the scenario is meant to
 * exercise through the browser.
 */
export function registrationState(email: string): RegistrationState {
    return JSON.parse(runArtisan(['e2e:user-state', email])) as RegistrationState;
}

export interface BootstrapGrant {
    token: string;
    purpose: string;
    url: string;
    path: string;
    expires_at: string;
}

/**
 * Issue a real single-use authentication bootstrap through the production
 * action and return the plaintext token once. State PREPARATION helper: the
 * scenario still drives the redemption through the browser.
 */
export function issueBootstrap(
    email: string,
    purpose: 'two_factor' | 'account_recovery' = 'two_factor',
    minutes?: number,
): BootstrapGrant {
    const args = ['e2e:bootstrap', 'issue', email, `--purpose=${purpose}`];

    if (minutes !== undefined) {
        args.push(`--minutes=${minutes}`);
    }

    return JSON.parse(runArtisan(args)) as BootstrapGrant;
}

/** Mark every outstanding grant of a purpose as already expired. */
export function expireBootstraps(email: string, purpose: 'two_factor' | 'account_recovery' = 'two_factor'): number {
    const result = JSON.parse(
        runArtisan(['e2e:bootstrap', 'expire', email, `--purpose=${purpose}`]),
    ) as { expired: number };

    return result.expired;
}

/** Mark every outstanding grant of a purpose as already consumed. */
export function consumeBootstraps(email: string, purpose: 'two_factor' | 'account_recovery' = 'two_factor'): number {
    const result = JSON.parse(
        runArtisan(['e2e:bootstrap', 'consume', email, `--purpose=${purpose}`]),
    ) as { consumed: number };

    return result.consumed;
}

/** Delete a fixture account through the production action (state preparation). */
export function deleteUser(email: string): void {
    runArtisan(['e2e:delete-user', email]);
}

export interface InviteLinkState {
    exists: boolean;
    household?: string;
    usable: boolean;
    uses_count: number;
    max_uses: number | null;
    revoked: boolean;
}

/** Read the persisted state of a household invitation link (assertion helper). */
export function inviteLinkState(token: string): InviteLinkState {
    return JSON.parse(runArtisan(['e2e:invite-link-state', token])) as InviteLinkState;
}
