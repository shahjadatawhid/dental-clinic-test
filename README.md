# Dental Clinic Management System (Laravel)

One system for patient care, appointments, clinic operations, and finances.

> **Status:** Planning. No code yet. Use **fictional data only** until the launch safeguards in [docs/03-business-rules.md](docs/03-business-rules.md) are done.

## Stack

Laravel 12 · Livewire 4 (+ Flux UI) · Tailwind CSS 4 · MySQL 8 (MariaDB 10.11 via XAMPP works for local dev) · Pest · Pint · Larastan

## Local setup (Windows + XAMPP)

Requirements: PHP 8.3+ with `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `sodium` enabled in `php.ini`; Composer; Node 20+; MySQL/MariaDB running.

```bash
# 1. Create databases (once)
mysql -u root -e "CREATE DATABASE dental_clinic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE dental_clinic_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Install
composer install
npm install
cp .env.example .env      # then check DB_* values
php artisan key:generate
php artisan migrate --seed

# 3. Run (server + queue + vite)
composer dev              # open http://127.0.0.1:8000
```

**Development logins** (created by `php artisan migrate --seed` in `local` only; password `password`):
`system-admin@clinic.test`, `owner@clinic.test`, `manager@clinic.test`, `receptionist@clinic.test`, `doctor@clinic.test`, `accountant@clinic.test`, `lab-coordinator@clinic.test`, `inventory-staff@clinic.test`

| Command | What it does |
|---------|--------------|
| `composer test` | Run Pest tests (uses `dental_clinic_test` DB) |
| `composer lint` | Fix code style (Pint) |
| `composer lint:check` | Check style without changing files |
| `composer analyse` | Static analysis (Larastan) |
| `npm run build` | Build CSS/JS for production |

## Planning documents

| # | Document | What it covers |
|---|----------|----------------|
| 01 | [Project overview](docs/01-project-overview.md) | Goal, scope, patient journey, v1 vs later |
| 02 | [Tech stack & architecture](docs/02-tech-stack-architecture.md) | Laravel stack, packages, folder structure, coding conventions |
| 03 | [Business rules & open questions](docs/03-business-rules.md) | Agreed rules (IDs, vouchers, dues, settlements, periods) + questions for the clinic |
| 04 | [Roles & permissions](docs/04-roles-permissions.md) | Role templates and permission matrix |
| 05 | [Database design](docs/05-database-design.md) | Tables, key columns, relationships, constraints |
| 06 | [Development roadmap](docs/06-roadmap.md) | Phases, milestones, module order |
| 07 | [Task list](docs/07-tasks.md) | Every task with ID, module, dependency, status |

## Modules

| ID | Module | Phase |
|----|--------|-------|
| M01 | [Foundation: auth, roles, settings, audit](docs/modules/M01-foundation.md) | 1 |
| M02 | [Staff, doctors, rooms & schedules](docs/modules/M02-staff-schedules.md) | 1 |
| M03 | [Patients](docs/modules/M03-patients.md) | 1 |
| M04 | [Appointments & reception queue](docs/modules/M04-appointments.md) | 1 |
| M05 | [Clinical visits & dental chart](docs/modules/M05-clinical-visits.md) | 1 (basic) / 2 |
| M06 | [Medicine catalog & prescriptions](docs/modules/M06-prescriptions.md) | 2 |
| M07 | [Billing, receipts & payments](docs/modules/M07-billing.md) | 1 |
| M08 | [Expenses & payment vouchers](docs/modules/M08-expenses.md) | 3 |
| M09 | [Daily closing & accounting periods](docs/modules/M09-closing-periods.md) | 1 (daily) / 3 |
| M10 | [Doctor settlements](docs/modules/M10-settlements.md) | 3 |
| M11 | [Lab orders](docs/modules/M11-lab.md) | 4 |
| M12 | [Inventory & purchasing](docs/modules/M12-inventory.md) | 4 |
| M13 | [Reports & dashboard](docs/modules/M13-reports.md) | 1–4 (grows) |
| M14 | [Data migration (spreadsheet import)](docs/modules/M14-migration.md) | 5 |
| M15 | [Operations: backup, deploy, security](docs/modules/M15-operations.md) | 5 |
