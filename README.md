# Parts Procurement

A brokerage platform connecting overseas auto-parts buyers with Japanese dismantler vendors, brokered by an admin. See [`CLAUDE.md`](./CLAUDE.md) for the full domain spec, business rules, and build order — read it before making changes.

## Stack

Laravel 13 · PHP 8.4 · MySQL 8.4 · Livewire 3 · Tailwind CSS 4 · Pest 5 · Redis (Horizon queues) · Laravel Reverb · Stripe (raw PaymentIntents SDK) · AWS S3/SES · spatie/laravel-permission + spatie/laravel-activitylog

## Local setup

Prerequisites: PHP 8.4, Composer, Node 20+, a running **MySQL 8.4** server (not SQLite — a Phase 4 migration widens/narrows a native MySQL `ENUM` column and silently no-ops on any other driver, so the schema only ends up correct on real MySQL). A running Redis server is convenient but not required — see the overrides below.

Fresh-machine checklist, in order:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Then edit `.env`:

- **`DB_*`** — point at your real MySQL server and a database you've created (`.env.example` assumes `parts_procurement` on `127.0.0.1:3306`).
- **`APP_URL`** — match whatever port you'll actually run `php artisan serve` on (default `http://localhost:8000`, or pass `--port=` below). Email-verification links are signed against this value, so a mismatch breaks them.
- **`PAYMENT_GATEWAY`** — not present in `.env.example` and does not need to be set locally. `PaymentServiceProvider` automatically falls back to the dev-only `stub` gateway (always succeeds, never calls a real processor) whenever this is unset outside production. Set `PAYMENT_GATEWAY=stub` explicitly only if you want it visible in your own `.env`; it must never be `stub` in production — the app refuses to boot that way.
- **No Redis installed?** Override `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, `BROADCAST_CONNECTION=log`. This matters before you even open a browser: `migrate:fresh --seed` itself touches the cache (spatie/laravel-permission clears it), so it fails against a Redis default with no server running. See `CONVENTIONS.md` for the full rationale.
- **Want vendor-response photo uploads viewable without real AWS credentials?** Override `FILESYSTEM_DISK=public` and run `php artisan storage:link` once. Otherwise leave the `.env.example` default (`local`) — uploads still work, they're just not browser-viewable. See `CONVENTIONS.md`.
- **`SESSION_DRIVER=database`** and **`MAIL_MAILER=log`** already ship in `.env.example` and need no setup: the `sessions` table migrates with everything else below, and outgoing mail (verification links, quote/order notifications) lands in `storage/logs/laravel.log` instead of actually sending.

Then:

```bash
php artisan migrate              # every Phase 0-4 migration: payments, buyer_addresses,
                                  # shipping_weight_brackets, the is_free columns, and the
                                  # shipping_method enum widen/narrow (MySQL-only, see above)
# -- or, for a seeded demo cast (admin/vendors/buyers) instead of an empty database:
php artisan migrate:fresh --seed # see DEMO.md for login credentials and a walkthrough

npm run build                    # or `npm run dev` while working on front-end
php artisan serve                # or --port=XXXX, matching APP_URL above
```

If you kept the default `QUEUE_CONNECTION=redis` (or set it to `database`), also run `php artisan queue:work` alongside `serve` — otherwise queued notification emails never actually send (they still queue, just never process).

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

## Demo data

`php artisan migrate:fresh --seed` seeds a realistic demo cast (admin,
vendors, buyers). See [`DEMO.md`](./DEMO.md) for login credentials and a
suggested click-path walkthrough.
