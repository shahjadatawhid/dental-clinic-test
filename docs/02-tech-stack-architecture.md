# 02 — Tech Stack & Architecture

## Stack (recommended)

| Layer | Choice | Why |
|-------|--------|-----|
| Language | PHP 8.3+ | Required by current Laravel |
| Framework | **Laravel** (latest stable) | Auth, queues, migrations, validation built in |
| UI | **Blade + Livewire 3 + Alpine.js + Tailwind CSS** | Interactive screens (calendar, queue, chart) without a separate SPA |
| Admin/CRUD screens | **Filament** (optional, recommended) | Fast catalog/settings/master-data screens; custom Livewire pages for complex flows |
| Database | **MySQL 8.0+** (InnoDB, `utf8mb4`) | Widely available on BD hosting; FKs + unique keys enforce integrity; double-booking prevented with row locks in code |
| Auth scaffold | Laravel Breeze (Livewire) or Filament auth | Login, password reset |
| Tests | Pest | Readable feature tests |
| Build | Vite | Comes with Laravel |
| Local dev | Laragon (Windows) | PHP + MySQL + Composer in one install |

> **Decision D-01 (confirmed): Laravel + MySQL + Tailwind CSS.**
> MySQL has no exclusion constraints, so overlap checks (appointments) and sequence numbers run inside `DB::transaction()` with `lockForUpdate()`. Use `utf8mb4_unicode_ci` so Bangla text stores correctly. Strict mode stays on (Laravel default).

## Packages

| Package | Purpose | Module |
|---------|---------|--------|
| `spatie/laravel-permission` | Roles & permissions | M01 |
| `spatie/laravel-activitylog` | Audit history of model changes | M01 |
| `spatie/laravel-medialibrary` | X-rays, photos, documents | M05 |
| `barryvdh/laravel-dompdf` (or `spatie/laravel-pdf`) | Prescription, invoice, receipt, voucher printing | M06, M07, M08 |
| `maatwebsite/excel` | Spreadsheet import/export | M13, M14 |
| `spatie/laravel-backup` | DB + file backups | M15 |
| `laravel/pint` | Code style | all |
| `larastan/larastan` | Static analysis | all |

## Architecture

Single Laravel monolith, organised **by module** inside `app/`:

```
app/
  Models/                     # Eloquent models (one folder, simple)
  Enums/                      # AppointmentStatus, PaymentMethod, VoucherType, ...
  Actions/                    # One class per business operation
    Patients/RegisterPatient.php
    Appointments/BookAppointment.php
    Billing/CreateInvoice.php
    Billing/RecordPayment.php
    Billing/VoidReceipt.php
  Services/
    NumberSequenceService.php # RV/PV/patient code/invoice numbers
    AvailabilityService.php   # free slots
    BalanceService.php        # patient balance
    SettlementCalculator.php
  Policies/                   # Authorization per model
  Livewire/                   # Screens, grouped by module
    Patients/ Appointments/ Visits/ Billing/ ...
  Http/Controllers/           # Only for PDFs, downloads, share links
database/
  migrations/  seeders/  factories/
tests/
  Feature/<Module>/  Unit/
docs/
```

