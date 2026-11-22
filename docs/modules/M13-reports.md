# M13 — Reports & Dashboard

**Phase:** grows with each phase · **Depends on:** the modules it reports on

## Dashboard (role-aware)
- Reception: today's appointments, queue, today's collection.
- Doctor: my queue today, my upcoming appointments.
- Manager/owner: today & month collection, dues total, no-shows, lab overdue, stock alerts.

## Reports
| Report | Phase | Filters |
|--------|-------|---------|
| Daily collection (by method, by receptionist) | 1 | date |
| Appointments (booked/completed/cancelled/no-show) | 1 | date range, doctor |
| New vs returning patients | 1 | date range |
| Income by doctor / procedure / payment method | 3 | period |
| Expenses by category / payee | 3 | period |
| Income vs expense summary (daily/monthly/custom) | 3 | period |
| Patient dues & aging | 3 | as of date |
| Discounts, refunds, write-offs, voids | 3 | period, user |
| Doctor settlement summary | 3 | period |
| Missed visits / follow-ups due | 2 | date range |
| Lab bills due, lab turnaround | 4 | period, lab |
| Stock levels, expiry, usage | 4 | item, date |
| Audit report | 1 | user, model, date |

## Rules
- Every report: on-screen table + export to Excel/PDF (export permission + audit log).
- Report queries read only non-void records unless "include void" chosen.
- Finance report numbers must reconcile with daily closing totals (test).
