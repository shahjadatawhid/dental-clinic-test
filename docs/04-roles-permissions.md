# 04 — Roles & Permissions

Implemented with `spatie/laravel-permission`. Roles are **templates**; an admin can adjust them, but changing access to clinical or financial records requires the `roles.manage` permission and is written to the audit log.

## Permission naming

`<resource>.<action>` where action ∈ `view`, `create`, `update`, `approve`, `void`, `export`, `manage`.

Examples: `patients.view`, `patients.view_clinical`, `appointments.create`, `invoices.void`, `payments.create`, `vouchers.approve`, `settlements.view_own`, `reports.finance.export`.

Scoping rules (enforced in Policies, not in permissions table):
- Doctor: `visits.*` only for patients they treat / appointments assigned to them (clinic may widen — Q9).
- Doctor: `settlements.view_own` only their own statement.

## Role templates

| Role | Purpose |
|------|---------|
| `system_admin` | Users, roles, settings, technical admin. No clinical/financial data unless also given another role. |
| `owner` | Read-only reports, audit history; approve settlements. |
| `manager` | Scheduling, patient ops, approve corrections, reopen day/period. |
| `receptionist` | Patient contact info, appointments, check-in, invoices, receipts, daily cashiering. |
| `doctor` | Own schedule, clinical records, prescriptions; own settlement (if allowed). |
| `accountant` | Receipts, PV, expenses, reconciliation, finance reports, settlements. |
| `lab_coordinator` | Lab cases + minimum patient identity. |
| `inventory_staff` | Stock, suppliers, purchases. |

## Matrix (starting proposal — confirm Q9)

V = view, C = create, U = update, A = approve, X = void, E = export, — = none, own = own records only

| Resource | Admin | Owner | Manager | Reception | Doctor | Accountant | Lab | Inventory |
|----------|:-----:|:-----:|:-------:|:---------:|:------:|:----------:|:---:|:---------:|
| Users / roles / settings | VCU | V | — | — | — | — | — | — |
| Staff & schedules | V | V | VCU | V | own V | — | — | — |
| Patient demographics | — | V | VCU | VCU | V | V | V (min) | — |
| Medical history / alerts | — | — | V | V (alerts only) | VCU | — | — | — |
| Appointments | — | V | VCUX | VCUX | own V/U status | — | — | — |
| Visits / dental chart | — | — | V | — | VCU | — | — | — |
| Prescriptions | — | — | V | V/print | VCUX | — | — | — |
| Procedure catalog | — | V | VCU | V | V | V | — | — |
| Medicine catalog | — | V | VCU | — | V (suggest) | — | — | — |
| Invoices | — | V | VCUAX | VCU | own V | VE | — | — |
| Payments / RV | — | V | VCAX | VC | — | VE | — | — |
| Refunds / write-offs | — | V | VCA | C (request) | — | VA | — | — |
| Expenses / PV | — | V | VCA | — | — | VCUXE | — | C (purchase) |
| Daily closing | — | V | VA (reopen) | VC | — | V | — | — |
| Accounting periods | — | V | — | — | — | VA (close/reopen) | — | — |
| Doctor settlements | — | VA | V | — | own V | VCE | — | — |
| Lab orders | — | V | VCU | VC | VCU | V (cost) | VCU | — |
| Inventory / suppliers | — | V | V | — | — | V | — | VCUX |
| Reports | — | VE | VE | daily V | own V | VE | — | stock V |
| Audit log | V | V | V | — | — | V | — | — |

## Guardrails
- Nobody can approve their own discount/refund/write-off/settlement above the limit.
- Changing a role's clinical or financial permissions → audit entry with old/new values.
- All exports of patient data are logged (who, what, when).
- Session timeout configurable (default 30 min idle on shared clinic PCs).
