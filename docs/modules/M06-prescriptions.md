# M06 — Medicine Catalog & Prescriptions

**Phase:** 2 · **Depends on:** M05 · **Tables:** medicines, prescriptions, prescription_items, prescription_shares

## Purpose
Help the doctor find and record medicines; save every prescription to the patient's history; print or share on request.

## Features
- Medicine catalog: generic, brand, strength, form, route, manufacturer, active flag. Maintained by authorised staff. CSV import.
- Prescription entry inside visit: search medicine → add line → doctor enters dose, frequency (e.g. 1+0+1), duration, quantity, instructions (Bangla/English free text).
- Advice & follow-up date.
- Templates/favourites per doctor (*nice to have*): reuse a saved set of lines, still reviewed.
- Correct / cancel / replace: original stays with status `replaced`/`cancelled` and link to new one.
- Print: only if clinic setting enabled **and** `wants_print` checked. Print layout: clinic header, doctor name/reg no/signature, patient name/code/age/sex, date, Rx lines, advice, follow-up.
- Share: generate expiring signed link (default 7 days), log share; later SMS/WhatsApp.

## Business rules
- No dose calculation or auto-selection by the system.
- Item stores a snapshot of medicine name/strength so later catalog edits don't change old prescriptions.
- Prescriptions are never hard-deleted.
- Printing/sharing events logged (who, when).

## Screens
Medicine catalog · Prescription panel (in visit) · Print preview · Share link page (public, minimal, no other patient data)

## Acceptance criteria
- Printing disabled in settings → print button hidden and print route returns 403.
- Replacing a prescription shows both in history with clear status.
- Expired share link shows "link expired".
