# Developer entry points. The E2E suite reuses the stack that is already in
# compose.yaml (MySQL, Redis, Mailpit) and starts its own isolated application
# server against a disposable database, so these targets never touch the
# developer's data.
#
# The `saas` override keeps the path-repository package mounted inside Sail.
#
# The registration suite runs in two profiles (section 13 of the registration
# spec). Both use the same stack; the profile only changes which registration
# defaults and gate the container binds:
#
#   make test-selfhosted  force the Community self-hosted defaults and gate, so
#                         the suite proves the app works with the commercial
#                         package NOT influencing admission.
#   make test-saas        force the commercial defaults and gate, so the suite
#                         proves they take over when the package is installed.
#
# Run both from this repo. The package repo has its own `test-saas` target that
# runs the package's PHPUnit suite; this file's `test-saas` runs THIS app suite
# in the SaaS profile, so the two names coexist and neither invokes the other.
#
# Sail forwards only a limited set of environment variables into the container,
# so the profile is passed explicitly with `docker compose exec -e` (PHPUnit
# then lets it win over the `ambient` default in phpunit.xml).

SAIL_FILES ?= ./compose.yaml:../saas/docker/saas-sail.yml
export SAIL_FILES
SAIL := vendor/bin/sail
COMPOSE := docker compose -f compose.yaml -f ../saas/docker/saas-sail.yml

# The isolated E2E context: a testing environment plus a database whose name
# carries the `_e2e` marker. Both are what the `e2e:*` command guard requires.
E2E_ENV := -e APP_ENV=e2e -e DB_DATABASE=testing_e2e
E2E_ARTIFACTS := tests/e2e/.artifacts

# The profile marker forwarded into the testing container. `docker compose exec
# -e` is what carries HOMESIDE_TEST_PROFILE past Sail's restricted environment.
PROFILE_SELFHOSTED := -e HOMESIDE_TEST_PROFILE=selfhosted
PROFILE_SAAS := -e HOMESIDE_TEST_PROFILE=saas

.PHONY: e2e-up e2e-seed e2e-reset e2e-test e2e-test-chromium e2e-test-firefox e2e-test-all e2e-test-critical e2e-report e2e-down test-selfhosted test-saas

# Bring up the shared stack (MySQL, Redis, Mailpit) and the application.
e2e-up:
	$(SAIL) up -d

# Seed the isolated E2E database (creates it and migrates it on first run).
e2e-seed:
	$(COMPOSE) exec -T -u sail $(E2E_ENV) laravel.test php artisan e2e:seed

# Rebuild the isolated E2E database from scratch.
e2e-reset:
	$(COMPOSE) exec -T -u sail $(E2E_ENV) laravel.test php artisan e2e:reset

# Run the whole browser suite in Chromium.
e2e-test:
	$(SAIL) npx playwright test --project=chromium

# Run the whole browser suite in Chromium (alias of `e2e-test`).
e2e-test-chromium:
	$(SAIL) npx playwright test --project=chromium

# Run the whole browser suite in Firefox. Both browsers are exercised now, not
# just configured: install Firefox once with
# `$(SAIL) npx playwright install firefox`.
e2e-test-firefox:
	$(SAIL) npx playwright test --project=firefox

# Run the whole browser suite on every configured project (Chromium + Firefox).
e2e-test-all:
	$(SAIL) npx playwright test

# Run only the tagged critical scenarios.
e2e-test-critical:
	$(SAIL) npx playwright test --project=chromium --grep @critical

# Open the HTML report produced by the last run (served from the host).
e2e-report:
	npx playwright show-report $(E2E_ARTIFACTS)/html-report

# Stop the shared stack.
e2e-down:
	$(SAIL) down

# Run the app suite with the Community self-hosted registration defaults and
# gate forced (the commercial package must not influence admission).
test-selfhosted:
	$(COMPOSE) exec -T -u sail $(PROFILE_SELFHOSTED) laravel.test php artisan test --compact

# Run the app suite with the commercial registration defaults and gate forced.
# This is the app suite, not the package suite: run `make test-saas` inside
# /home/developer/saas for the package's own PHPUnit run.
test-saas:
	$(COMPOSE) exec -T -u sail $(PROFILE_SAAS) laravel.test php artisan test --compact
