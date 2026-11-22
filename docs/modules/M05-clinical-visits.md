# M05 — Clinical Visits & Dental Chart

**Phase:** 1 (visit notes, procedures, treatments) / 2 (timeline, chart, plans, files) · **Depends on:** M03, M04 · **Tables:** visits, procedures, procedure_price_history, treatments, treatment_plans, treatment_plan_items, tooth_records, media

## Purpose
What the doctor examined, found, planned, and did — with who/when on every entry.

## Features
### Phase 1
- Visit screen (opened from queue): chief complaint, examination, diagnosis, notes.
- Procedure catalog (code, name, category, price, tooth-specific flag, settlement-eligible flag) with price history.
- Add performed treatments (procedure, tooth, surface, price) → feed the invoice (M07).
- Close visit → "Send to billing".

### Phase 2
- Patient timeline: dated list of visits, diagnoses, treatments, prescriptions, lab, payments, alerts — each with author.
- Dental chart (FDI numbering, adult + child): click tooth → set condition/surface; history per tooth.
- Treatment plans: proposed items with estimate, accept/reject, track progress across visits.
- Attachments: X-rays, photos, documents on patient/visit (image preview, PDF).

## Business rules
- Only the visit's doctor (or permitted role) edits clinical notes; edits after close are audited with reason.
- Treatment price defaults from catalog; doctor/manager may override (audited).
- System doesn't suggest diagnoses or treatments.

## Screens
Visit workspace (left: alerts + history; centre: notes/treatments; right: chart) · Procedure catalog · Treatment plan · Files gallery

## Acceptance criteria
- Doctor completes a visit with 2 treatments; reception sees them pre-filled on a new invoice.
- Timeline shows items in date order with author name.
