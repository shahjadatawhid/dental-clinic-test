# M07 — Billing, Receipts & Payments

**Phase:** 1 (invoice, RV, payment, split) / 3 (refund, write-off, opening balance, deposit, discount approval) · **Depends on:** M01, M03, M05 · **Tables:** invoices, invoice_items, receipts, receipt_methods, payment_allocations, refunds, write_offs, opening_balances

## Purpose
Show clearly what was billed, what was paid, and what remains due — with a reliable trail.

## Features
### Phase 1
- Create invoice from visit (treatments pre-filled) or standalone; add/remove items before issuing.
- Issue invoice → number assigned, items locked.
- Record payment → **Receipt Voucher (RV)**: amount, one or more methods (cash/bKash/Nagad/card/bank + reference), allocation to invoices (default: oldest unpaid first).
- Print/PDF: invoice, money receipt.
- Patient billing tab: invoices, receipts, running balance.
- Void receipt / invoice with reason (permission-based); voided number stays.

### Phase 3
- Item and invoice discounts with reason; above limit needs approval.
- Deposit/advance (receipt with no allocation → patient credit; allocate later).
- Refund linked to receipt + invoice (creates PV when cash leaves — M08).
- Write-off with approval.
- Opening balance (from migration).
- Dues list: patients with outstanding balance, aging (0–30, 31–60, 60+ days).

## Business rules
- Charges, payments, refunds, write-offs are separate records; nothing edited in place after issue.
- Allocation total ≤ receipt amount; allocation to an invoice ≤ its balance.
- Every money action in one DB transaction; invoice cached totals recalculated.
- Can't record or void payments for a closed day/period without the reopen permission (M09).
- `invoice_items.doctor_id` kept for settlements.

## Screens
Invoice create/edit · Invoice view/print · Take payment modal · Receipt view/print · Patient billing tab · Dues list · Refund / write-off forms

## Acceptance criteria (tests required)
- Invoice 3,000; pay 1,000 cash + 500 bKash → balance 1,500, one RV, two method lines.
- Payment 2,000 with no invoice chosen and two unpaid invoices (1,500 older, 1,000 newer) → 1,500 + 500 allocated.
- Voiding a receipt restores invoice balances and keeps the RV number as void.
