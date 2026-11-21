# Family Hub

A family management app: calendar, chores & rewards, recipes, meal plans, shopping lists, budgeting, contacts and games. Laravel 12 · Inertia v2 · Vue 3 + TypeScript · shadcn-vue · installable PWA.

## Local development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed      # roles/permissions + sample data
composer dev                    # server, queue worker, logs and Vite together
```

The **first account registered becomes the admin** and owns the family. Everyone else joins through an invite link (Family → *Invite someone*), or an admin can link an existing account.

```bash
./vendor/bin/pest          # tests (SQLite in-memory)
composer lint              # Pint
composer analyse           # PHPStan level 5 (legacy findings in phpstan-baseline.neon)
npm run lint && npm run build
```

## Going to production — checklist

**Environment**

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your.domain` (the calendar feed link is built from it).
- `SESSION_SECURE_COOKIE=true` (sessions are already encrypted by default in `.env.example`).
- `REGISTRATION_OPEN=false` once your family has joined, so sign-ups are invite-only.
- Real `MAIL_*` settings. The example uses `MAIL_MAILER=log`, which means **no email is actually sent**.
- `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` for push notifications. Generate once with `php artisan webpush:vapid` and **never regenerate** — existing subscriptions stop working if the keys change.

**Processes** (nothing is delivered without these)

- A queue worker: `php artisan queue:work` (use Supervisor/systemd). Notifications are queued.
- The scheduler, every minute: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`. It drives the daily digest and Sunday recap.

**Deploy steps**

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

**HTTPS is required** for push notifications and for installing the PWA. On iPhone the app must be added to the Home Screen first.

**Backups.** There is no built-in backup. Back up the database (it holds everything) and `.env`. Family owners can also download a JSON export from *Settings → Profile*.

**Not configured yet.** There is no Content-Security-Policy (the app uses an inline theme script, a font CDN and arbitrary recipe image URLs, so one needs tuning in a real browser). Baseline security headers and per-user rate limiting are in place.

## Architecture notes

See [CLAUDE.md](CLAUDE.md) for conventions: the multi-family ownership model, permission naming, notification scheduling, chore approval, invites, and the rules around the recipe importer's URL fetching.
