# CLAUDE.md — Auto-Parts Procurement Brokerage

This file is the source of truth for how this project is built. Read it fully before writing any code, and follow it in every session. If anything you're about to do conflicts with this file, stop and ask.

---

# Security Rules

- Never read, print, expose, or disclose secret values from `.env`.
- Never include credentials, API keys, passwords, tokens, or private keys in source code.
- Never add real secrets to `.env.example`, documentation, tests, logs, or Git.
- Environment variable names may be referenced and configured, but secret values must not be displayed.
- Do not run commands that dump `.env`, environment variables, credentials, SSH keys, or other secrets.
- When configuring services such as AWS S3, use the environment variable names without accessing their values.


## 1. Mission & quality bar

We are building a production web system for a real client's daily business — a brokerage that connects overseas parts **buyers** with Japanese dismantler **vendors**, with an **admin** brokering every interaction.

This is not a prototype. The bar is:
- Simple, readable, maintainable code a future developer can pick up without a walkthrough.
- Proper automated testing, proper error handling, proper logging.
- Idiomatic Laravel — no clever abstractions, no framework-inside-a-framework.

Favour boring, obvious solutions over clever ones. Readability wins over brevity.

---

## 2. Tech stack (locked — do not substitute)

- Laravel (latest stable) + PHP 8.2+
- MySQL
- Livewire 3 + Blade + Tailwind + Alpine.js
- Pest for tests; Laravel Pint (PSR-12) + Larastan/PHPStan for quality
- Redis — queues (Horizon), cache, broadcast backend
- Laravel Reverb — real-time
- Stripe via the **raw Stripe PHP SDK (PaymentIntents)** — NOT Cashier (no subscriptions here)
- AWS S3 (private bucket + signed URLs) for photos; AWS SES for email
- spatie/laravel-permission (roles) + spatie/laravel-activitylog (audit)
- Telescope + Horizon in local/dev only
- GitHub Actions (`.github/workflows/ci.yml`): Pint → Larastan → Pest, with MySQL + Redis service containers
- UI: light professional theme, English-language UI, i18n-ready (lang files, no hardcoded strings)

**Verify the current stable version of Laravel and every package at install time — do not trust hardcoded version numbers. Ask before adding any package not listed above.**

---

## 3. The domain in brief

A **buyer** requests a car part. The **admin** broadcasts that request to selected **vendors**. Vendors quote a wholesale cost (with photo, quality rank, lead time). The admin applies a margin and presents a marked-up price to the buyer. The buyer pays by card. **Only after payment is confirmed** does the admin purchase from the chosen vendor and ship by the buyer's chosen method.

Buyers and vendors never see or contact each other. The admin is the only bridge.

The uploaded prototype HTML files (`buyer_portal.html`, `vendor_portal.html`, `admin_portal.html`) are the **UI/UX and field reference only** — reuse their Tailwind look and form fields, but rebuild all logic properly. Do NOT port their localStorage JavaScript.

---

## 4. Roles & the isolation rule (security-critical)

Single `users` table + `role` enum (`admin` | `buyer` | `vendor`), guarded by Policies and route middleware. Three portals: `/admin`, `/buyer`, `/vendor`.

**Isolation is a hard requirement, enforced by Policies and covered by explicit tests:**
- A buyer must never see a vendor's identity, a vendor's cost price, or any other buyer's data.
- A vendor must never see the buyer's identity, or any other vendor's response.
- Only the admin sees both sides and the cost-vs-margin figures.

A leak here exposes the client's margin and supplier relationships. Treat authorization as a first-class feature, not middleware you add later.

**Admin-role granularity — confirmed upcoming requirement:** the client's company has two admin tiers, not one undifferentiated `admin` role:
- **Owner/manager**: full access — margin, settings, and creating/managing staff accounts + permissions.
- **Staff**: operational work only — requests, quotes, messaging — but **not** margin, settings, account editing, or creating staff.

