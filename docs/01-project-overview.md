# 01 — Project Overview

## Goal

Replace the clinic's three spreadsheets with one browser-based system that clinic computers and tablets can use, covering:

- Patient records and clinical history
- Appointments, doctor schedules, reception queue
- Visits, dental chart, treatments, prescriptions
- Billing, receipts, payments, refunds, dues
- Expenses, daily closing, accounting periods
- Doctor settlements
- Lab orders, inventory, purchasing
- Reports, audit history, backups

## Guiding principles

1. **Never silently delete.** Financial and clinical records are corrected or voided with a reason; history is kept.
2. **Separate records for separate facts.** Charges, payments, refunds, and expenses are separate tables. Balance = what was billed − what was paid + what was refunded.
3. **The system records, the doctor decides.** No automatic diagnosis, treatment choice, or dose calculation.
4. **Who and when on everything.** Every important record stores `created_by`, `updated_by`, timestamps, and an audit trail.
5. **Least access.** Each role sees only what its job needs.
6. **Rules are configuration, not code**, where the clinic hasn't confirmed them (commission %, voucher prefixes, periods).
7. **Fictional data during development.** Real data only after the safeguards in M15 are done.

## Patient journey (the core flow v1 must support)

```
Book appointment            (M04)
  ↓
Find or register patient    (M03)
  ↓
Check in, review history    (M04 → M05)
  ↓
Doctor records visit/treatment (M05)
  ↓
Doctor writes prescription  (M06)
  ↓
Print if enabled & requested (M06)
  ↓
Create bill, record payment (M07)
  ↓
Book follow-up, close the day (M04, M09)
```

Every step writes to the patient's timeline so it can be found at a later visit.

## Scope by release

### v1 — "A patient can go through the clinic" (Phase 1)
Login & roles, staff/doctor/room setup, doctor schedules, patient registration & search, booking, check-in queue, basic visit notes, procedure catalog, invoice, receipt voucher (RV), payment, daily cash closing, basic daily report.

### v2 — Clinical history & prescribing (Phase 2)
Medical history/allergies/alerts, visit timeline, dental chart, treatment plans, attachments (X-ray/photo), medicine catalog, prescriptions with print controls, secure share link.

### v3 — Finance controls (Phase 3)
Expenses & payment vouchers (PV), refunds/write-offs/opening balances, accounting periods & closing, doctor settlement plans and statements, finance reports.

### v4 — Lab & inventory (Phase 4)
Lab orders, suppliers, purchases, stock movements, expiry/reorder alerts.

### v5 — Migration, pilot, launch (Phase 5)
Spreadsheet import, duplicate review, parallel run, backups & restore drill, security review, training.

### Later / optional
Patient self-booking portal, SMS/WhatsApp reminders, multi-branch, Bangla UI translation (design for it from day one with `__()`), mobile app.

## Out of scope (for now)
Insurance claims, payroll for non-doctor staff, full double-entry general ledger (reports are enough; export to accountant's software if needed).
