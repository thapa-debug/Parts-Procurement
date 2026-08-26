# Parts Procurement

A brokerage platform connecting overseas auto-parts buyers with Japanese dismantler vendors, brokered by an admin. See [`CLAUDE.md`](./CLAUDE.md) for the full domain spec, business rules, and build order — read it before making changes.

## Stack

Laravel 13 · PHP 8.4 · MySQL 8.4 · Livewire 3 · Tailwind CSS 4 · Pest 5 · Redis (Horizon queues) · Laravel Reverb · Stripe (raw PaymentIntents SDK) · AWS S3/SES · spatie/laravel-permission + spatie/laravel-activitylog

## Local setup

Prerequisites: PHP 8.4, Composer, Node 20+, a running MySQL 8.4 server, a running Redis server.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build   # or `npm run dev` while working on front-end
php artisan serve
```

On Windows, Horizon requires `ext-pcntl`/`ext-posix`, which don't exist on Windows PHP builds. Composer install still works (a local, non-committed platform override is configured for this machine), but you cannot run the `horizon` supervisor itself locally — use `php artisan queue:work` for local queue processing, and reserve Horizon for WSL2/Docker/CI/production.

## Quality gates

Run all three before considering any task done (also enforced in CI):

```bash
vendor/bin/pint            # fix formatting (or --test to check only)
vendor/bin/phpstan analyse # static analysis (Larastan, level 5)
php artisan test           # Pest suite
```

> Note: on this Windows/Laravel Herd setup, invoke `vendor/bin/pest` indirectly via `php artisan test` (or `composer test`) — calling the `pest` binary directly fails due to a Herd PHP-shim path-resolution quirk. Not an issue in CI or on Linux.

## CI

GitHub Actions (`.github/workflows/ci.yml`) runs on every push/PR to `main`: Pint → Larastan → Pest, the last stage against real MySQL 8.4 and Redis 7 service containers.

## Conventions

See [`CONVENTIONS.md`](./CONVENTIONS.md) for patterns introduced as the codebase grows.