Unlimited staff accounts; only owner/managers create or manage staff. To be built in **Phase 2**, using spatie/laravel-permission's fine-grained permissions (the package is already installed — §2 — this is what it's for; not yet wired to any real roles/permissions as of Phase 1). Whether finer-than-two-tier control is needed is still open, pending client confirmation — don't build past two tiers until that's settled.

This lands on top of the Policy-based defense-in-depth discipline already established for Phase 1's admin screens (route middleware + component-level Policy check on every action, e.g. `VendorMasterTest`'s buyer/vendor-forbidden cases): **design every Phase 2 admin screen so an owner-vs-staff permission check slots into that same per-action Policy shape without rework** — the check belongs inside each Policy method (`create`, `update`, ...) alongside the existing `isAdmin()` check, not bolted on separately. This doesn't require touching Phase 1's already-shipped policies (`VendorProfilePolicy`, `BuyerProfilePolicy`, `SettingPolicy`) now — whether those need an owner-only permission added later (e.g. `SettingPolicy::update` restricted to owner/manager, matching "not... settings" above) is itself a Phase 2 decision, once permissions actually exist to check against.

---

## 5. Request lifecycle (state machine)

One `part_request` moves through these statuses. Transitions are guarded — never set status by hand where a guarded action should own the transition.

| Status (code) | JP label | Meaning |
|---|---|---|
| `new` | 新規依頼 | Buyer submitted |
| `vendor_inquiry` | 業者照会中 | Admin broadcast to vendors |
| `quoted` | 見積もり回答済み | Admin presented a priced quote to buyer |
| `paid` | 発注・検品中 | Buyer paid, payment confirmed |
| `ordered_to_vendor` | 発注確定 | Admin purchased from chosen vendor |
| `procurement_failed` | — | Paid, but vendor can't supply → route back to re-quote |
| `shipped` | 発送完了 | Admin shipped |
| `received` | 受取完了 | Buyer confirmed receipt |

---

## 6. Core business rules (CRITICAL — get these exactly right)

### 6.1 Pricing
```
rate    = settings.margin_rate      // admin-editable, default 20
minFee  = settings.margin_min_fee   // admin-editable, default 2000
margin  = max(round(cost_price * rate / 100), minFee)
buyer_price = cost_price + margin
```
The `max()` is the whole rule: whichever is larger, the percentage or the ¥2,000 floor. The floor is a guaranteed minimum profit per deal; it only triggers on low-cost parts. All of this lives in one `PricingService` used by every portal. This is the first thing we build, test-first, as the reference implementation.

### 6.2 Snapshot pricing per request (financial history must never drift)
When a quote is presented to the buyer, **snapshot** `cost_price`, `applied_rate`, `applied_min_fee`, and `buyer_price` onto the `part_request`. Display historical orders from these snapshotted columns. **Never** recompute an existing order's price from live settings — the day the admin changes the rate in production, old orders must still show what the buyer actually paid.

### 6.3 Payment gate (hard invariant)
The admin cannot purchase from a vendor until the buyer's payment is confirmed.
```
ConfirmOrderToVendorAction  requires  request.payment.status === 'confirmed'
```
If not, throw `PaymentNotConfirmedException`; disable the button in the UI. This is money-critical — it gets its own test asserting you cannot confirm a vendor purchase on an unpaid request.

### 6.4 Non-refundable (for launch) — but design for refunds later
No refund flow, no Stripe refund calls, no refund UI in this build. Refunds are Phase 2 (pending a client meeting). To keep that cheap later:
- Store rich payment records now: `gateway_payment_id`, `amount`, `currency`, and a `status` **enum** (`pending` | `confirmed` | `failed`) — never a boolean, so `refunded` can be added later without migration pain.
- Never destroy or overwrite payment data.
- The `procurement_failed` state today routes to re-quote (no money returned); later it simply gains a second exit for refunds.

