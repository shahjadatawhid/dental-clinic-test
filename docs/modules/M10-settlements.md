# M10 — Doctor Settlements

**Phase:** 3 · **Depends on:** M02, M05, M07, M08, M09 · **Tables:** settlement_plans, settlement_plan_components, settlements, settlement_lines

> ⚠️ Build only after Q4, Q5, Q6 in [03-business-rules.md](../03-business-rules.md) are confirmed. Use agreed hand-calculated examples as test cases.

## Purpose
Calculate what each doctor is owed per period, with a clear, reviewable statement.

## Features
- Settlement plan per doctor with effective dates; components:
  - `percent_collection` — % of collected amount on eligible items (optionally per procedure)
  - `fixed_per_visit` / `fixed_per_procedure`
  - `fixed_periodic` — salary/retainer per period
- Flag `deduct_lab_cost` (default off).
- Generate statement for doctor + period → lines showing source (receipt allocation → invoice item), base, rate, amount.
- Manual adjustments with reason.
- Workflow: draft → reviewed → approved → paid (creates PV) → void with reason.
- Doctor can view own statements (if clinic allows).

## Calculation (default rule)
For each payment allocation dated in the period to an invoice item where `doctor_id` = doctor and procedure is settlement-eligible:
`base = allocated amount (pro-rata to item) − refunds on that item in period`
`amount = base × rate` (rate effective on the **treatment date**, per procedure override if any).
Plus fixed components. Minus lab cost only if plan says so.

## Business rules
- Rate changes never alter already-approved statements.
- Statement period can't overlap an existing non-void statement for the same doctor.

## Screens
Plans list/form · Generate statement · Statement view/print · Doctor "My settlements"

## Acceptance criteria
- Clinic's sample month reproduces the hand-calculated figure exactly (feature test with fixture data).
