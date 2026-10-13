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
 * The simulated Google provider the E2E stack starts on its own listener.
 *
 * It is a SEPARATE server from the application: the app calls the token and
 * userinfo endpoints server-to-server while handling the callback, so sharing
 * the single-threaded app server would deadlock.
 */
export const E2E_GOOGLE_SIMULATOR_URL =
    process.env.E2E_GOOGLE_SIMULATOR_URL ?? 'http://127.0.0.1:8011';

export const E2E_GOOGLE_SIMULATOR_PORT = Number(
    process.env.E2E_GOOGLE_SIMULATOR_PORT ?? 8011,
);

/**
 * The client coordinates shared by the app and the simulator. They are fake and
 * fixed: the exchange is simulated end to end, so no real Google secret is ever
 * involved. They override any value the shell may carry.
 */
export const E2E_GOOGLE_CLIENT_ID = 'e2e-google-client-id';
export const E2E_GOOGLE_CLIENT_SECRET = 'e2e-google-client-secret';

export const E2E_GOOGLE_REDIRECT_URI = `${E2E_BASE_URL}/auth/google/callback`;

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
        // Google points at the local simulator. `GOOGLE_REDIRECT_URI` must be the
        // loopback callback the browser can actually reach, NOT whatever the
        // developer's `.env` holds. The values are fixed, so a real Google
        // credential can never leak into an E2E exchange.
        GOOGLE_CLIENT_ID: E2E_GOOGLE_CLIENT_ID,
        GOOGLE_CLIENT_SECRET: E2E_GOOGLE_CLIENT_SECRET,
        GOOGLE_REDIRECT_URI: E2E_GOOGLE_REDIRECT_URI,
        E2E_GOOGLE_SIMULATOR_ENABLED: 'true',
        E2E_GOOGLE_SIMULATOR_URL,
        E2E_GOOGLE_SIMULATOR_PORT: String(E2E_GOOGLE_SIMULATOR_PORT),
        // The E2E server must use the FILE session driver. A blocked or
        // suspended user's already-open session has to survive the revocation
        // and lose access at the account-status middleware (403); with the
        // database driver the session row is deleted and the navigation turns
        // into a login redirect, which asserts the wrong thing. Forced, not
        // overridable: the stack's own SESSION_DRIVER is irrelevant here.
        SESSION_DRIVER: 'file',
        E2E_SECRETS_FILE,
        E2E_MAILPIT_URL,
    };
}