### 6.5 Notifications must never point at vanished state (forward note for Phase 3)
General principle: any buyer-facing notification that references a specific mutable record (a quote, a message, anything the admin can still change or remove) needs defined behavior for "the thing changed after the notification was sent, before the buyer looked." Silently going stale — a notification that leads to a blank or confusing page — is not acceptable.

Relevant case from the multi-quote-presentation slice (client revision): a buyer can be presented several quotes at once and notified about them. Presenting is deliberately final today — there is no admin withdraw/remove action, by explicit client decision, so this specific scenario doesn't yet arise in practice. But if a future revision ever reintroduces a way to pull an already-presented quote (e.g. the vendor's part sold in the meantime), that action **must** be paired with a follow-up notification ("a previously presented option is no longer available") the moment Phase 3 notifications exist — never a silent delete. Keep this principle in mind for any future mutable-record notification, not just this one.

---

## 7. Database schema (build to this)

- **users**: name, email(unique), password, role(enum admin|buyer|vendor), is_active(bool), email_verified_at, timestamps, softDeletes
- **countries**: name(unique), is_active(bool default true), timestamps -- admin-managed reference data (client revision, replaces a free-text buyer_profiles field). Managed from the existing admin Settings page (add / rename / activate / deactivate), not a separate route. Never hard-deleted -- deactivate instead, so a buyer already assigned an inactive country keeps it.
- **makers**: name(unique), is_active(bool default true), timestamps -- admin-managed reference data (client revision, replaces a fixed lang-file dropdown list on part_requests). Managed the same way as countries, from the same Settings page. Never hard-deleted -- deactivate instead, so a request already assigned an inactive maker keeps it.
- **buyer_profiles**: user_id(fk), company_name, member_code(unique), country_id(fk → countries; active-only at write time for a new selection, but an existing row keeps a since-deactivated country rather than being forced to change it), phone
- **vendor_profiles**: user_id(fk), company_name, contact_person, phone, notify_email, status(enum active|suspended)
- **part_requests**: request_code(unique), buyer_id(fk), part_type(enum used|new|both), maker_id(fk → makers, active-only at write time), car_model, vin (always required, client-confirmed — no exceptions), oem_part_number(nullable), part_name, reference_url(nullable), memo(nullable), status(enum §5), selected_response_id(fk nullable), confirmed_vendor_id(fk nullable), cost_price(nullable), applied_rate(nullable), applied_min_fee(nullable), buyer_price(nullable), shipping_method(enum dhl|vehicle|container nullable), shipping_fee(nullable), timestamps, softDeletes
- **vendor_responses**: part_request_id(fk), vendor_id(fk), cost_price(nullable), quality_rank(enum S|A|B|C nullable), lead_time(enum, nullable), comment(text, nullable), weight_kg(decimal 8,2 nullable), length_cm/width_cm/height_cm(decimal 8,2 nullable), is_no_stock(bool), timestamps -- cost_price/quality_rank/lead_time/comment/weight_kg/length_cm/width_cm/height_cm are all nullable together: a one-tap `is_no_stock` reply has none of them. Weight/dimensions are required on a real quote (enforced in RequestResponse's Livewire rules(), not at the DB/Action layer, same as cost_price/quality_rank/lead_time) -- captured while the vendor has the part in hand, for Phase 4 checkout's admin→buyer shipping cost calculation
- **response_photos**: vendor_response_id(fk), disk, path, original_name, size
- **request_vendor** (pivot for 打診 broadcast targets): part_request_id(fk), vendor_id(fk), invited_at
- **messages**: part_request_id(fk), channel(enum buyer|vendor), vendor_id(fk nullable — vendor channel only), sender_role(enum admin|buyer|vendor), body(text), is_broadcast(bool), read_by_admin(bool), read_by_recipient(bool), created_at
- **invoices** (admin→buyer): part_request_id(fk), invoice_no(unique), buyer_id(fk), parts_price, shipping_fee, tax(nullable), total, status(enum issued|paid), issued_at
- **vendor_invoices** (vendor→admin): part_request_id(fk), vendor_id(fk), amount, issued_at
- **payments**: part_request_id(fk), invoice_id(fk nullable), gateway(enum), gateway_payment_id, amount, currency(char3), status(enum pending|confirmed|failed), paid_at(nullable), raw_response(json), timestamps
- **settings**: key, value, type  (holds margin_rate, margin_min_fee, shipping_fee_vehicle, shipping_fee_container, admin_sender_email — vehicle/container are provisional fixed fees pending client confirmation; DHL is deliberately **not** a settings key, see §14 Phase 4)
- Plus Laravel's `notifications`, `jobs`, `failed_jobs`, and spatie's `activity_log` + permission tables.

All money stored as integers (yen, no decimals).

---

## 8. Architecture & code conventions

- **Thin controllers / Livewire components**: receive input, delegate, return. No business logic.
- **Form Requests** for all validation — one findable place per action.
- **Service layer** for business logic (`PricingService`, `RequestService`, …).
- **Single-purpose Action classes** for money-critical multi-step flows (`CheckoutAction`, `ConfirmOrderToVendorAction`, `PresentQuoteAction`). One class, one job.
- **Enums** (PHP backed enums) for status, quality_rank, part_type, shipping_method, roles — no magic strings anywhere.
- **Events + Listeners** for side effects, especially notifications: e.g. `QuotePresented` → listener sends SES mail. Keep the core flow clean.
- **Policies** for the three-party isolation.
- Keep Laravel's default directory structure — familiarity is maintainability. No generic Repository pattern, no interfaces-on-everything. Don't over-engineer.
- Wrap every multi-write operation in a DB transaction (checkout creating order + payment + invoice must be atomic).

---

## 9. Testing standards (Pest)

- Test by risk, not uniformly.
- **Write the test first** for business logic — start with `PricingService` covering: below floor → floor wins; at floor; above floor → percentage wins. These pass for any configured rate/minFee.
- **Feature tests** for the full lifecycle: submit → broadcast → quote → present → pay → confirm → ship → receive.
- **Authorization tests are mandatory**: assert a buyer cannot load a vendor's cost, a vendor cannot see the buyer or another vendor.
- A **payment-gate test**: assert vendor purchase cannot be confirmed on an unpaid request.
- Factories + seeders for realistic data.
- A task is not "done" until Pint, Larastan, and Pest all pass.

---

## 10. Error handling standards

- Validation in Form Requests — bad input never reaches business logic.
- **Domain exceptions** (`PaymentNotConfirmedException`, `ProcurementUnavailableException`) over generic throws, mapped to clean user messages.
- DB transactions for atomicity (see §8).
- Wrap external services (Stripe, SES, S3) so failures degrade gracefully — a failed SES send must never roll back a confirmed payment; it queues a retry and logs.

---

## 11. Logging & audit standards

- Dedicated Monolog channels: a `payments` channel and an `audit` channel, separate from the default app log.
- `spatie/laravel-activitylog` for who-changed-what (margin changes, request edits, order confirmations).
- **Never log secrets** — no card data, no passwords, no full Stripe payloads.

---

## 12. How to work (workflow — follow this every session)

1. **Build phase by phase.** Complete and fully test the current phase before starting the next. Do not scaffold the whole app at once.
2. **Test-first** for all business logic and money-critical flows.
3. **Verify current package versions** before installing; never guess a version.
4. **When a decision is money-critical or genuinely ambiguous, STOP and ask** — do not guess on pricing, payments, or isolation.
5. Reference the prototype HTML for UI and fields; rebuild logic properly.
6. Run Pint + Larastan + Pest before declaring a task done.
7. Commit in small logical units with conventional commit messages. Keep secrets in GitHub Actions secrets, never in the repo.
8. Update `CONVENTIONS.md` / `README.md` when a new pattern is introduced.

---

## 13. Never do this

- Never let the admin confirm a vendor purchase before `payment.status === 'confirmed'`.
- Never build refund logic in this phase — but never use a boolean where a status enum belongs, and never destroy payment data.
- Never recompute a historical order's price from live settings — use snapshotted values.
- Never expose vendor cost/identity to buyers, or leak any data across the three parties.
- Never put business logic in controllers, Livewire components, or Blade.
- Never use magic strings for statuses/ranks/roles — use enums.
- Never commit secrets. Never log card data.
- Never add a dependency outside §2 without asking.

---

## 14. Phased build order

- **Phase 0 — Foundation**: project skeleton, `README.md` + `CONVENTIONS.md`, config (S3/SES/Redis/queue/Reverb), auth + roles (Spatie), base Blade/Tailwind layout, GitHub Actions CI pipeline. Then the **`settings` + `PricingService` slice with its full Pest suite** — our reference implementation that sets the quality bar.
- **Phase 1 — Accounts & masters**: buyer + vendor registration, vendor master CRUD + suspend/resume, settings admin UI (margin, shipping fees, sender email). Two account-creation paths, one shared onboarding invariant:
  - **Self-registration** (buyer only): user picks their own password. Account can log in immediately.
  - **Admin-created** (buyer or vendor — vendors are admin-created only, no vendor self-registration): admin creates the account; the system generates a temporary password, shown once to the admin and never emailed (the buyer/vendor may not be reachable yet — relaying it is on the admin's own time, the account simply waits). Stored hashed like any password. The account is active immediately on creation — there is no approval queue. `vendor_profiles.status` (`active`|`suspended`, §7) is trading state only, toggled by the admin's suspend/resume action once the vendor exists; it does not gate onboarding.
  - First login on a temporary password forces a password change before anything else — enforced by a middleware applied to the global `web` stack, not opt-in per route, so no route can accidentally skip it.
  - **Universal invariant**: email verification gates the ability to *act* (buyer creating a request, vendor responding to an inquiry) — not the ability to log in. An unverified user can log in and browse but cannot act until verified. Self-registered buyers go straight to this gate (no forced password change); admin-created accounts pass through the forced-change gate first, then this one.
  - Email delivery: log driver in Phase 1, SES from Phase 3.
- **Phase 2 — Core lifecycle**: buyer request form → admin board with status tabs → broadcast to vendors → vendor response with S3 photo upload → admin presents priced quote (pricing snapshot). **Also where owner/manager-vs-staff admin permissions get built** (§4) — every admin screen in this phase needs its Policy shaped for that from the start, not retrofitted after.
- **Phase 3 — Messaging**: two chat channels (buyer/vendor, single-target + broadcast), read/unread badges, SES notifications via events/listeners.
- **Phase 4 — Payments & ordering**: buyer checkout (shipping selection) → Stripe PaymentIntents → payment gate → `ordered_to_vendor` + `procurement_failed` path → buyer invoice + vendor invoice. **This is also where the shipping model gets finalized, not before**: `shipping_fee_vehicle`/`shipping_fee_container` (§7 `settings`) are provisional fixed fees pending client confirmation. DHL is realistically per-request — the admin enters the actual fee at quote time (`part_requests.shipping_fee`, already nullable for this) — not a global fixed number. A DHL Express (MyDHL) Rating-API auto-calculation is a plausible later enhancement, but it requires the *client's own* DHL Express business account; whether they have one is an open question to confirm with the client before scoping any DHL API work.
- **Phase 5 — Fulfilment & ops**: shipped/received, KPI dashboard, real-time via Reverb/Horizon, audit log wired in.
- **Phase 6 — Hardening**: coverage pass, Larastan level-up, isolation security review, deployment to the client's environment.

Deferred to Phase 2-post-launch (client meeting pending): **refunds**.

---

## 15. Start here

Begin Phase 0. First: confirm the current stable Laravel + package versions, then scaffold the project and set up the GitHub Actions CI pipeline. Before writing any feature code, produce the `settings` + `PricingService` slice test-first, and show me the Pest tests for the three margin cases before the implementation. Ask me if anything in this file is unclear.
