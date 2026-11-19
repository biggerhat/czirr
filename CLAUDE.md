# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Family management app (calendar, chores, recipes, meal plans, budgeting, lists, contacts) built with Laravel 12, Inertia.js, and Vue 3 + TypeScript. UI uses shadcn-vue (reka-ui v2.8.0). Auth via Laravel Fortify with Spatie Laravel Permission for role-based access.

## Commands

```bash
# Development (starts server, queue, logs, and Vite concurrently)
composer dev

# Run tests
./vendor/bin/pest
./vendor/bin/pest tests/Feature/CalendarTest.php          # single file
./vendor/bin/pest --filter=test_name                       # single test

# Linting
composer lint              # PHP (Pint; `fully_qualified_strict_types` disabled in pint.json to avoid mass churn)
composer analyse           # PHPStan level 5 (existing issues in phpstan-baseline.neon)
npm run lint               # ESLint (JS/TS/Vue)
npm run format             # Prettier

# Build
npm run build              # Production build (includes PWA assets)

# Migrations
php artisan migrate
```

## Architecture

### Multi-Family Ownership Model

All resources are scoped to a "family owner" user. The `User` model has key methods:
- `familyOwnerId()` — returns the owning user's ID (self for owners, linked user's owner for members)
- `familyOwner()` — returns the owning User instance
- `linkedFamilyMember()` — returns the FamilyMember record linking this user into another family

**Always scope resource queries with `$user->familyOwnerId()`**, never assume `$user->id` owns resources. When creating resources, use `$user->familyOwner()->chores()->create(...)`.

### Inertia Data Flow

Controllers return `Inertia::render('PageName', [...props])`. The `HandleInertiaRequests` middleware shares global props: `auth.user`, `permissions` (array of permission names), `familyRole`, `userRole`, `sidebarOpen`.

Vue pages live in `resources/js/pages/` and receive props from their controller. No separate API routes for main flows.

### Permission System

Uses Spatie Laravel Permission. Format: `resource.action` (e.g., `chores.create`, `budgeting.view`).
- Routes: `->middleware('permission:chores.create')`
- Controllers: `$user->can('chores.edit')`
- Frontend: permissions array is shared via Inertia props

The `family` middleware alias (`EnsureFamilyMember`) ensures the user belongs to a family before accessing protected routes.

### Notifications & Scheduling

- Scheduler (`routes/console.php`): `digest:send` and `recap:send` run **hourly** and send to users whose *local* hour matches (per-user `timezone` + `notification_preferences` JSON on `users`; read with `$user->notificationPreference($key)`, defaults in `User::DEFAULT_NOTIFICATION_PREFERENCES`).
- Notifications live in `app/Notifications/` (`DailyDigest`, `WeeklyRecap`, `PushAlert`). Web push uses `laravel-notification-channels/webpush` (VAPID keys in `.env`; service worker handler in `public/push-sw.js`, loaded via Workbox `importScripts`).
- Production needs: real `MAIL_*`, a queue worker, and the `schedule:run` cron. `Notification::fake()` ignores notifications whose `via()` is empty — give test users a push subscription.
- Calendar feed: `users.calendar_token` → public `/calendar/feed/{token}.ics` (`CalendarFeedService`). Bill/income events are hidden from users without `budgeting.view`.

### Visibility (lists, cookbooks)

Use the `HasFamilyVisibility` trait (`Model::visibleTo($user)` scope, `$model->isVisibleTo($user)`) — don't hand-roll visibility queries.

### Rewards & My Day

Spendable balance = all-time chore + bonus points − pending/approved redemptions (`RewardService`); the scoreboard is unaffected. Rewards reuse `chores.view` (redeem) / `chores.edit` (manage + approve).

### Recipe import

`RecipeImporter` fetches user-supplied URLs — keep its SSRF guards (public IPs only, pinned resolve, manual redirects) intact.

### Chores: approval & limits