### Rules for code
- **Business logic lives in Actions/Services**, not in Livewire components or controllers. Components call actions.
- **Every money-changing action runs in `DB::transaction()`.**
- **Money:** `decimal(12,2)` columns, cast with a Money/decimal cast; never use float math. All amounts in BDT.
- **Dates:** app timezone is `Asia/Dhaka` (single clinic, no DST), so timestamps are stored and shown in Dhaka time. Business dates (invoice date, voucher date, closing date) are `date` columns.
- **Enums:** PHP backed enums for statuses; never magic strings.
- **Soft deletes** only for master data (patients, medicines, procedures). Financial records are never deleted — they get `status = void` + reason.
- **Authorization:** every Livewire action calls `$this->authorize()` / policies. Never rely on hiding buttons.
- **New screen checklist:** (1) route with `->middleware('permission:...')`, (2) `Gate::authorize()` in every action that changes data, (3) menu entry in [app/Navigation/Menu.php](../app/Navigation/Menu.php) with the **same** permission. [tests/Feature/Authorization/RouteAccessTest.php](../tests/Feature/Authorization/RouteAccessTest.php) fails if a signed-in route has no permission middleware (unless listed as personal), if a user without the permission doesn't get 403, or if a menu item and its route disagree.
- **Audit:** every model with clinical or financial meaning uses:
  - `App\Models\Concerns\Auditable` — logs create/update/delete with old → new values and the acting user (`activity_log` table). Override `auditExcept()` to skip noisy columns; `$hidden` columns are never logged.
  - `App\Models\Concerns\HasUserstamps` + `$table->userstamps();` in the migration — fills `created_by` / `updated_by`.
  - `App\Support\Audit::record($event, $model, $description, $props, reason: ...)` for business events (void, approve, reopen, roles_changed...).
  - Sign-in activity (login, logout, failed, blocked) goes to the `auth` log automatically.
  - Never schedule `activitylog:clean` — the audit trail is part of the record.
  - Viewed at **Administration → Audit history** (`audit.view`). Audit entries copy changed data, so a new Auditable model with clinical or settlement data **must** be added to [app/Authorization/AuditLogAccess.php](../app/Authorization/AuditLogAccess.php) with the permission needed to see it; otherwise e.g. an accountant could read clinical notes through the audit log.
- **Settings:** a clinic-configurable value is added to [app/Settings/Definitions.php](../app/Settings/Definitions.php) (key, group, type, default, label, rules) and read with `setting('group.key')`. It then appears on **Administration → Clinic settings** automatically. Only non-default values are stored; changes are audited. Unknown keys throw, so typos fail loudly.
- **Fictional data:** factories use `fake_bd()->name('female')`, `->phone()`, `->address()` for Bangladeshi-looking made-up data. Each module adds a demo seeder to [DemoDataSeeder](../database/seeders/DemoDataSeeder.php) (idempotent, uses `GuardsAgainstProduction`). `DatabaseSeeder` only seeds reference data (roles, sequences) in production.
- **i18n:** all UI text through `__()` so Bangla can be added later.
- **Tests:** each Action has a feature test. Money and sequence logic must have tests before merge.

### Number sequences
- Series are the `App\Enums\SequenceSeries` cases (patient, invoice, receipt_voucher, payment_voucher, lab_order, purchase) with default formats (`FD-11-26-000001`, `RV-2026-000001`...).
- `number_sequences` holds each series' prefix / format (`{prefix}`, `{year}`, `{yy}`, `{mm}`, `{number}`) / padding / `reset_yearly`; `number_sequence_counters` holds the counter per period (the year if `reset_yearly`, else 0), so back-dated documents use their own year's counter.
- Take a number **inside the transaction that saves the document**:
  `$no = app(NumberSequenceService::class)->next(SequenceSeries::ReceiptVoucher, $receiptDate);`
  The counter row is locked (`SELECT ... FOR UPDATE`) until commit. If the save fails the number is released (no gap); a voided document keeps its number.
- `preview()` shows the next number for display only — never save it.
- `php artisan app:sync-sequences` pre-creates series and counters for this year and next; it runs from the seeder and daily at 00:05 (needs the scheduler in production).
- Concurrency is proven by `tests/Concurrency` (4 PHP processes × 25 numbers → 100 unique, gap-free). That suite doesn't use `RefreshDatabase` and cleans up after itself.
- Prefix/format changes are audited. Confirm formats with the clinic (Q1, Q2) before real data. No admin screen yet — change via tinker or a later settings screen.

## Environments
- **local** — developer machine, fictional seed data.
- **staging** — same as production, fictional or anonymised data, for clinic review.
- **production** — real data, only after M15 checklist.

## Git workflow
- `main` = deployable. Feature branches `feature/M04-booking`. Small commits.
- Commit message: `M04: add appointment booking action`.
- Task IDs from [07-tasks.md](07-tasks.md) in branch/commit names.
