# Dental Clinic Management System (Laravel)

One system for patient care, appointments, clinic operations, and finances.

> **Status:** Planning. No code yet. Use **fictional data only** until the launch safeguards in [docs/03-business-rules.md](docs/03-business-rules.md) are done.

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
