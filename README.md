# HomeSide

Open-source home management platform for families: recipes, products, economy,
households and AI-assisted organisation. Built with Laravel 13, Inertia and Vue.

HomeSide runs fully on its own. There is no required commercial component: the
application depends only on its own contracts and binds self-hosted
implementations of them.

## Getting started

```bash
composer install
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate --seed
```

All PHP, Composer, Artisan and Node commands run through Sail. See
`vendor/bin/sail list` for the available shortcuts.

## The optional commercial module

`homeside/saas` is a **first-party commercial module** (plans, billing accounts,
entitlements, grants and the SaaS registration gate). It is deliberately **not** a
Composer requirement of this repository: a fresh `composer install` succeeds
without it and HomeSide boots with the self-hosted registration and entitlement
bindings. Nothing in `app/`, `routes/` or `config/` references the module — the
app talks to its own ports in `app/Contracts`, which is the only seam.

This repository therefore carries no reference to the module's source: no
Composer repository, no suggestion entry, no sibling mount, no install target.
It stays deployable with no credentials to a private package.

### Install it for local development

The module's own repository owns that. From a checkout of `homeside/saas` beside
this one, run:

```bash
make dev-install     # installs into ../homeside as a copy and publishes its pages
make dev-uninstall   # removes it and restores this repository's manifests
```

`dev-install` injects the `path` repository into this `composer.json`, installs
the package as a **copy** (so no symlink and no Sail override are needed here),
publishes its Vue pages and locale fragments, and rebuilds the bundle. Both
targets leave `composer.json`/`composer.lock` dirty on purpose; `dev-uninstall`
restores them with `git checkout`.

A commercial deployment resolves the module as a real Composer dependency from
its own repository, so nothing about it has to live in this one.

## Testing

```bash
make test-selfhosted   # Community defaults and gate (works without the module)
```

The commercial test profile (`HOMESIDE_TEST_PROFILE=saas`) and the commercial
browser scenarios live with the module: `make test-app-saas` and
`make test-app-e2e` in the package repository run them against this app. The
full PHP suite is `vendor/bin/sail artisan test --compact`.

The browser suite and its isolated E2E stack are documented in
[`tests/e2e/README.md`](tests/e2e/README.md).
