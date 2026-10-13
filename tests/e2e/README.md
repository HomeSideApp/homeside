# Browser end-to-end suite

Playwright drives the real application for the registration, approval and
rejection flows. It talks to a real database, real Mailpit and a real browser;
nothing is mocked.

## How it runs

The suite runs **inside the Sail container** and reuses the stack that is
already in `compose.yaml` (MySQL, Redis, Mailpit). It starts its **own**
application server, bound to loopback, already pointed at a **disposable**
database:

| Piece | Value | Why |
| --- | --- | --- |
| Application server | `php artisan e2e:serve` on `http://localhost:8010` | Playwright's `webServer`; the developer's own instance on port 80 is never touched. |
| Database | `testing_e2e` | Carries the `_e2e` marker the destructive commands require. |
| Environment | `APP_ENV=e2e` | The second guard condition. |
| Mail | the existing Mailpit (`http://mailpit:8025`) | The stack already provides it. |
| Secrets | `~/.local/share/homeside-e2e/secrets.json` (0600, outside the repo) | Generated passwords and TOTP secrets for the fixtures. |

Bring the stack up once, then run:

```bash
make e2e-up              # shared MySQL / Redis / Mailpit / app
make e2e-test-critical   # the tagged critical scenarios, Chromium
make e2e-test            # the whole suite, Chromium
make e2e-report          # open the HTML report of the last run
make e2e-down            # stop the shared stack
```

Equivalent raw command for the critical run:

```bash
SAIL_FILES=./compose.yaml:../saas/docker/saas-sail.yml \
  vendor/bin/sail npx playwright test --project=chromium --grep @critical
```

The `firefox` project is configured as a secondary target. It needs its browser
once: `vendor/bin/sail npx playwright install firefox`.

On a fresh container (or after `npm ci` wipes `node_modules`) Chromium also has
to be materialised once, unless `/usr/bin/google-chrome` exists:

```bash
vendor/bin/sail npx playwright install chromium
```

### Browser resolution

The config resolves the browser **without requiring a Playwright download**:

1. `E2E_CHROME_PATH`, when set;
2. `/usr/bin/google-chrome`, when it exists;
3. otherwise Playwright's managed Chromium (what runs inside Sail).

CI, which has no system Chrome, installs the managed browsers once instead:

```bash
npx playwright install --with-deps chromium firefox
```

## Isolated state and the guard

`php artisan e2e:seed` and `php artisan e2e:reset` drop and rewrite tables, so
they refuse to run unless **both** conditions hold:

1. `APP_ENV` is `testing` or `e2e`;
2. the resolved database name ends with `_e2e` (configurable via
   `E2E_DATABASE_MARKER`).

The PHPUnit suite runs against the unmarked `testing` database, so it can never
trigger a wipe. The guard is covered by
`tests/Feature/E2E/E2EDatabaseGuardTest.php`.

Fixtures: `admin@homeside.test`, `approver@homeside.test`,
`member@homeside.test`, `pending@homeside.test`, `rejected@homeside.test`,
`invited@homeside.test`, `blocked@homeside.test`, `google.pending@homeside.test`,
`google.linked@homeside.test`, `google.unlinked@homeside.test`, plus the Google,
capacity and identity-block fixtures the scenarios need.
`database/seeders/E2EDatabaseSeeder.php` is the source of truth. It reuses
`RolesAndPermissionsSeeder` and the existing factories.

## Sensitivity

Session state, traces, screenshots and the HTML/JUnit reports are **sensitive**:
they can contain live session cookies, one-time enrolment URLs and account data.
They are written under `tests/e2e/.artifacts/` and gitignored. The generated
passwords and TOTP secrets live in a `0600` file **outside** the repository and
are never printed, committed or embedded in a spec.

## Scenarios

| Id | Status |
| --- | --- |
| E2E-01 self-hosted refuses public registration | green |
| E2E-02 approval lifecycle across two browser contexts | green |
| E2E-03 administrative rejection | green |
| E2E-04 Google approval lifecycle | green |
| E2E-05 Google edge cases | green |
| E2E-06 household invitation | green |
| E2E-07 administrative invitation | green |
| E2E-09 TOTP bootstrap security | green |
| E2E-15/16/18 rejection persistence | green |
| E2E-17 rejected identity tries Google | green |
| E2E-19 release rejection | green |
| E2E-20 delete without block | green |
| E2E-21 delete and block | green |
| E2E-22 release block | green |
| E2E-23 suspend lifecycle | green |
| E2E-24 release permissions | green |
| E2E-25 blocked open session | green |
| E2E-26 bootstrap before rejection | green |
| E2E-28 blocked identity returned by Google | green |
| E2E-29 targeted invitation blocked | green |
| remaining spec scenarios | pending |

## Simulated Google provider

The Google scenarios (E2E-04, 05, 17, 28) must run through the REAL callback
route and the REAL OAuth exchange. Only the three provider endpoints move to a
local simulator; `state` validation, the redirect-URI check, the code exchange
and the identity mapping all stay in Socialite's real code paths.

| Piece | Value | Why |
| --- | --- | --- |
| Driver selection | `E2EGoogleSimulator` + `GoogleDriverRegistrar` | Fail-closed: registered only when `APP_ENV=e2e`, the URL is loopback and the credentials are set. A production install that exports the flag keeps talking to Google. |
| Simulator listener | `php -S 127.0.0.1:8011`, started by `e2e:serve` | It must be a SEPARATE process: the app calls the token/userinfo endpoints server-to-server while handling the callback, and its own `php artisan serve` is single-threaded. |
| Endpoints | `/oauth2/auth`, `/oauth2/consent`, `/oauth2/token`, `/oauth2/userinfo` | A real browser consent plus the real server-to-server exchange. |
| Control | `/__reset`, `/__scenario`, `/__calls`, `/__health` | Test-only: set the identity per run and read the call counters/state. |

The fixture `tests/e2e/fixtures/google.ts` drives the control endpoints. The
scenarios assert `calls.token`/`calls.userinfo`, so a test that bypassed the
exchange would fail.


## Layout

```
tests/e2e/
  fixtures/   env, secrets, mail (Mailpit), totp, setup (artisan orchestration), users, global setup, test fixture
  pages/      Login, Register, SetPassword, OtpSetup, TwoFactor, ApprovalPanel, Dashboard
  specs/      the @critical scenarios
```
