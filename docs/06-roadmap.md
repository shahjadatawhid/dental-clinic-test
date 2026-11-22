# 06 — Development Roadmap

Each phase ends with a **demo to clinic staff using fictional data**. Don't start the next phase until the milestone is accepted.

## Phase 0 — Prepare (before coding)
- Confirm open questions Q1–Q14 in [03-business-rules.md](03-business-rules.md) (at least the ones blocking Phase 1: Q1, Q2, Q9, Q10, Q13).
- Document current workflow (booking → check-in → treatment → billing → closing) with reception & a doctor.
- Sketch screens (paper or Figma): patient search, registration, calendar, queue, patient profile/timeline, visit, invoice, receipt, daily summary.
- Restrict spreadsheet sharing.

**Milestone:** staff agree on the v1 workflow and screen sketches.

## Phase 1 — First release: a patient can go through the clinic
Modules: M01, M02, M03, M04, M05 (basic visit notes + procedure catalog), M07 (invoice, RV, payment), M09 (daily closing only), M13 (daily report).

**Milestone:** reception books → checks in → doctor writes visit note & treatments → reception creates invoice, takes split payment, prints receipt → cashier closes the day, totals match.

## Phase 2 — Clinical history & prescribing
Modules: M05 (alerts, medical history, timeline, dental chart, treatment plans, attachments), M06.

**Milestone:** a doctor opens a returning patient, reviews dated history & alerts, records treatment on the chart, creates a saved prescription, prints (when enabled) or shares a link.

## Phase 3 — Finance controls
Modules: M07 (refunds, write-offs, opening balances, deposits, discount approval), M08, M09 (accounting periods), M10, M13 (finance reports).

**Milestone:** accountant checks sample month reports and one doctor settlement against agreed hand-calculated examples.

## Phase 4 — Lab & inventory
Modules: M11, M12, M13 (lab/stock reports).

**Milestone:** manager tracks a lab case end-to-end and sees stock + expiry alerts.

## Phase 5 — Migration, pilot, launch
Modules: M14, M15.

**Milestone:** reviewed spreadsheet copy imported; patient counts and dues totals match; 2–4 week parallel run; restore drill passed; go-live.

## Rough timeline (one part-time developer, learning as they go)

| Phase | Estimate |
|-------|----------|
| 0 | 2–3 weeks |
| 1 | 8–10 weeks |
| 2 | 5–6 weeks |
| 3 | 5–6 weeks |
| 4 | 3–4 weeks |
| 5 | 4–6 weeks (incl. parallel run) |

Estimates are guesses; re-estimate at the end of each phase.
