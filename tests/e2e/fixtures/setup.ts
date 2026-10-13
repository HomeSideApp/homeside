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

export interface RegistrationState {
    email: string;
    exists: boolean;
    name?: string;
    approval_status: string;
    account_status: string;
    email_verified: boolean;
    two_factor_enabled: boolean;
    roles: string[];
    active_identity_block: boolean;
    approved_by: string | null;
    approved_at: string | null;
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
