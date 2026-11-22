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
- **Dates:** store `timestamp` in UTC, display in `Asia/Dhaka`. Business dates (invoice date, voucher date, closing date) are `date` columns in clinic local time.
- **Enums:** PHP backed enums for statuses; never magic strings.
- **Soft deletes** only for master data (patients, medicines, procedures). Financial records are never deleted — they get `status = void` + reason.
- **Authorization:** every Livewire action calls `$this->authorize()` / policies. Never rely on hiding buttons.
- **Audit:** models with clinical or financial meaning use `LogsActivity`.
- **i18n:** all UI text through `__()` so Bangla can be added later.
- **Tests:** each Action has a feature test. Money and sequence logic must have tests before merge.

### Number sequences
A `number_sequences` table holds one row per series (`patient`, `invoice`, `RV`, `PV`, `lab`, `purchase`). `NumberSequenceService::next('RV')` does `SELECT ... FOR UPDATE` inside the transaction, increments, formats (e.g. `RV-2026-000001`). Gaps only happen if a transaction rolls back — voided vouchers keep their number.

## Environments
- **local** — developer machine, fictional seed data.
- **staging** — same as production, fictional or anonymised data, for clinic review.
- **production** — real data, only after M15 checklist.

## Git workflow
- `main` = deployable. Feature branches `feature/M04-booking`. Small commits.
- Commit message: `M04: add appointment booking action`.
- Task IDs from [07-tasks.md](07-tasks.md) in branch/commit names.
