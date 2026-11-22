# 05 — Database Design

Conventions:
- Every table: `id` (bigint PK), `created_at`, `updated_at`. Most also `created_by`, `updated_by` (FK `users`).
- Money: `decimal(12,2)`. Business dates: `date`. Moments: `timestamp` (UTC).
- Statuses: `string` backed by PHP enums.
- FKs with `restrict` on delete for anything financial/clinical.
- `notes` = `text nullable` where shown.

Legend: **PK** primary key · **FK** foreign key · **UQ** unique · **IDX** index

---

## M01 Foundation

| Table | Key columns |
|-------|-------------|
| `users` | name, email UQ, phone, password, is_active, deactivated_at, last_login_at (staff profile links back via `staff.user_id`) |
| `roles`, `permissions`, `model_has_roles`, ... | from spatie/laravel-permission |
| `settings` | key UQ, value json, effective_from date nullable, updated_by |
| `activity_log` | from spatie/laravel-activitylog (who, what model, old/new values, reason) |
| `number_sequences` | series UQ (patient, invoice, RV, PV, lab, purchase), prefix, year nullable, next_value, padding, reset_yearly bool |
| `clinic_profile` | name, address, phone, logo_path, bin/registration no (singleton row) |

## M02 Staff & schedules

| Table | Key columns |
|-------|-------------|
| `staff` | user_id FK UQ nullable, full_name, type (doctor/staff), designation, gender, phone IDX, email, joined_on, left_on, is_active, notes — never deleted |
| `doctors` | staff_id FK UQ, registration_no UQ nullable (BMDC), specialty, qualifications, signature_path, default_slot_minutes nullable (→ setting), calendar_color, is_bookable |
| `rooms` | name UQ, notes, sort_order, is_active |
| `chairs` | room_id FK, name (UQ per room), sort_order, is_active — usable only if its room is active |
| `doctor_schedules` | doctor_id FK, effective_from (UQ per doctor), effective_to nullable, note — one version of the weekly routine; versions never overlap |
| `doctor_schedule_blocks` | doctor_schedule_id FK (cascade), weekday (0=Sun…6=Sat), start_time, end_time, chair_id FK nullable — no overlapping blocks on a day |
| `doctor_time_off` | doctor_id FK, starts_at, ends_at, type (leave/blocked/holiday), reason |
| `clinic_holidays` | date UQ, name |

## M03 Patients

| Table | Key columns |
|-------|-------------|
| `patients` | patient_code UQ (FD-MM-YY-NNNNNN), legacy_code IDX nullable (not unique), registered_on, full_name IDX, gender, date_of_birth nullable, age_years + age_recorded_on (if DOB unknown), phone IDX, alt_phone IDX, email, address, occupation, preferred_language (bn/en), emergency_contact_name/phone, referred_by, notes, is_active, merged_into_id FK nullable — never deleted |
| `patient_consents` | patient_id, type (treatment/data/sharing), given_at, given_by_name, document media, revoked_at |
| `patient_alerts` | patient_id, type (allergy/condition/medication/other), title, severity, notes, source, recorded_by, recorded_at, resolved_at |
| `patient_medical_history` | patient_id, condition, details, since, recorded_by, recorded_at |

## M04 Appointments

| Table | Key columns |
|-------|-------------|
| `appointment_types` | name, default_minutes, color, is_active |
| `appointments` | patient_id FK nullable (walk-in/request), requested_name/phone (if no patient yet), doctor_id FK, chair_id FK nullable, appointment_type_id, starts_at, ends_at, status, source (phone/walk-in/portal), reason, cancelled_reason, booked_by, checked_in_at, started_at, completed_at, queue_no |
| `waitlist_entries` | patient_id, doctor_id nullable, preferred_from/to dates, notes, status |

Double-booking guard (MySQL): index `(doctor_id, starts_at)` and `(chair_id, starts_at)`. In `BookAppointment`, inside `DB::transaction()`:
1. Lock the doctor row (`Doctor::lockForUpdate()->find($id)`) — and the chair row if one is chosen — so concurrent bookings for that doctor/chair wait in line.
2. Check overlap: `starts_at < :new_end AND ends_at > :new_start AND status NOT IN ('cancelled','no_show','waitlist')`.
3. If none, insert; otherwise throw a "slot taken" error.

## M05 Clinical

| Table | Key columns |
|-------|-------------|
| `visits` | patient_id, appointment_id nullable, doctor_id, visit_date, chief_complaint, examination, diagnosis, notes, status (open/closed), closed_at |
| `procedures` | code UQ, name, category, default_price, is_tooth_specific, is_settlement_eligible, is_active |
| `procedure_price_history` | procedure_id, price, effective_from |
| `treatment_plans` | patient_id, doctor_id, title, status (proposed/accepted/in_progress/done/cancelled), estimated_total |
| `treatment_plan_items` | treatment_plan_id, procedure_id, tooth_number nullable, surface, estimated_price, status, sequence |
| `treatments` (performed) | visit_id, patient_id, doctor_id, procedure_id, tooth_number, surface, notes, price, status (done/voided), treatment_plan_item_id nullable |
| `tooth_records` | patient_id, tooth_number (FDI 11–48, 51–85), condition (caries/missing/filled/crown/rct/implant/...), surface, recorded_visit_id, recorded_by, recorded_at |
| `media` | spatie medialibrary (X-ray, photo, document) attached to patient/visit |

## M06 Prescriptions

