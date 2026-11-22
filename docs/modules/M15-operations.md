# M15 — Operations: Backup, Deployment, Security

**Phase:** 5 (but set up staging + backups earlier) · **Depends on:** all

## Hosting
- Decide (Q14): VPS (e.g. DigitalOcean/Hetzner/local BD provider) vs managed Laravel hosting (Forge/Ploi + VPS, Laravel Cloud). Check data location and terms.
- Requirements: PHP 8.3+, MySQL 8.0+, HTTPS, queue worker, scheduler (cron).

## Deployment
- Git-based deploy; `.env` never committed.
- Steps: pull → `composer install --no-dev` → `php artisan migrate --force` → `config/route/view:cache` → restart queue.
- Staging deploy first, then production.

## Backups
- `spatie/laravel-backup`: DB daily (and before each deploy), uploaded files daily, to off-server storage (S3-compatible / Google Drive).
- Retention: 7 daily, 4 weekly, 12 monthly.
- Backup failure notification (email).
- **Restore drill** documented and performed before go-live, then every quarter.

## Security checklist
- [ ] HTTPS only, HSTS
- [ ] Strong password policy; optional 2FA for admin/manager/accountant
- [ ] Idle session timeout
- [ ] Rate-limit login
- [ ] Policies on every route/action (tests for forbidden access)
- [ ] Uploaded files stored privately, served through authorised controller
- [ ] Share links: random token, hashed in DB, expiry
- [ ] `APP_DEBUG=false` in production
- [ ] Dependencies updated (`composer audit`)
- [ ] Export & print actions logged
- [ ] Qualified local privacy / record-keeping review

## Go-live checklist
- [ ] All users created with correct roles; no shared accounts
- [ ] Settings reviewed (printing, sequences, periods, discount limit)
- [ ] Data imported and reconciled (M14)
- [ ] Staff trained (reception, doctors, accountant)
- [ ] Restore drill passed
- [ ] Support/maintenance plan written (who fixes what, response time)
