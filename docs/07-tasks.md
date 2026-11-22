# 07 — Task List

Format: `- [ ] **ID** — task · _depends on_`
Status: `[ ]` todo · `[~]` in progress · `[x]` done
Size: S (≤ ½ day) · M (1–2 days) · L (3–5 days)

Work top to bottom. A task is **done** when code + test (where noted 🧪) are merged and it works in the browser.

---

## Phase 0 — Prepare

### P0 Planning & decisions
- [ ] **P0-01** (S) Restrict sharing on all 3 spreadsheets to "Restricted"
- [ ] **P0-02** (M) Meet owner/accountant; answer Q1–Q14 in [03-business-rules.md](03-business-rules.md)
- [ ] **P0-03** (M) Write current workflow notes (booking → check-in → treatment → billing → closing) with reception + one doctor
- [ ] **P0-04** (S) Collect procedure list with approved prices (Q13) and expense categories (Q12)
- [ ] **P0-05** (M) Sketch screens: patient search, register, calendar, queue, profile/timeline, visit, invoice, payment, day close
- [ ] **P0-06** (S) Review sketches with staff; record changes
- [ ] **P0-07** (S) Decide hosting (Q14) — must support PHP 8.3+ and MySQL 8 (stack decided: Laravel + MySQL + Tailwind)

### P0 Environment
- [x] **P0-08** (S) Install PHP 8.3+, Composer, Node, Git, MySQL 8 (Laragon recommended on Windows — bundles MySQL)
- [ ] **P0-09** (S) `git init`, create GitHub private repo, push docs

---

## Phase 1 — First release

### M01 Foundation
- [x] **M01-01** (S) Create Laravel project; configure `.env`, timezone `Asia/Dhaka`, locale · _P0-08_
- [x] **M01-02** (S) Install Tailwind, Livewire, Alpine; base layout (sidebar, topbar) · _M01-01_
- [x] **M01-03** (S) Install Pest, Pint, Larastan; add `composer test` / `lint` scripts · _M01-01_
- [x] **M01-04** (S) Auth scaffold (Breeze Livewire or Filament): login, logout, password reset · _M01-02_ — public registration and self-delete removed
- [x] **M01-05** (M) Install spatie/laravel-permission; seed permissions + 8 role templates from doc 04 · _M01-04_ — `app/Authorization/Permissions.php`, `php artisan app:sync-permissions`
- [x] **M01-06** (M) User management screen (list, create, deactivate, assign roles) · _M01-05_ — `/admin/users`
- [x] **M01-07** (S) Block login for inactive users 🧪 · _M01-06_
- [x] **M01-08** (M) Roles & permissions editor (admin) with audit of changes · _M01-05, M01-10_ — `/admin/roles`; reason required; admin role keeps user/role management
- [x] **M01-09** (S) `settings` table + `Setting` helper + settings screen · _M01-05_ — `app/Settings/Definitions.php`, `setting()` helper, `/admin/settings`, idle sign-out
- [x] **M01-10** (S) Install spatie/laravel-activitylog; `HasAudit` trait (created_by/updated_by + log) · _M01-01_ — `Auditable`, `HasUserstamps`, `Audit::record()`, auth log
- [x] **M01-11** (M) `number_sequences` + `NumberSequenceService` with row lock 🧪 concurrency · _M01-01_ — `NumberSequenceService`, `SequenceSeries` enum, `app:sync-sequences` (daily), multi-process test in `tests/Concurrency`
- [~] **M01-12** (S) Clinic profile screen (name, address, logo) · _M01-09_ — name/address/phone/email/reg no done as settings; logo upload pending
- [x] **M01-13** (M) Audit log viewer with filters · _M01-10_ — `/admin/audit`; clinical logs hidden by `AuditLogAccess`; "History" link on users
- [x] **M01-14** (S) Role-based sidebar menu + 403 page; test forbidden URL access 🧪 · _M01-05_ — `app/Navigation/Menu.php`; 403/404/419 pages; RouteAccessTest guards every route
- [x] **M01-15** (S) Fictional data seeder base (factories) · _M01-01_ — `fake_bd()` (BD names/phones/addresses), DevUsersSeeder, DemoDataSeeder hook, production guard

