# Conventions

Patterns introduced as the codebase grows, in the order they showed up. `CLAUDE.md` is the source of truth for *what* to build; this file records *how* we've been building it, so a new pattern doesn't get reinvented differently next time.

## Enums, not magic strings

PHP backed enums live in `app/Enums`. `App\Enums\UserRole` (`admin`/`buyer`/`vendor`) backs the `users.role` column. Status/rank/method enums for `part_requests`, `vendor_responses`, etc. should follow the same shape as they're introduced.

## Model casts: use the `$casts` property, not the `casts()` method

Laravel 11+ supports both `protected $casts = [...]` and `protected function casts(): array`. **Use the property form.** Larastan 3.10 doesn't resolve enum/type casts declared via the `casts()` method — it falls back to inferring the attribute's type straight from the DB column (e.g. an `enum` column becomes a string-literal union), which produces false-positive "always false" errors on strict enum comparisons (`$user->role === UserRole::Admin`). The property form is equally idiomatic and Larastan resolves it correctly.

## Settings

`App\Models\Setting` is a key/value/type store (`settings` table). Read with `Setting::get(string $key, mixed $default = null)` — casts the stored value based on its `type` column (`integer`, `boolean`, else raw string). Write with `Setting::set(string $key, mixed $value, string $type = 'string')`.

## PricingService

`App\Services\PricingService::calculate(int $costPrice): array` is the single source of truth for margin pricing (§6.1 of `CLAUDE.md`). Returns `cost_price`, `applied_rate`, `applied_min_fee`, `margin`, `buyer_price` — the four snapshot fields plus the input, ready to persist onto a `part_request` at quote-presentation time (§6.2: never recompute a historical order from live settings). Every caller that needs a price goes through this service — do not reimplement the `max(percentage, floor)` rule elsewhere.

## Livewire's bundled Alpine — don't add a separate `alpinejs` package

Livewire 3 ships its own internal build of Alpine.js and boots it automatically via `@livewireScripts`. Installing `alpinejs` as a separate npm dependency causes a double-initialization conflict. Alpine is available on every page that includes `@livewireScripts` — no extra import needed.

## spatie/laravel-activitylog: attribute changes live in `attribute_changes`

On the installed 5.x line, a logged model's attribute diff is on the `attribute_changes` column (`['attributes' => [...], 'old' => [...]]`), not on `properties` (which comes back empty for a plain `LogsActivity` model) and not via a `changes()` method — both exist in older docs/versions but not this one. See `VendorProfile`/`VendorProfileTest` for the working pattern.

## Vendor status is a flag only — not enforced anywhere yet

`vendor_profiles.status` (`active`/`suspended`) is pure data right now. Nothing reads it. Two places will need to start respecting it and don't yet:
- **Phase 2 broadcast logic**: selecting which vendors a request goes out to must exclude suspended vendors.
- **The `act`/login gates** (`app/Providers/AppServiceProvider.php`'s `act` gate, and login itself): currently only check email verification, not vendor status. Whether a suspended vendor should be blocked from logging in at all, or only from acting, is an open decision for whoever builds that enforcement — don't assume either way without deciding it explicitly first.

## Theme: light only, no dark mode

The design tokens in `resources/css/app.css` (`@theme` block: `--color-brand-*`, `--color-surface*`, `--color-ink*`, `--color-line`) define a locked light, professional palette. Never add `dark:` variants — this app does not support a dark theme.

## i18n: no hardcoded UI strings

All UI-facing text goes through `__('key')` against `lang/en/*.php`. `lang/en/app.php` holds app-wide strings; add a new file per domain area as portals are built (e.g. `lang/en/buyer.php`) rather than piling everything into one file. This keeps a future Japanese translation a matter of adding `lang/ja/*.php`, not a rewrite.

## Testing

- Unit tests for pure business logic live under `tests/Unit`, grouped by the class under test (e.g. `tests/Unit/Services/PricingServiceTest.php`).
- Use `uses(RefreshDatabase::class)` per-file (see `tests/Pest.php` for the base `TestCase` binding) whenever a test touches the DB, even in `tests/Unit`.
- Prefer a Pest `->with([...])` dataset over duplicate `it(...)` blocks when the same assertion needs to hold across many inputs (see `PricingServiceTest`'s "holds for any configured rate/minFee" case).
