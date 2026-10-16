# Developer entry points. The E2E suite reuses the stack that is already in
# compose.yaml (MySQL, Redis, Mailpit) and starts its own isolated application
# server against a disposable database, so these targets never touch the
# developer's data.
#
# HomeSide runs WITHOUT the commercial `homeside/saas` module. It is declared as
# a Composer suggestion, not a requirement (see composer.json), and the app
# binds its own self-hosted registration and entitlement implementations. The
# `path` Composer repository at ../saas (composer.json repositories) is kept so
# the module can still be installed deliberately from the sibling checkout:
#
#   make install-saas     add homeside/saas back from ../saas; it updates
#                         composer.json's `require` and composer.lock
#
# The `saas` Sail override (../saas/docker/saas-sail.yml) bind-mounts the
# sibling checkout at the path the `../saas` Composer path repository expects
# INSIDE the container. It is layered automatically whenever the module is
# installed, so the OSS targets need only the app's own compose.yaml.
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
#                         It requires the module and fails fast without it.
#
# Run both from this repo. The package repo has its own `test-saas` target that
# runs the package's PHPUnit suite; this file's `test-saas` runs THIS app suite
# in the SaaS profile, so the two names coexist and neither invokes the other.
#
# Sail forwards only a limited set of environment variables into the container,
# so the profile is passed explicitly with `docker compose exec -e` (PHPUnit
# then lets it win over the `ambient` default in phpunit.xml).

# The OSS install needs only the app's own compose file, so a fresh clone with
# no sibling ../saas works. The commercial module's override is layered on
# demand (install-saas / test-saas) and is what mounts the ../saas sibling at
# /var/www/saas inside the container.
COMPOSE_OSS := docker compose -f compose.yaml
COMPOSE_SAAS := docker compose -f compose.yaml -f ../saas/docker/saas-sail.yml
SAIL_FILES_OSS := ./compose.yaml
SAIL_FILES_SAAS := ./compose.yaml:../saas/docker/saas-sail.yml

# Layer the commercial override automatically when the module is installed, so
# a full-local/commercial checkout keeps working unchanged while an OSS
# checkout never references a sibling that may not exist.
ifneq ($(wildcard vendor/homeside/saas),)
DEFAULT_COMPOSE := $(COMPOSE_SAAS)
DEFAULT_SAIL_FILES := $(SAIL_FILES_SAAS)
else
DEFAULT_COMPOSE := $(COMPOSE_OSS)
DEFAULT_SAIL_FILES := $(SAIL_FILES_OSS)
endif

SAIL_FILES ?= $(DEFAULT_SAIL_FILES)
export SAIL_FILES
SAIL := vendor/bin/sail
COMPOSE := $(DEFAULT_COMPOSE)
# Force the commercial override regardless of the auto-detection above; used by
# install-saas so the mount exists even before the module is installed.
SAAS_SAIL := SAIL_FILES=$(SAIL_FILES_SAAS) $(SAIL)

# The isolated E2E context: a testing environment plus a database whose name
# carries the `_e2e` marker. Both are what the `e2e:*` command guard requires.
E2E_ENV := -e APP_ENV=e2e -e DB_DATABASE=testing_e2e
E2E_ARTIFACTS := tests/e2e/.artifacts

# The profile marker forwarded into the testing container. `docker compose exec
# -e` is what carries HOMESIDE_TEST_PROFILE past Sail's restricted environment.
PROFILE_SELFHOSTED := -e HOMESIDE_TEST_PROFILE=selfhosted
PROFILE_SAAS := -e HOMESIDE_TEST_PROFILE=saas

.PHONY: e2e-up e2e-seed e2e-reset e2e-test e2e-test-chromium e2e-test-firefox e2e-test-all e2e-test-critical e2e-report e2e-down test-selfhosted test-saas install-saas

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

# Run only the tagged critical scenarios. The commercial scenarios that need
# the optional homeside/saas module skip themselves with their stated reason.
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
#
# The module is an optional Composer suggestion now, so a package-less checkout
# would otherwise run the OSS profile and skip the commercial coverage. Refuse
# instead, with the exact command that fixes it.
test-saas:
	@test -d vendor/homeside/saas || { \
		echo "test-saas requires the optional homeside/saas module, which is not installed."; \
		echo "Run 'make install-saas' first (it needs the sibling ../saas checkout)."; \
		exit 1; \
	}
	$(COMPOSE_SAAS) exec -T -u sail $(PROFILE_SAAS) laravel.test php artisan test --compact

# Install the optional commercial module from the sibling ../saas checkout.
#
# `homeside/saas` is a Composer suggestion in composer.json, not a requirement,
# so nothing pulls it in on its own. This target brings the stack up with the
# override that mounts ../saas, then runs `composer require`, which:
#   * adds `"homeside/saas": "^0.1"` back to composer.json's `require` (a bounded
#     constraint, because an unbound `*` makes `composer validate` complain);
#   * writes the package entry (with its ../saas `dist` path) into
#     composer.lock and symlinks vendor/homeside/saas at the sibling;
#   * leaves every other locked package untouched.
#
# The published Vue pages are NOT committed: they are generated by
# `saas:install --pages` and they import Wayfinder routes that only exist while
# the module is installed, so they are gitignored and must be republished here
# or `npm run build` cannot resolve them.
#
# Run `composer install` afterwards to keep vendor/ in lock step on any other
# machine. To reverse it, move the entry back to `suggest`, delete it from
# composer.lock with `composer update homeside/saas --no-install`, and remove
# resources/js/pages/saas.
install-saas:
	@test -d ../saas || { \
		echo "homeside/saas is not available: expected the sibling checkout at ../saas."; \
		echo "The module has no Composer remote, so this is the only source."; \
		exit 1; \
	}
	$(SAAS_SAIL) up -d
	# Composer must run as root here: vendor/homeside/ is created by container
	# operations owned by root, so a symlink attempt as the sail user fails with
	# "Symlink from /var/www/saas ... failed". The package's own Makefile installs
	# the same way.
	$(SAAS_SAIL) exec -u root laravel.test composer require 'homeside/saas:^0.1' --no-interaction
	$(SAAS_SAIL) exec -u root laravel.test php artisan saas:install --pages
	@echo "Installed homeside/saas and published its pages. Run 'npm run build' next."
