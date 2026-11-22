# M02 — Staff, Doctors, Rooms & Schedules

**Phase:** 1 · **Depends on:** M01 · **Tables:** staff, doctors, rooms, chairs, doctor_schedules, doctor_schedule_blocks, doctor_time_off, clinic_holidays

## Purpose
Who works, where, and when — the input for appointment availability.

## Features
- Staff profiles (doctor & non-doctor), link to user account.
- Doctor details: registration no, specialty, qualifications, signature image (for prescriptions), calendar color, default slot length.
- Rooms and dental chairs.
- Weekly working hours per doctor (multiple blocks per day, e.g. 10–1 and 5–9), optionally tied to a chair, with effective dates.
- Leave / blocked time / clinic holidays.

## Business rules
- Working-hour changes have `effective_from` so past schedules stay correct.
- Adding leave over existing appointments shows a warning listing affected appointments (they must be rescheduled manually). **Pending M04** — appointments do not exist yet; add this check to `AddTimeOff` / the hours page and to `AddClinicHolidays` when M04-04 is built.
- Availability for a date = `DoctorAvailability::workingPeriods()` (routine − holiday − leave − blocked time). M04-03 builds on it and subtracts existing bookings.

## Screens
Staff list/form · Doctor profile (tabs: info, working hours, leave) · Rooms & chairs · Holidays

## Acceptance criteria
- Doctor with hours Sat–Thu 17:00–21:00 and leave on a date → no availability that date (verified via M04 AvailabilityService test).