### M02 Staff & schedules
- [x] **M02-01** (S) Migrations + models: staff, doctors, rooms, chairs · _M01-10_ — Staff, Doctor, Room, Chair + factories; Auditable + HasUserstamps
- [x] **M02-02** (M) Staff list/form; doctor profile fields; link user account · _M02-01_ — `/staff`; SaveStaff action; private signature upload; phone normalised
- [x] **M02-03** (S) Rooms & chairs CRUD · _M02-01_ — `/rooms` single page, inline edit, deactivate only
- [x] **M02-04** (M) Doctor working hours editor (multiple blocks/day, chair, effective dates) · _M02-02_ — versioned `doctor_schedules` + blocks; `/schedules`, `/doctors/{id}/hours`; `$doctor->hoursOn($date)`
- [x] **M02-05** (S) Doctor leave / blocked time; clinic holidays · _M02-02_ — `doctor_time_off`, `clinic_holidays`, `DoctorAvailability::workingPeriods()`; `/holidays`; leave UI on hours page
- [x] **M02-06** (S) Seed fictional doctors, rooms, chairs, hours · _M02-04_ — `StaffSeeder` (4 doctors, 3 staff, 3 rooms/4 chairs, routines, demo leave, fixed BD holidays)

### M03 Patients (core)
- [x] **M03-01** (S) Patients migration + model + factory (patient_code, legacy_code, indexes) · _M01-11_ — + registered_on, age_years/age_recorded_on, merged_into_id; patient audit log needs patients.view
- [x] **M03-02** (M) `RegisterPatient` action (auto code, phone normalise) 🧪 · _M03-01_ — `RegisterPatient` + shared `PatientRules` (clean-up, validation)
- [ ] **M03-03** (M) Duplicate detection (phone / name+DOB) + warning on form 🧪 · _M03-02_
- [ ] **M03-04** (M) Patient search page (name/phone/code/legacy) + global top-bar search · _M03-01_
- [ ] **M03-05** (M) Register / edit patient form · _M03-02_
- [ ] **M03-06** (M) Patient profile page shell with tabs (Overview, Appointments, Visits, Billing) · _M03-05_
- [ ] **M03-07** (S) Seed 2,000+ fictional patients; check search speed · _M03-04_

### M04 Appointments & queue
- [ ] **M04-01** (S) appointment_types + appointments migrations, `AppointmentStatus` enum with allowed transitions 🧪 · _M02-01, M03-01_
- [ ] **M04-02** (S) MySQL overlap guard: indexes + `lockForUpdate()` on doctor/chair row inside booking transaction · _M04-01_
- [ ] **M04-03** (L) `AvailabilityService` (hours − leave − holidays − bookings, by duration/chair) 🧪 · _M02-05, M04-01_ — build on `DoctorAvailability::workingPeriods()`; also add the "appointments affected by new leave/holiday" warning (M02 doc)
- [ ] **M04-04** (M) `BookAppointment` / `RescheduleAppointment` / `CancelAppointment` actions 🧪 double-booking · _M04-03_
- [ ] **M04-05** (L) Calendar view (day/week, by doctor/chair) · _M04-04_
- [ ] **M04-06** (M) Booking modal: doctor → date → free slots → patient find/quick-register → confirm; alternative doctors when none free · _M04-04, M03-04_
- [ ] **M04-07** (M) Today's reception queue: check-in (queue no), walk-in, no-show · _M04-04_
- [ ] **M04-08** (S) Doctor "My queue"; start treatment → status In treatment · _M04-07_
- [ ] **M04-09** (S) Waitlist list + add/convert to booking · _M04-04_
- [ ] **M04-10** (S) Appointments tab on patient profile · _M04-04, M03-06_

