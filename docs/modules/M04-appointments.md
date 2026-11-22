# M04 — Appointments & Reception Queue

**Phase:** 1 · **Depends on:** M02, M03 · **Tables:** appointment_types, appointments, waitlist_entries

## Purpose
Book, reschedule, cancel; check patients in; run the day's queue.

## Features
- Appointment types with default duration (consultation 15m, scaling 30m, RCT 60m…).
- `AvailabilityService`: free slots = working hours − time off − holidays − existing bookings, by type duration and chair.
- Booking flow: choose doctor (or "any") → date → free slot → find/register patient (or quick name+phone for request) → confirm.
- If preferred doctor has no slot: show other doctors' slots as options.
- Calendar views: day/week, by doctor or by chair.
- Reschedule (drag or form), cancel with reason, mark no-show.
- Waitlist.
- Status flow: Requested → Confirmed → Arrived → In treatment → Completed; Cancelled, No-show, Waitlist.
- Reception queue (today): arrived patients in order with queue no, waiting time, doctor; doctor's "My queue" view.
- Walk-in: create appointment + check-in in one step.
- Reminders: *later* (channel decision pending).

## Business rules
- No overlap for same doctor or same chair (MySQL: row lock on doctor/chair + overlap query inside the booking transaction).
- Status transitions validated by enum (e.g. can't go Completed → Arrived).
- "In treatment" automatically opens a visit (M05).

## Screens
Calendar · Book/edit appointment modal · Today's queue (reception) · My queue (doctor) · Waitlist

## Acceptance criteria
- Two receptionists booking the same slot at the same time → one succeeds, one gets a clear error.
- Check-in assigns the next queue number for the day.
