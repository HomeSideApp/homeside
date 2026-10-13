import { defineConfig, devices } from '@playwright/test';
import { existsSync } from 'node:fs';
import { E2E_BASE_URL, E2E_PORT, e2eEnv } from './tests/e2e/fixtures/env';

/**
 * Browser suite for the registration, approval and rejection flows.
 *
 * The suite runs INSIDE the Sail container (see `make e2e-test`) and brings up
 * its own isolated `php artisan serve` instance bound to the loopback
 * interface, already pointing at the disposable E2E database. The existing
 * stack (MySQL, Redis and Mailpit) is reused; the developer's own application
 * instance and database are never touched.
 *
 * Artifacts (traces, screenshots, reports, session state) contain live cookies
 * and one-time URLs: they are treated as sensitive and gitignored.
 */

const port = E2E_PORT;

/**
 * The base URL. Override with E2E_BASE_URL; the default points at the isolated
 * server that Playwright starts below.
 */
const baseURL = E2E_BASE_URL;

/**
 * Environment handed to the isolated application server and to every artisan
 * command the specs run. APP_ENV plus the marked database name satisfy the
 * `e2e:*` command guard; Mailpit is the service that is already running.
 */
export const e2eServerEnv = e2eEnv();

/**
 * Resolve the Chromium executable WITHOUT requiring a Playwright download.
 *
 * Order:
 *  1. `E2E_CHROME_PATH` — an explicit executable, for unusual environments;
 *  2. `/usr/bin/google-chrome` — the system Chrome when it is installed;
 *  3. nothing — fall back to Playwright's managed Chromium.
 *
 * CI (which has no system Chrome) installs the managed browser instead, once,
 * with `npx playwright install --with-deps chromium firefox`. The repository's
 * Dockerfile already runs `playwright install-deps`, so inside Sail the managed
 * Chromium only needs to be materialised under `node_modules`.
 */
function chromiumLaunchOptions(): Record<string, unknown> {
    const explicit = process.env.E2E_CHROME_PATH;
    if (explicit && existsSync(explicit)) {
        return { executablePath: explicit };
    }

    if (existsSync('/usr/bin/google-chrome')) {
        return { executablePath: '/usr/bin/google-chrome' };
    }

    return {};
}

export default defineConfig({
    testDir: './tests/e2e',
    testMatch: '**/*.spec.ts',
    outputDir: 'tests/e2e/.artifacts/test-results',
    globalSetup: './tests/e2e/fixtures/global-setup.ts',

    // The critical scenarios mutate shared fixture state (a pending account is
    // approved or rejected), so one worker keeps the run deterministic.
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: 1,
    timeout: 120_000,
    expect: { timeout: 15_000 },

    reporter: [
        ['list'],
        ['html', { outputFolder: 'tests/e2e/.artifacts/html-report', open: 'never' }],
        ['junit', { outputFile: 'tests/e2e/.artifacts/junit.xml' }],
    ],

    use: {
        baseURL,
        screenshot: 'only-on-failure',
        trace: 'on-first-retry',
        video: 'off',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },

    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                launchOptions: chromiumLaunchOptions(),
            },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
    ],

    webServer: {
        command: `php artisan e2e:serve --host=127.0.0.1 --port=${port}`,
        url: `${baseURL}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 180_000,
        env: e2eServerEnv,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