### M05 Clinical (basic)
- [ ] **M05-01** (S) procedures + price history migrations; catalog CRUD; seed from P0-04 · _M01-10_
- [ ] **M05-02** (S) visits + treatments migrations/models · _M04-01, M05-01_
- [ ] **M05-03** (S) Auto-open visit when appointment → In treatment 🧪 · _M05-02, M04-08_
- [ ] **M05-04** (M) Visit screen: complaint, examination, diagnosis, notes · _M05-03_
- [ ] **M05-05** (M) Add performed treatments (procedure, tooth, surface, price) · _M05-04_
- [ ] **M05-06** (S) Close visit → appointment Completed → "ready for billing" flag · _M05-05_
- [ ] **M05-07** (S) Clinical notes edit policy (author/permission; post-close edit audited) 🧪 · _M05-04_

### M07 Billing (basic)
- [ ] **M07-01** (M) invoices, invoice_items, receipts, receipt_methods, payment_allocations migrations + enums (PaymentMethod) · _M05-02, M01-11_
- [ ] **M07-02** (M) `CreateInvoice` from visit (pre-fill treatments) or standalone; issue → number 🧪 · _M07-01_
- [ ] **M07-03** (M) Invoice create/edit/view screen · _M07-02_
- [ ] **M07-04** (L) `RecordPayment` action: RV number, split methods, allocation (chosen or oldest-first) 🧪 all acceptance cases · _M07-02_
- [ ] **M07-05** (M) Take-payment modal with live balance · _M07-04_
- [ ] **M07-06** (S) `BalanceService` (patient balance, invoice cached totals) 🧪 · _M07-04_
- [ ] **M07-07** (M) Void invoice / void receipt with reason (keeps number) 🧪 · _M07-04_
- [ ] **M07-08** (M) PDF/print: invoice and money receipt (clinic header) · _M07-03, M01-12_
- [ ] **M07-09** (S) Billing tab on patient profile (invoices, receipts, balance) · _M07-06, M03-06_
- [ ] **M07-10** (S) "Ready for billing" list for reception · _M05-06, M07-03_

### M09 Daily closing
- [ ] **M09-01** (S) daily_closings migration/model · _M07-01_
- [ ] **M09-02** (M) Day summary calculation (by method, expected cash) 🧪 · _M09-01_
- [ ] **M09-03** (M) Closing screen: enter counted amounts, difference, note, close · _M09-02_
- [ ] **M09-04** (M) `ClosedPeriodGuard` used in payment/void actions; reopen with reason 🧪 · _M09-03_
- [ ] **M09-05** (S) Print day-end summary · _M09-03_

