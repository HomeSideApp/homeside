import os from 'node:os';
import path from 'node:path';

/**
 * The single source of truth for the E2E environment.
 *
 * Both the Playwright config (the isolated application server) and the fixture
 * helpers (every `php artisan e2e:*` call) read this, so the server and the
 * seeding commands can never disagree about which database they are using.
 */

export const E2E_PORT = Number(process.env.E2E_PORT ?? 8010);

export const E2E_BASE_URL = process.env.E2E_BASE_URL ?? `http://localhost:${E2E_PORT}`;

export const E2E_DATABASE = process.env.E2E_DB_DATABASE ?? 'testing_e2e';

export const E2E_MAILPIT_URL = process.env.E2E_MAILPIT_URL ?? 'http://mailpit:8025';

export const E2E_SECRETS_FILE =
    process.env.E2E_SECRETS_FILE ??
    path.join(os.homedir(), '.local', 'share', 'homeside-e2e', 'secrets.json');

/**
 * Environment for the isolated application server and the artisan commands.
 *
 * `APP_ENV=e2e` plus a database name that carries the `_e2e` marker is exactly
 * what the destructive `e2e:*` commands require; anything else and they refuse
 * to run.
 */
export function e2eEnv(): NodeJS.ProcessEnv {
    return {
        ...process.env,
        APP_ENV: 'e2e',
        DB_DATABASE: E2E_DATABASE,
        DB_CONNECTION: process.env.DB_CONNECTION ?? 'mysql',
        MAIL_MAILER: process.env.MAIL_MAILER ?? 'smtp',
        MAIL_HOST: process.env.MAIL_HOST ?? 'mailpit',
        MAIL_PORT: process.env.MAIL_PORT ?? '1025',
        APP_URL: E2E_BASE_URL,
        E2E_SECRETS_FILE,
        E2E_MAILPIT_URL,
    };
}
