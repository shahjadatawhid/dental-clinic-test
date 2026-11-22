# M08 — Expenses & Payment Vouchers (PV)

**Phase:** 3 · **Depends on:** M01, M07 · **Tables:** expense_categories, payees, payment_vouchers

## Purpose
Record every taka paid out with its own voucher series.

## Features
- Expense categories (hierarchical; confirm list — Q12): rent, utilities, salary, lab, supplies, maintenance, doctor settlement, refund, misc.
- Payees/suppliers list.
- Create Payment Voucher: date, payee, category, amount, method, reference, description, attachment (bill photo).
- Workflow: draft → approved → paid; void with reason.
- PVs automatically created (as draft) from: doctor settlement payout (M10), lab bill (M11), purchase (M12), refund (M07).
- PV print.
- Expense list with filters (date, category, payee, method).

## Business rules
- PV number assigned when approved/paid (decide: at creation vs. approval — default at creation, voided numbers kept).
- Cash PVs reduce expected cash in daily closing.
- Closed day/period rules apply as in M09.

## Screens
Expense list · PV form · PV view/print · Categories · Payees

## Acceptance criteria
- Cash PV of 500 on a day reduces that day's expected cash by 500 in closing.