### M13 Reports (phase 1)
- [ ] **M13-01** (M) Role-aware dashboard (today's appts, queue, collection) · _M04-07, M07-04_
- [ ] **M13-02** (M) Daily collection report + Excel export · _M07-04_
- [ ] **M13-03** (S) Appointments report (by status/doctor) · _M04-04_

### Phase 1 wrap-up
- [ ] **P1-01** (M) End-to-end test: book → check-in → visit → invoice → split payment → close day 🧪
- [ ] **P1-02** (S) Deploy to staging with fictional data · _P1-01_
- [ ] **P1-03** (M) Demo to reception + doctor; collect feedback; create fix tasks

---

## Phase 2 — Clinical history & prescribing

### M03 Patients (clinical)
- [ ] **M03-08** (M) patient_alerts + medical_history: CRUD with source, date, author · _M03-06_
- [ ] **M03-09** (S) Alerts banner on profile, queue, visit · _M03-08_
- [ ] **M03-10** (S) Consents (type, date, scanned doc) · _M05-14_
- [ ] **M03-11** (M) Merge duplicate patients (manager) 🧪 · _M03-03_

### M05 Clinical (full)
- [ ] **M05-08** (L) Patient timeline (visits, treatments, Rx, payments, lab, alerts; author + date) · _M05-05_
- [ ] **M05-09** (L) Dental chart component (FDI adult/child, conditions, surfaces) · _M05-02_
- [ ] **M05-10** (S) Per-tooth history popup · _M05-09_
- [ ] **M05-11** (M) Treatment plans: items, estimate, accept, progress · _M05-05_
- [ ] **M05-12** (S) Link performed treatment to plan item · _M05-11_
- [ ] **M05-13** (M) Visit workspace layout (alerts/history | notes/treatments | chart) · _M05-08, M05-09_
- [ ] **M05-14** (M) Attachments via medialibrary (private storage, image/PDF preview) · _M05-02_

### M06 Prescriptions
- [ ] **M06-01** (S) medicines migration; catalog CRUD; CSV import · _M01-10_
- [ ] **M06-02** (S) prescriptions + items migrations (snapshot json) · _M05-02, M06-01_
- [ ] **M06-03** (L) Prescription panel in visit: search medicine, add lines, directions, advice, follow-up · _M06-02, M05-13_
- [ ] **M06-04** (M) Cancel / replace prescription keeping history 🧪 · _M06-03_
- [ ] **M06-05** (M) Print layout + clinic print setting + `wants_print` flag + print log 🧪 · _M06-03_
- [ ] **M06-06** (M) Expiring share link (hashed token, public minimal page, share log) 🧪 · _M06-03_
- [ ] **M06-07** (S) Doctor Rx templates/favourites (optional) · _M06-03_
- [ ] **M06-08** (S) Prescriptions tab on patient profile · _M06-03_

### M13 / wrap-up
- [ ] **M13-04** (S) Follow-ups due / missed visits report · _M06-03, M04-04_
- [ ] **P2-01** (M) Demo to doctors; feedback fixes

---

## Phase 3 — Finance controls

### M07 Billing (advanced)
- [ ] **M07-11** (M) Discounts per item/invoice with reason; approval above limit 🧪 · _M07-02_
- [ ] **M07-12** (M) Deposit / advance as patient credit; allocate later 🧪 · _M07-04_
- [ ] **M07-13** (M) Refund linked to receipt/invoice (+ draft PV) 🧪 · _M07-04, M08-03_
- [ ] **M07-14** (S) Write-off with approval 🧪 · _M07-06_
- [ ] **M07-15** (S) Opening balances table + inclusion in balance 🧪 · _M07-06_
- [ ] **M07-16** (M) Dues list with aging · _M07-15_

### M08 Expenses
- [ ] **M08-01** (S) expense_categories, payees migrations + CRUD; seed categories · _M01-10_
- [ ] **M08-02** (M) payment_vouchers migration; PV number via sequence · _M08-01, M01-11_
- [ ] **M08-03** (M) PV form + workflow (draft → approved → paid → void) 🧪 · _M08-02_
- [ ] **M08-04** (S) PV print · _M08-03_
- [ ] **M08-05** (S) Expense list with filters · _M08-03_
- [ ] **M08-06** (S) Include cash PVs in daily closing expected cash 🧪 · _M08-03, M09-02_

### M09 Accounting periods
- [ ] **M09-06** (M) accounting_periods: define, close, reopen with reason 🧪 · _M09-04_
- [ ] **M09-07** (S) Extend `ClosedPeriodGuard` to periods 🧪 · _M09-06_

### M10 Settlements (only after Q4–Q6 confirmed)
- [ ] **M10-01** (M) Plans + components migrations; plan form with effective dates · _M02-02_
- [ ] **M10-02** (L) `SettlementCalculator` 🧪 with clinic's hand-calculated examples · _M10-01, M07-13_
- [ ] **M10-03** (M) Generate statement screen; lines; adjustments · _M10-02_
- [ ] **M10-04** (M) Review → approve → paid (creates PV) → void 🧪 · _M10-03, M08-03_
- [ ] **M10-05** (S) Statement print; doctor "My settlements" · _M10-04_

### M13 Finance reports
- [ ] **M13-05** (M) Income by doctor / procedure / method · _M07-04_
- [ ] **M13-06** (M) Expenses by category/payee; income vs expense summary · _M08-03_
- [ ] **M13-07** (S) Discounts/refunds/write-offs/voids report · _M07-14_
- [ ] **M13-08** (S) Settlement summary report · _M10-04_
- [ ] **M13-09** (S) Reconciliation test: reports == daily closing totals 🧪 · _M13-06_
- [ ] **P3-01** (M) Accountant checks a sample month; fix differences

---

## Phase 4 — Lab & inventory

### M11 Lab
- [ ] **M11-01** (S) labs, lab_orders migrations; lab CRUD · _M08-01_
- [ ] **M11-02** (M) Lab order form from visit/treatment; status flow · _M11-01, M05-05_
- [ ] **M11-03** (S) Overdue list + dashboard alert · _M11-02_
- [ ] **M11-04** (M) Lab bill payment → PV; payment status · _M11-02, M08-03_
- [ ] **M11-05** (S) Lab coordinator limited patient view (policy) 🧪 · _M11-02_
- [ ] **M11-06** (S) Lab tab on patient profile · _M11-02_

### M12 Inventory
- [ ] **M12-01** (S) inventory_items, batches, movements migrations; item CRUD · _M01-10_
- [ ] **M12-02** (M) `StockMovement` actions (receive/use/adjust/expire), no negative stock 🧪 · _M12-01_
- [ ] **M12-03** (M) Purchases: form, receive into batches, PV · _M12-02, M08-03_
- [ ] **M12-04** (S) Stock count / adjust screen · _M12-02_
- [ ] **M12-05** (S) Reorder & expiry alerts (scheduled job + dashboard) · _M12-02_
- [ ] **M12-06** (S) Optional: supplies used on visit · _M12-02, M05-05_

### M13
- [ ] **M13-10** (S) Lab bills due / turnaround report · _M11-04_
- [ ] **M13-11** (S) Stock levels / expiry / usage report · _M12-02_
- [ ] **P4-01** (S) Demo to manager

---

## Phase 5 — Migration, pilot, launch

### M14 Migration
- [ ] **M14-01** (M) Copy spreadsheets; fill column mapping table in M14 doc
- [ ] **M14-02** (M) `import:patients` command with dry-run, idempotent, error file 🧪 · _M03-02_
- [ ] **M14-03** (M) Duplicate review report for staff · _M14-02_
- [ ] **M14-04** (M) `import:opening-balances` 🧪 · _M07-15_
- [ ] **M14-05** (M) `import:receipts` / `import:expenses` with legacy voucher no (if wanted) · _M07-04, M08-03_
- [ ] **M14-06** (M) Reconciliation report (counts, dues, monthly totals vs sheet) · _M14-05_
- [ ] **M14-07** (S) Staff verify sample records

### M15 Operations
- [ ] **M15-01** (M) Provision server/hosting; HTTPS; queue + scheduler · _P0-07_
- [ ] **M15-02** (S) Deploy script; staging + production environments · _M15-01_
- [ ] **M15-03** (M) spatie/laravel-backup to off-site storage + failure alerts · _M15-01_
- [ ] **M15-04** (S) Write restore procedure; perform restore drill · _M15-03_
- [ ] **M15-05** (M) Security checklist (M15 doc) incl. 2FA for privileged roles, login rate limit · _M15-02_
- [ ] **M15-06** (S) Export/print audit logging check · _M01-10_
- [ ] **M15-07** (M) Training: reception, doctors, accountant (short guides) 
- [ ] **M15-08** (L) Parallel run 2–4 weeks; compare daily totals · _M14-06_
- [ ] **M15-09** (S) Arrange qualified privacy / security review
- [ ] **M15-10** (S) Go-live checklist sign-off; spreadsheets set read-only archive

---

## Later / backlog
- [ ] **BL-01** Patient self-booking portal (shows only free slots)
- [ ] **BL-02** SMS / WhatsApp appointment reminders
- [ ] **BL-03** Bangla UI translation
- [ ] **BL-04** Prescription share via SMS/WhatsApp/email
- [ ] **BL-05** Multi-branch support
