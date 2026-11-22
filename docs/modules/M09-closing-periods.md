# M09 — Daily Closing & Accounting Periods

**Phase:** 1 (daily closing) / 3 (accounting periods) · **Depends on:** M07, M08 · **Tables:** daily_closings, accounting_periods

## Purpose
Make sure recorded money matches money actually received, and lock past records.

## Features
### Daily closing (Phase 1)
- Day summary: receipts by method, refunds, cash PVs, expected cash in hand, expected per digital method.
- Cashier enters counted cash and confirmed digital totals → difference shown; note required if difference ≠ 0.
- Close day → that date's receipts/PVs locked for routine edits.
- Manager reopens with reason (audited).
- Print day-end summary.

### Accounting periods (Phase 3)
- Admin defines periods (default monthly; cutoff configurable — Q7).
- Accountant closes period; closed period blocks edits/voids dated in it.
- Reopen (privileged) or post correction in the current period with reason.

## Business rules
- A `ClosedPeriodGuard` checked in every money action: if date is in a closed day/period and user lacks override permission → reject.
- Can't close a period while any day in it is still open (warning list).

## Screens
Daily closing · Closing history · Accounting periods

## Acceptance criteria
- After closing today, receptionist can't void a receipt dated today; manager can after reopening.
