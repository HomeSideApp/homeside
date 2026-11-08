import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { e2eEnv } from './env';

/**
 * Orchestration for the isolated E2E state.
 *
 * Every call shells out to `php artisan` with the E2E environment (APP_ENV and
 * the marked database), so the application's own guard is what decides whether
 * the state may be touched. Nothing here writes to the database directly.
 */

/**
 * Whether the optional commercial `homeside/saas` module is installed here.
 *
 * The module is a Composer suggestion (see composer.json): an OSS checkout has
 * no `vendor/homeside/saas` and the container resolves the Community
 * registration gate. The commercial scenarios read this BEFORE they run and
 * skip with a stated reason instead of failing, so the browser suite stays
 * green on a package-less checkout. Bring the module back with `make
 * install-saas`.
 */
export function saasPackageInstalled(): boolean {
    return existsSync(path.join(process.cwd(), 'vendor', 'homeside', 'saas'));
}

/**
 * The skip reason the commercial scenarios share when the module is absent.
 */
export const SAAS_REQUIRED_REASON =
    'The optional homeside/saas module is not installed; run `make install-saas` to run the commercial scenarios.';

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

export interface RegistrationProfile {
    gate: string;
    defaults: string;
    policies: { web: string; api: string; google: string };
}

/**
 * Read the registration gate, defaults and effective policies the running E2E
 * application server resolves.
 *
 * The SaaS scenarios assert this reports the package's gate/defaults so the
 * suite proves it is exercising the commercial admission path.
 */
export function registrationProfile(): RegistrationProfile {
    return JSON.parse(runArtisan(['e2e:registration-profile'])) as RegistrationProfile;
}

/**
 * Create or refresh the CONTROLLED entitlement plans (caps 1, 2, 3).
 *
 * The controlled limits live in the E2E fixture, never in the application; the
 * capacity scenarios assert against these values and nothing else.
 */
export function seedControlledPlans(): void {
    runArtisan(['e2e:plan', 'seed']);
}

export interface GrantControlledPlanOptions {
    own?: number;
    reset?: boolean;
}

/**
 * Assign a controlled plan to a fixture account and seed how many households it
 * already owns. `reset` clears its previous grants and owned households first,
 * keeping each scenario's baseline deterministic.
 */
export function grantControlledPlan(
    email: string,
    plan: 'e2e-small' | 'e2e-medium' | 'e2e-large',
    options: GrantControlledPlanOptions = {},
): void {
    const args = ['e2e:plan', 'grant', email, plan, `--own=${options.own ?? 0}`];

    if (options.reset === true) {
        args.push('--reset');
    }

    runArtisan(args);
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
    owned_households: string[];
    plan_key: string | null;
    households_own_cap: number | null;
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
