# 03 — Business Rules & Open Questions

Status legend: ✅ agreed (from plan / owner explanation) · 🟡 proposed default, needs clinic confirmation · ❓ open question

---

## 1. Patient IDs
- ✅ Every patient has an internal ID (`patients.id`) that never changes.
- ✅ Existing IDs like `FD-01-23-0208` are kept as `legacy_code`, searchable.
- ✅ New visible ID in the clinic's own style `FD-MM-YY-NNNNNN` (e.g. `FD-11-26-000001`): MM-YY = registration month/year, 6-digit counter that never restarts. Stored in `patient_code`. (Q1, confirmed by clinic 2026-11-22)
- ✅ IDs are never reused, even after merge or deactivation.
- ✅ Possible duplicates (same phone, similar name + DOB) are warned about at registration; merge is a manager action with audit.

## 2. Vouchers (V. NO)
- ✅ Old `V. NO` = voucher number used for both money received and money paid out.
- ✅ New system uses two series:
  - **Receipt Voucher (RV)** — money received → linked to patient payment/invoice. e.g. `RV-2026-000001`
  - **Payment Voucher (PV)** — money paid out → linked to expense/supplier/payee/doctor. e.g. `PV-2026-000001`
- ✅ Voucher date is a separate field.
- 🟡 Sequence is continuous; whether it restarts each year is a setting (`voucher_reset_yearly`).
- ✅ Imported spreadsheet voucher number kept as `legacy_voucher_no`.
- ✅ Issued vouchers are never deleted — void or correct with reason + history. Voided number stays used.

## 3. Bills, dues, discounts, refunds
- 🟡 One itemised invoice per visit (or per treatment stage).
- 🟡 Discounts applied per invoice item (or invoice-level), with `approved_by` and reason. Discount above X% needs manager approval (X = setting).
- 🟡 A payment can be allocated to one or more invoices. If staff choose none → auto-allocate to oldest unpaid invoice first.
- ✅ Balance is shown after every payment.
- ✅ Previous dues from spreadsheets imported as **opening balance** records, separate from new invoices.
- ✅ Refund links to its original payment and invoice. Cancelled treatment = invoice item void/credit + refund.
- ✅ Write-off needs reason + approval.
- 🟡 Advance/deposit = payment with no invoice yet; held as patient credit, allocated later.

## 4. Payment methods
- ✅ Cash, bKash, Nagad, Card, Bank transfer.
- 🟡 Split payment allowed (one receipt, multiple method lines).
- 🟡 Digital payments store transaction reference.

## 5. Daily closing
- 🟡 At end of day the cashier counts cash and checks digital totals; system compares expected vs actual and records difference with a note.
- 🟡 After a day is closed, payments for that date cannot be edited by cashier; manager can reopen with reason.

## 6. Accounting periods
- 🟡 Admin configures periods (default: calendar month). Settings stored with effective dates.
- ✅ Closed period → routine edits blocked. Manager/accountant can reopen or post correction in a later period, with reason in audit.

## 7. Doctor settlements
- ✅ Each doctor has a settlement plan made of one or more components: % of eligible collections, fixed per visit/procedure, fixed salary/periodic fee.
- 🟡 Default: % calculated on **amount actually collected** for eligible items, after discounts and refunds.
- 🟡 Do **not** deduct lab costs, supplies, overhead unless the doctor's agreement says so (configurable flag per plan).
- ✅ Rates per doctor, optionally per procedure, with effective start dates. Rate change doesn't rewrite old statements.
- ✅ Period: weekly / monthly / custom.
- ✅ Flow: generate statement → review → approve (authorised person) → pay (creates PV).

## 8. Appointments
- ✅ Statuses: Requested → Confirmed → Arrived → In treatment → Completed; plus Cancelled, No-show, Waitlist.
- ✅ Availability = doctor working hours − leave/blocked time − existing bookings, by appointment type duration and free chair/room.
- ✅ Two bookings can't hold the same doctor or chair at the same time.
- ✅ Show only free slots; never show other patients' appointments to patients.
- ✅ If preferred doctor has no slot, offer other doctors as options (never auto-switch).

## 9. Prescriptions
- ✅ Doctor selects medicine from catalog, enters and reviews dose/frequency/duration/quantity/instructions. System never calculates dose.
- ✅ Each medicine line is its own record linked to prescription → visit → patient.
- ✅ Correct / cancel / replace keeps history.
- ✅ Clinic setting enables/disables printing; per prescription "patient wants printed copy" flag.
- ✅ Prescription is saved even if not printed.
- 🟡 Electronic sharing via expiring signed link, with record of when/how shared. WhatsApp/SMS/email later.

---

## ❓ Questions to confirm with clinic owner / accountant

| # | Question | Blocks |
|---|----------|--------|
| ~~Q1~~ | ✅ Answered 2026-11-22: keep the old style as `FD-MM-YY-NNNNNN`, counter never restarts | M03 |
| Q2 | RV/PV prefix format and yearly reset? | M07, M08 |
| Q3 | Discount limit before manager approval? Who approves write-offs? | M07 |
| Q4 | Which revenue counts for doctor settlement (which procedures, consultation fee?) | M10 |
| Q5 | Commission % / fixed amounts per doctor; settlement period? | M10 |
| Q6 | Are lab costs shared with doctor? Under what agreement? | M10, M11 |
| Q7 | Accounting period cutoff (calendar month? custom date?) | M09 |
| Q8 | Who can edit/void after daily close? After monthly close? | M09 |
| Q9 | Which clinical/financial info can each role see? (review [04-roles-permissions.md](04-roles-permissions.md)) | M01 |
| Q10 | Which doctors, rooms, chairs appear in calendar? Default slot length per appointment type? | M02, M04 |
| Q11 | Print prescriptions, electronic sharing, or both? Which channel? | M06 |
| Q12 | Expense categories list? | M08 |
| Q13 | Procedure catalog with approved prices? | M05, M07 |
| Q14 | Where will the app be hosted? Data location acceptable? | M15 |

Record answers here with date and who confirmed, then update the rule status to ✅.

---

## Launch safeguards (must be done before real data)
- [ ] Change spreadsheet sharing from "anyone with link" to **Restricted**.
- [ ] Individual staff accounts (no shared logins).
- [ ] Role permissions reviewed and approved by owner.
- [ ] Automated backups + one successful restore drill.
- [ ] Hosting data location and terms reviewed.
- [ ] Qualified local review of privacy / record-keeping requirements.
- [ ] HTTPS, strong passwords, session timeout.
