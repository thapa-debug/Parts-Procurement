# Demo walkthrough

A realistic cast of accounts for showing Phase 1 (accounts & masters) to
the client, or for exercising the app locally without hand-creating data
every time.

## Seed it

```bash
php artisan migrate:fresh --seed
```

`DatabaseSeeder` delegates to `database/seeders/DemoSeeder.php`, which is
the only place the demo cast is defined — kept separate so it doesn't
clutter what would otherwise hold real production seed logic. Safe to
re-run any time; it always starts from a fresh database.

No real email is sent: local `.env` defaults to `MAIL_MAILER=log`, so any
verification email lands in `storage/logs/laravel.log` (or point
`MAIL_MAILER` at [MailDev](https://github.com/maildev/maildev) instead —
see `CONVENTIONS.md`).

## Login credentials

Every seeded account uses the password **`password`**.

| Role | Name | Company | Email | Notes |
|---|---|---|---|---|
| Admin | Aiko Tanaka | — | `admin@demo.test` | Full `/admin` access |
| Vendor | Kenichi Sato | Yamato Auto Dismantlers | `vendor1@demo.test` | Active, verified |
| Vendor | Daisuke Suzuki | Kanto Parts Recycle Co. | `vendor2@demo.test` | Active, verified |
| Vendor | Misaki Tanaka | Kyushu Used Parts Center | `vendor3@demo.test` | Active, verified |
| Vendor | Naoki Ito | Hokkaido Recycle Auto | `vendor4@demo.test` | Active, verified |
| Buyer | James Carter | Global Auto Parts Ltd | `buyer1@demo.test` | Verified, approved, member code `BYR-000006` |
| Buyer | Fatima Al-Sayed | Pacific Rim Motors | `buyer2@demo.test` | Verified, approved, member code `BYR-000007` |
| Buyer | Liam O'Connor | Southern Cross Auto Imports | `buyer3@demo.test` | **Unverified**, approved — shows the badge/resend flow, member code `BYR-000008` |
| Buyer | Sofia Herrera | Andes Auto Traders | `buyer4@demo.test` | Verified, **pending approval** — shows the approval-queue flow, member code `BYR-000009` |

Only the admin portal (`/admin/*`) has real screens in Phase 1. Logging
in as a vendor or buyer lands on the shared authenticated shell with no
role-specific content yet — that's expected, not a bug; buyer/vendor
portals are Phase 2+.

## Suggested walkthrough

Everything below is reachable from a fresh `migrate:fresh --seed` and the
credentials table above. Roughly 10–15 minutes end to end.

### 1. Vendor master (`/admin/vendors`)

1. Log in as `admin@demo.test`.
2. Land on **Vendors** — four seeded vendors, all `Verified`. Search by
   company name, contact, or email to see the list filter live.
3. Open a row's **Actions** menu and choose **Edit** to open the shared
   detail-edit view. Change the phone number, **Save changes**, confirm
   the "Saved." indicator.
4. Back on the list, click **New vendor** and fill in the form. On
   submit, the one-time temporary-password reveal appears — try
   **Copy**, tick "I've saved this password", then **Done**. That
   password is never shown again and never emailed (CLAUDE.md §14). The
   reveal also confirms a verification email was sent automatically to
   the new vendor's address — check `storage/logs/laravel.log` (or
   MailDev) for it.
5. Open the row-actions menu (**Actions**) on any vendor and try
   **Suspend** / **Resume** — note the status badge flip and that a
   suspended vendor's status change is recorded in the activity log
   (`VendorProfile::getActivitylogOptions()`).

### 2. Buyer master (`/admin/buyers`)

1. Go to **Buyers**. Note Southern Cross Auto Imports shows an
   **Unverified** badge — every other row shows **Verified**.
2. Open that row's **Actions** menu and click **Resend verification
   email**. The button flips to "Sent." — check
   `storage/logs/laravel.log` for the outgoing notification (or MailDev's
   inbox at `http://127.0.0.1:1080` if you've pointed `.env` at it). This
   is the manual fallback for a bounced or lost email — a fresh **New
   buyer** creation (below) sends this automatically, no click needed.
3. Choose **Edit** from a buyer's Actions menu to see the same shared
   detail-edit view used for vendors, with buyer-specific fields
   (destination country) instead of vendor ones — same component,
   different data, proving the reuse (see `CONVENTIONS.md`).
4. Click **New buyer** and note the **Approve immediately** checkbox at
   the bottom of the form, checked by default. Leave it checked and the
   new buyer can act as soon as they verify; uncheck it and they land in
   the pending-approval queue instead (Andes Auto Traders, `buyer4@demo.test`,
   is a seeded example of that pending state) — approving them from that
   queue is a later slice, not yet built.

### 3. Settings (`/admin/settings`)

Adjust the margin rate or minimum fee and save — this is the live config
`PricingService` will read from once Phase 2 starts quoting requests.

### 4. Forced password-change gate

This needs a fresh account, since every seeded login already has a
real password set:

1. From the Vendor master, create a new vendor and copy its temporary
   password from the reveal modal (step 1.4 above).
2. Log out, then log back in as that new vendor's email with the
   temporary password.
3. You're redirected straight to **Change password** before anything
   else is reachable — confirm you can't navigate away first. Set a new
   password.
4. You land in the app normally afterward, still showing the
   verification banner (admin-created accounts aren't pre-verified even
   though their verification email already went out at creation).

### 5. Self-registration + email verification

1. Log out, go to `/register`, and sign up as a new buyer with your own
   choice of password.
2. You're logged in immediately (no forced password-change — that's
   only for admin-created accounts) but see the amber verification
   banner right away.
3. Check `storage/logs/laravel.log` for the verification email, copy
   its signed link into the browser, and confirm the banner disappears.

## Regenerating the demo data

`DemoSeeder` is idempotent only in the sense that `migrate:fresh --seed`
always starts clean — running `php artisan db:seed` alone against a
non-empty database will fail on the unique email/member-code
constraints. Always pair it with `migrate:fresh` for a repeatable demo.
