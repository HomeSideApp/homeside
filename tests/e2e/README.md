# Browser end-to-end suite

Playwright drives the real application for the registration, approval and
rejection flows. It talks to a real database, real Mailpit and a real browser;
nothing is mocked.

## Coverage

The spec's scenarios are `E2E-01`..`E2E-29`. Five of them are commercial and
live in the `homeside/saas` package repository (see below); the rest are covered
here:

- **22 scenario IDs live in `tests/e2e/specs/`** across 20 spec files, as
  **44 test cases** at runtime (`vendor/bin/sail npx playwright test --list`
  prints the exact list; loops over access profiles expand some cases).
- **E2E-27** is a PHP feature test with two real processes
  (`tests/Feature/E2E/RegistrationDecisionConcurrencyTest.php`). A browser
  cannot prove a database race, so it runs in the PHP suite, not in Playwright.
- **E2E-14** has no standalone spec: `E2E-03` already exercises the scenario as
  part of its rejection flow, so no duplicate file was added.
- **21 cases** are tagged `@critical` and run on every pull request. The rest
  run in the full suite (both browsers).

| Id | Spec / location | Cases | `@critical` |
| --- | --- | --- | --- |
| E2E-01 self-hosted refuses public registration | `specs/e2e-01-self-hosted-registration.spec.ts` | 1 | yes |
| E2E-02 approval lifecycle across two browser contexts | `specs/e2e-02-approval-lifecycle.spec.ts` | 1 | yes |
| E2E-03 administrative rejection | `specs/e2e-03-administrative-rejection.spec.ts` | 1 | yes |
| E2E-04 Google approval lifecycle | `specs/e2e-04-google-approval.spec.ts` | 1 | yes |
| E2E-05 Google edge cases | `specs/e2e-05-google-edge-cases.spec.ts` | 8 | no |
| E2E-06 household invitation across all four access profiles | `specs/e2e-06-household-invitation.spec.ts` | 4 | no |
| E2E-07 administrative invitation | `specs/e2e-07-administrative-invitation.spec.ts` | 1 | no |
| E2E-09 TOTP bootstrap security | `specs/e2e-09-totp-bootstrap-security.spec.ts` | 13 | yes |
| E2E-14 rejection scenario covered by E2E-03 | `specs/e2e-03-administrative-rejection.spec.ts` (no standalone spec) | 0 | — |
| E2E-15 rejection refused by the web form | `specs/e2e-15-16-18-rejection-persistence.spec.ts` | 1 | yes |
| E2E-16 rejection refused by the public API | `specs/e2e-15-16-18-rejection-persistence.spec.ts` | 1 | yes |
| E2E-17 rejected identity tries Google | `specs/e2e-17-rejected-identity-google.spec.ts` | 1 | no |
| E2E-18 rejection refused by a fresh invitation | `specs/e2e-15-16-18-rejection-persistence.spec.ts` | 1 | yes |
| E2E-19 release rejection from the panel | `specs/e2e-19-release-rejection.spec.ts` | 1 | no |
| E2E-20 delete without block frees the identity | `specs/e2e-20-delete-without-block.spec.ts` | 1 | no |
| E2E-21 delete-and-block reserves the identity | `specs/e2e-21-delete-and-block.spec.ts` | 1 | no |
| E2E-22 releasing a block records its actor | `specs/e2e-22-release-block.spec.ts` | 1 | no |
| E2E-23 suspension refuses login and reserves the identity | `specs/e2e-23-suspend-lifecycle.spec.ts` | 1 | no |
| E2E-24 release affordances are gated and refused server-side | `specs/e2e-24-release-permissions.spec.ts` | 1 | no |
| E2E-25 a blocked open session loses access | `specs/e2e-25-blocked-open-session.spec.ts` | 1 | no |
| E2E-26 a bootstrap issued before a rejection | `specs/e2e-26-bootstrap-before-rejection.spec.ts` | 1 | yes |
| E2E-27 two simultaneous decisions collapse into one | `tests/Feature/E2E/RegistrationDecisionConcurrencyTest.php` (PHP, `pcntl_fork`) | 1 (PHP) | no |
| E2E-28 blocked identity returned by Google | `specs/e2e-28-blocked-identity-google.spec.ts` | 1 | no |
| E2E-29 targeted invitation cannot admit a blocked identity | `specs/e2e-29-targeted-invitation-blocked.spec.ts` | 1 | no |

### Commercial scenarios (E2E-08, 10, 11, 12, 13)

Household entitlement capacity and the commercial registration profiles drive
screens that only exist while the optional `homeside/saas` module is installed,
so their specs and fixtures ship with that package, not with this repository.
They arrive here for one run and are removed afterwards:

```bash
make test-app-e2e    # inside the package repository, with the module installed
```

The target copies the five specs into `tests/e2e/specs/` as
`zz-commercial-*.spec.ts`, plus `fixtures/zz-commercial-saas.ts`, runs them, and
deletes them on the way out. Nothing under `zz-commercial-` is part of this
repository.

## How the suite runs

The suite runs **inside the Sail container** and reuses the stack that is
already in `compose.yaml` (MySQL, Redis, Mailpit). It starts its **own**
application server, bound to loopback, already pointed at a **disposable**
database:

| Piece | Value | Why |
| --- | --- | --- |
| Application server | `php artisan e2e:serve` on `http://localhost:8010` | Playwright's `webServer`; the developer's own instance on port 80 is never touched. |
| Database | `testing_e2e` | Carries the `_e2e` marker the destructive commands require. |
| Environment | `APP_ENV=e2e` | The second guard condition. |
| Sessions | `SESSION_DRIVER=file` | Forced by the harness (see below). |
| Mail | the existing Mailpit (`http://mailpit:8025`) | The stack already provides it. |
| Secrets | `~/.local/share/homeside-e2e/secrets.json` (0600, outside the repo) | Generated passwords and TOTP secrets for the fixtures. |