| Table | Key columns |
|-------|-------------|
| `medicines` | generic_name IDX, brand_name IDX, strength, form (tab/cap/syrup/gel/...), route, manufacturer, is_active |
| `prescriptions` | patient_id, visit_id, doctor_id, issued_at, status (active/cancelled/replaced), replaces_id FK nullable, advice, follow_up_date, wants_print bool, printed_at, printed_by, cancel_reason |
| `prescription_items` | prescription_id, medicine_id, medicine_snapshot json (name/strength/form at time of issue), dose, frequency, duration, quantity, route, instructions, sort |
| `prescription_shares` | prescription_id, channel (link/print/...), token hash UQ, expires_at, shared_by, shared_at, opened_at |

## M07 Billing

| Table | Key columns |
|-------|-------------|
| `invoices` | invoice_no UQ, patient_id, visit_id nullable, doctor_id nullable, invoice_date, subtotal, discount_total, total, paid_total, refunded_total, balance, status (draft/issued/partially_paid/paid/void), void_reason, voided_by |
| `invoice_items` | invoice_id, procedure_id nullable, treatment_id nullable, doctor_id (for settlement), description, tooth_number, qty, unit_price, discount_amount, discount_reason, discount_approved_by, line_total, status (active/void) |
| `receipts` (Receipt Voucher RV) | voucher_no UQ, legacy_voucher_no IDX, patient_id, receipt_date, amount, status (issued/void), void_reason, received_by, closing_id nullable |
| `receipt_methods` | receipt_id, method (cash/bkash/nagad/card/bank), amount, reference |
| `payment_allocations` | receipt_id, invoice_id, amount (sum ≤ receipt amount; remainder = patient credit) |
| `refunds` | patient_id, receipt_id, invoice_id nullable, amount, method, reason, approved_by, refund_date, payment_voucher_id nullable |
| `write_offs` | invoice_id, amount, reason, approved_by, date |
| `opening_balances` | patient_id UQ, amount, as_of_date, source (import), verified_by |

Balance = opening_balance + Σ invoice totals (non-void) − Σ allocations − write-offs + refunds. Store `paid_total/balance` on invoices as cached values recalculated by `BalanceService` inside the same transaction.

## M08 Expenses

| Table | Key columns |
|-------|-------------|
| `expense_categories` | name UQ, parent_id nullable, is_active |
| `payees` | name, type (supplier/lab/doctor/utility/other), phone, notes |
| `payment_vouchers` (PV) | voucher_no UQ, legacy_voucher_no, voucher_date, payee_id nullable, payee_name, expense_category_id, amount, method, reference, description, status (draft/approved/paid/void), approved_by, void_reason, source_type/source_id (morph: settlement, lab_order, purchase, refund) |

## M09 Closing & periods

| Table | Key columns |
|-------|-------------|
| `daily_closings` | closing_date UQ, expected_cash, counted_cash, expected_digital json (per method), actual_digital json, difference, notes, closed_by, closed_at, reopened_by, reopened_at, reopen_reason, status (open/closed) |
| `accounting_periods` | name, starts_on, ends_on, status (open/closed), closed_by, closed_at, reopen history via activity log |

## M10 Settlements

| Table | Key columns |
|-------|-------------|
| `settlement_plans` | doctor_id, name, effective_from, effective_to, deduct_lab_cost bool, notes, approved_by |
| `settlement_plan_components` | plan_id, type (percent_collection/fixed_per_visit/fixed_per_procedure/fixed_periodic), procedure_id nullable, rate, amount |
| `settlements` | doctor_id, plan_id, period_start, period_end, gross_eligible_collection, computed_amount, adjustments, final_amount, status (draft/reviewed/approved/paid/void), approved_by, payment_voucher_id |
| `settlement_lines` | settlement_id, source (receipt allocation / visit / fixed), reference ids, base_amount, rate, amount |

## M11 Lab

| Table | Key columns |
|-------|-------------|
| `labs` | name, contact, phone, payee_id FK |
| `lab_orders` | order_no UQ, patient_id, doctor_id, visit_id, treatment_id nullable, lab_id, work_type, tooth_numbers, shade, sent_on, due_on, received_on, delivered_on, status (created/sent/received/fitted/remake/cancelled), cost, payment_status, payment_voucher_id |

## M12 Inventory

| Table | Key columns |
|-------|-------------|
| `inventory_items` | sku UQ, name, category, unit, reorder_level, track_expiry bool, is_active |
| `stock_batches` | item_id, batch_no, expiry_date, qty_on_hand |
| `stock_movements` | item_id, batch_id, type (receive/use/adjust/expire/return), qty (+/-), reason, visit_id nullable, purchase_id nullable, moved_by, moved_at |
| `suppliers` | (or reuse `payees` with type=supplier) |
| `purchases` | purchase_no UQ, supplier_id, purchase_date, total, status, payment_voucher_id |
| `purchase_items` | purchase_id, item_id, qty, unit_cost, batch_no, expiry_date |

`qty_on_hand` is cached; source of truth is Σ `stock_movements`.

---

## Relationship summary

```
patients ─┬─< appointments >── doctors ──< doctor_schedules ──< doctor_schedule_blocks
          ├─< visits ─┬─< treatments >── procedures
          │           ├─< prescriptions ─< prescription_items >── medicines
          │           └─< lab_orders >── labs
          ├─< invoices ─< invoice_items
          ├─< receipts ─< receipt_methods
          │      └─< payment_allocations >── invoices
          ├─< refunds
          ├─< patient_alerts / medical_history / tooth_records
          └── opening_balances
payment_vouchers >── expense_categories, payees, (settlements | lab_orders | purchases | refunds)
```
