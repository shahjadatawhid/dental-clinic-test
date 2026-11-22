# M01 — Foundation: Auth, Roles, Settings, Audit

**Phase:** 1 · **Depends on:** — · **Tables:** users, roles/permissions, settings, clinic_profile, number_sequences, activity_log

## Purpose
Base everything else stands on: login, role-based access, clinic settings, number sequences, audit trail, layout.

## Features
- Login / logout / password reset; individual accounts only.
- User management (admin): create, deactivate (never delete), assign roles.
- Role & permission management with seeded templates ([04-roles-permissions.md](../04-roles-permissions.md)).
- Clinic profile (name, address, phone, logo) — used on all printouts.
- Settings screen: printing on/off, discount approval limit, voucher prefixes & yearly reset, session timeout, timezone.
- `NumberSequenceService` for patient code, invoice, RV, PV, lab, purchase numbers.
- Audit log: model changes (old/new), who, when, reason; audit viewer with filters.
- App layout: sidebar by role, top bar with global patient search, flash messages.

## Business rules
- Deactivated users can't log in; their past records still show their name.
- Role/permission changes are audited.
- Number sequence increments under row lock; never reused.

## Screens
Login · Users list/form · Roles & permissions · Clinic profile · Settings · Audit log

## Acceptance criteria
- A receptionist cannot open admin screens by typing the URL (403).
- Two concurrent `next('RV')` calls never return the same number (test).
- Changing a setting creates an audit entry.