Bring the stack up once, then run:

```bash
make e2e-up              # shared MySQL / Redis / Mailpit / app
make e2e-test-critical   # the tagged critical scenarios, Chromium
make e2e-test            # the whole suite, Chromium
make e2e-test-firefox    # the whole suite, Firefox
make e2e-test-all        # the whole suite, both configured browsers
make e2e-report          # open the HTML report of the last run
make e2e-down            # stop the shared stack
```

The equivalent direct Playwright commands (inside Sail) are:

```bash
vendor/bin/sail npx playwright test --project=chromium            # Chromium
vendor/bin/sail npx playwright test --project=firefox             # Firefox
vendor/bin/sail npx playwright test                               # both projects
vendor/bin/sail npx playwright test --project=chromium --grep @critical
```

Both browser projects are exercised. Chromium and Firefox each run the full
suite (44 cases); the suite is not Chromium-only.

## Fresh clone: install both browsers once

Playwright's browsers are not committed and `npm ci` wipes `node_modules`, so a
fresh clone must materialise them once:

```bash
vendor/bin/sail npx playwright install chromium firefox
```

The Firefox run needs its own browser download; it is no longer a
configure-only target.

### Browser resolution

The config resolves Chromium **without requiring a Playwright download**:

1. `E2E_CHROME_PATH`, when set;
2. `/usr/bin/google-chrome`, when it exists;
3. otherwise Playwright's managed Chromium (what runs inside Sail).

CI, which has no system Chrome, installs the managed browsers once instead:

```bash
npx playwright install --with-deps chromium firefox
```

## PHP deployment profiles

The app suite runs with the Community self-hosted registration defaults and gate
forced (section 13 of the registration spec):

```bash
make test-selfhosted   # Community default profile: 1197 passed + 2 skipped
```

- **`make test-selfhosted`** forces the Community self-hosted registration
  defaults and gate. It proves the app works with the commercial package **not
  influencing admission**: the package may be installed, but the self-hosted
  bindings are the ones under test.

The commercial registration profile is not a target of this repository; it runs
from the package repository as `make test-app-saas` there, against this app's
suite.

The profile is forwarded into the container with `docker compose exec -e`
(`HOMESIDE_TEST_PROFILE`), because Sail forwards only a limited set of
environment variables. PHPUnit then lets an externally supplied value win over
the `ambient` default in `phpunit.xml`.

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

`e2e:serve` starts the simulator when the harness enables it; the harness sets
`E2E_GOOGLE_SIMULATOR_ENABLED=true` and the fixed fake credentials, so a real
Google credential can never leak into an E2E exchange. Outside the `e2e`
environment nothing starts and the application keeps using real Google: the
selection is fail-closed, and a stray variable on a real install cannot redirect
it to the simulator.

The fixture `tests/e2e/fixtures/google.ts` drives the control endpoints. The
scenarios assert `calls.token`/`calls.userinfo`, so a test that bypassed the
exchange would fail.

## Isolated state, the guard and the session driver

`php artisan e2e:seed` and `php artisan e2e:reset` drop and rewrite tables, so
they refuse to run unless **both** conditions hold:

1. `APP_ENV` is `testing` or `e2e`;
2. the resolved database name ends with `_e2e` (configurable via
   `E2E_DATABASE_MARKER`).

The E2E stack points at the dedicated `testing_e2e` database, which carries the
marker. The PHPUnit suite runs against the unmarked `testing` database, so it can
never trigger a wipe. The guard is covered by
`tests/Feature/E2E/E2EDatabaseGuardTest.php`.

The isolated application server **forces `SESSION_DRIVER=file`**, overriding the
stack's own driver. A blocked or suspended user's already-open session must
survive the revocation and lose access at the account-status middleware (403);
with the database driver the session row is deleted and the navigation turns
into a login redirect, which would assert the wrong thing.

Fixtures: `admin@homeside.test`, `approver@homeside.test`,
`member@homeside.test`, `pending@homeside.test`, `rejected@homeside.test`,
`invited@homeside.test`, `blocked@homeside.test`, `google.pending@homeside.test`,
`google.linked@homeside.test`, `google.unlinked@homeside.test`, plus the Google
and identity-block fixtures the scenarios need.
`database/seeders/E2EDatabaseSeeder.php` is the source of truth. It reuses
`RolesAndPermissionsSeeder` and the existing factories.

## Sensitivity — do not publish artifacts

Session state, traces, screenshots and the HTML/JUnit reports are **sensitive**:
they can contain live session cookies, one-time enrolment URLs and account data.
They are written under `tests/e2e/.artifacts/` and gitignored, and the generated
`secrets.json` (passwords and TOTP secrets) lives in a `0600` file **outside**
the repository. None of it is ever printed, committed or embedded in a spec.

**Never attach a trace, screenshot, session file, report or the generated
secrets file to a public issue or a pull request comment**, and never paste
their contents into one. A one-time enrolment URL in a trace is a working
credential until it is consumed.

## Layout

```
tests/e2e/
  fixtures/   env, secrets, mail (Mailpit), totp, setup (artisan orchestration), users, global setup, test fixture
  pages/      Login, Register, SetPassword, OtpSetup, TwoFactor, ApprovalPanel, Dashboard, ...
  specs/      every OSS browser scenario (E2E-14 is folded into E2E-03; E2E-27 is a PHP test; the
              commercial E2E-08/10/11/12/13 arrive from the package as zz-commercial-*.spec.ts)
```
