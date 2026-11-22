# M11 — Lab Orders

**Phase:** 4 · **Depends on:** M03, M05, M08 · **Tables:** labs, lab_orders

## Purpose
Track lab work (crowns, dentures, aligners…) from order to fitting, and what the clinic owes the lab.

## Features
- Labs list (linked to payee).
- Lab order: patient, doctor, visit/treatment, lab, work type, teeth, shade, sent date, due date, cost, notes, attachments.
- Status: created → sent → received → fitted; remake; cancelled.
- Overdue list (due date passed, not received).
- Lab bills: mark paid → creates PV (M08); payment status unpaid/partial/paid.
- Lab coordinator view shows minimum patient identity only.

## Screens
Lab orders board/list · Lab order form · Lab bills due

## Acceptance criteria
- Order past due date appears in overdue list and dashboard alert.