- `ChoreCompletion` has a global scope `approved` — **pending completions are invisible to every points/streak/digest query by default**. Use `withoutGlobalScope('approved')` only where pending ones must be seen (the approval queue, My Day, the toggle itself).
- A chore with `requires_approval` makes kids' completions `pending`; users with `chores.edit` approve/reject via `PATCH chore-completions/{id}` and are auto-approved themselves.
- Completions are bounded: no dates beyond today+1 and nothing older than 7 days; users without `chores.edit` can only complete their own assignments. Points are spendable (rewards), so keep these guards.

### Family invites

`FamilyInvite` tokens are stored **hashed** (`token_hash`); the plain token is only returned once at creation. Guests landing on `/invite/{token}` get `url.intended` set so Fortify returns them there after login/register. Keep `accepted_at`/`accepted_by` in `$fillable` — omitting them silently breaks single-use.

`REGISTRATION_OPEN=false` makes sign-ups invite-only (`CreateNewUser::ensureRegistrationAllowed`); the first account is always allowed.

### Family board

`BoardPost`/`BoardController`: viewing and posting reuse `lists.view`/`lists.create`; **pinning and deleting others' posts needs `family.edit`** — kids hold `lists.edit`/`lists.delete`, so don't use those for moderation.

### Budget insights & CSV import

`BudgetInsightsService` builds the six-month trend and category breakdown from `BudgetService::getMonthlyOverview` (so numbers match the page) and is sent as a **deferred** Inertia prop (`<Deferred data="insights">`). `ExpenseCsvImporter` detects delimiter/headers; if the file has any negative amounts, negatives are spending and positives are skipped. Expenses must be created via `$owner->expenses()->create()` — `user_id` isn't fillable.

### Pantry

`PantryItem` staples are skipped by `MealPlanController::generateGroceryList`. Matching is **exact on `PantryItem::normalize()`** (case, plurals, parentheticals) and deliberately not fuzzy — a wrong skip means a missing ingredient at the store. Skipped names are flashed to the list page via `session('status')`.

### Timezones

The app timezone is UTC. For "today"/"this week" use `$user->localNow()`, never `Carbon::now()`/`today()` (the user's timezone is auto-detected from the browser on first load). Don't change the PHP default timezone per request — it would corrupt stored timestamps.

### Cross-family safety

Any `exists:` validation rule on a family-owned table must be scoped: `Rule::exists('table', 'id')->where('user_id', $ownerId)` (shared rows with `user_id IS NULL`, e.g. cuisines/tags/event types, use `whereNull('user_id')->orWhere(...)`). Admin members can only be modified by users with `roles.manage`. Owners can't delete their account while other members are linked.

### Security headers & rate limiting

`SecurityHeaders` middleware adds baseline headers (no CSP yet — needs browser testing). The authenticated route group uses the `family` rate limiter (300/min per user).

### Frontend Structure

- Pages: `resources/js/pages/{Feature}/` — Inertia page components
- UI components: `resources/js/components/ui/` — shadcn-vue primitives
- Feature components: `resources/js/components/{feature}/` — feature-specific
- Types: `resources/js/types/` — TypeScript interfaces
- Composables: `resources/js/composables/`
- Route helpers: `resources/js/wayfinder/` — generated by `@laravel/vite-plugin-wayfinder`

### Service Layer

Business logic for complex features lives in `app/Services/` (e.g., `ChoreScoreService` for gamification scoring, `BudgetService`, `RecurringEventService`).

## Key Gotchas

**reka-ui Checkbox**: Uses `modelValue`, NOT `checked`. Using `:checked` silently fails — the prop falls through as an HTML attribute. Correct: `v-model="val"` or `:model-value="val"` + `@update:model-value="handler"`.

**Pre-existing TS error** (not introduced by us):
- `TwoFactorSetupModal.vue(271)`: Property 'code' does not exist on type 'string'

## Code Style

- PHP: Laravel Pint (laravel preset), enforced in CI
- JS/TS/Vue: ESLint (Vue 3 essential + TypeScript) + Prettier (single quotes, 4-space indent, 80-char width)
- Import sorting enforced: builtin → external → internal → parent → sibling → index
- Type-only imports must use `import type` syntax
