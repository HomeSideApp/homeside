# Developer entry points. The E2E suite reuses the stack that is already in
# compose.yaml (MySQL, Redis, Mailpit) and starts its own isolated application
# server against a disposable database, so these targets never touch the
# developer's data.
#
# The `saas` override keeps the path-repository package mounted inside Sail.

SAIL_FILES ?= ./compose.yaml:../saas/docker/saas-sail.yml
export SAIL_FILES
SAIL := vendor/bin/sail
COMPOSE := docker compose -f compose.yaml -f ../saas/docker/saas-sail.yml

# The isolated E2E context: a testing environment plus a database whose name
# carries the `_e2e` marker. Both are what the `e2e:*` command guard requires.
E2E_ENV := -e APP_ENV=e2e -e DB_DATABASE=testing_e2e
E2E_ARTIFACTS := tests/e2e/.artifacts

.PHONY: e2e-up e2e-seed e2e-reset e2e-test e2e-test-critical e2e-report e2e-down

# Bring up the shared stack (MySQL, Redis, Mailpit) and the application.
e2e-up:
	$(SAIL) up -d

# Seed the isolated E2E database (creates it and migrates it on first run).
e2e-seed:
	$(COMPOSE) exec -T -u sail $(E2E_ENV) laravel.test php artisan e2e:seed

# Rebuild the isolated E2E database from scratch.
e2e-reset:
	$(COMPOSE) exec -T -u sail $(E2E_ENV) laravel.test php artisan e2e:reset

# Run the whole browser suite in Chromium (and Firefox when its browser is
# installed: `$(SAIL) npx playwright install firefox`).
e2e-test:
	$(SAIL) npx playwright test --project=chromium

# Run only the tagged critical scenarios.
e2e-test-critical:
	$(SAIL) npx playwright test --project=chromium --grep @critical

# Open the HTML report produced by the last run (served from the host).
e2e-report:
	npx playwright show-report $(E2E_ARTIFACTS)/html-report

# Stop the shared stack.
e2e-down:
	$(SAIL) down
