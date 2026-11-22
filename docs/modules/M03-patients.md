# M03 — Patients

**Phase:** 1 (core) / 2 (alerts, history, consent) · **Depends on:** M01 · **Tables:** patients, patient_alerts, patient_medical_history, patient_consents

## Purpose
Register, find, and maintain patient records; the patient profile is the hub that links to everything else.

## Features
- Register patient → auto `patient_code` (`FD-MM-YY-NNNNNN`, e.g. FD-11-26-000001); optional `legacy_code` for old IDs like FD-01-23-0208.
- Fast search: name, phone, patient code, legacy code (one search box; also in top bar).
- One phone can belong to several patients (family); each still gets their own code. Registration shows possible duplicates (`PossibleDuplicates::check`): same phone + same name, similar name + age, family on the number. Never blocks.
- Edit demographics; deactivate; merge duplicates (manager, audited; merged record points to survivor; codes never reused).
- Patient profile page with tabs: Overview · Timeline · Appointments · Visits · Dental chart · Prescriptions · Billing · Lab · Files.
- Alerts banner (allergies, conditions) on profile and visit screens, each with date + who recorded + source. *(Phase 2)*
- Medical history and consent records. *(Phase 2)*

## Business rules
- Age can be entered when DOB unknown; store `age_years` + date recorded.
- Phone stored normalised (`01XXXXXXXXX`).
- Receptionists see alerts (for safety) but not detailed clinical notes.

## Screens
Patient search/list · Register/edit form · Patient profile (tabbed) · Merge screen

## Acceptance criteria
- Searching "0171" or partial name returns matches within 1s on 20k fictional patients.
- Registering a child on a parent's phone lists the family on that number and allows continue; the child gets their own code and own history.
- Same phone + same name is shown first as "probably the same person".
- Searching by phone shows every patient on that number; the profile header always shows code + name + age + gender so staff confirm the right person.
- Merging moves appointments/visits/invoices to the survivor and logs it.
