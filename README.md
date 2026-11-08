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
entitlements, grants and the SaaS registration gate). It is declared as a
Composer *suggestion*, not a requirement, so a fresh `composer install` succeeds
without it and HomeSide boots with the self-hosted registration and entitlement
bindings. Nothing in `app/`, `routes/` or `config/` references the module.

`composer.json` keeps a `path` repository pointing at `../saas`. That entry is
what lets a developer or operator install the module from the sibling checkout
inside this workspace; it does **not** install anything on its own. Deleting it
would remove the only way to add the module back.

### Install it (commercial or full-local environment)

Check out the module next to this repository (`../saas`), then run:

```bash
make install-saas
```

The target mounts the sibling checkout inside Sail and runs

```bash
vendor/bin/sail composer require 'homeside/saas:*'
```

which is the exact command. It adds `"homeside/saas": "*"` back to
`composer.json`'s `require` and writes the package entry (with its `../saas`
`dist` path) into `composer.lock`, symlinking `vendor/homeside/saas` at the
checkout. Every other locked package is left untouched. Other machines then
pick the module up with a plain `composer install`.

### Remove it again (the OSS-clean state)

Move the `homeside/saas` entry from `require` back to `suggest` and run:

```bash
vendor/bin/sail composer update homeside/saas --no-install
```

That removes the single lock entry and rewrites the lock's content hash;
`composer install` then drops it from `vendor/`.

## Testing

```bash
make test-selfhosted   # Community defaults and gate (works without the module)
make test-saas         # commercial defaults and gate (requires the module)
```

`make test-saas` fails fast and tells you to run `make install-saas` when the
module is absent, instead of silently running the OSS profile. The full PHP
suite is `vendor/bin/sail artisan test --compact`.

The browser suite and its isolated E2E stack are documented in
[`tests/e2e/README.md`](tests/e2e/README.md).
