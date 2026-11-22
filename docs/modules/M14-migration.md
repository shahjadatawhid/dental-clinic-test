# M14 — Data Migration (Spreadsheet Import)

**Phase:** 5 · **Depends on:** M03, M07, M08

## Purpose
Bring existing patient and finance records from the three spreadsheets into the system safely.

## Steps
1. **Copy** the spreadsheets (never work on originals). Restrict sharing on originals.
2. **Map columns** → document `spreadsheet column → table.column` for each sheet (fill table below).
3. **Clean**: trim names, normalise phones, fix dates, flag blanks.
4. **Detect duplicates**: same phone / same legacy ID / similar names → review list for staff.
5. **Import to staging** via artisan commands (`php artisan import:patients file.xlsx --dry-run`):
   - Patients → `patients` with `legacy_code`.
   - Previous dues → `opening_balances` (verified amounts only).
   - Historical receipts/expenses (if wanted) → receipts / payment_vouchers with `legacy_voucher_no`, marked `source = import`.
6. **Validate**: counts per sheet, dues total, income/expense totals per month vs spreadsheet. Produce a reconciliation report.
7. **Staff verify** a sample of records.
8. **Parallel run** 2–4 weeks; compare daily totals.
9. **Final import** at cut-over; spreadsheets kept read-only as archive.

## Column mapping (fill in)
| Sheet | Column | Target | Transform | Notes |
|-------|--------|--------|-----------|-------|
| | | | | |

## Rules
- Dry-run mode prints what would be created and every row error, writes nothing.
- Import is idempotent (re-running doesn't duplicate — match on legacy code).
- Rows with errors go to an error file for manual fix.
