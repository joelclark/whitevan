# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common Commands

### Development
- `composer run dev` — starts PHP server, queue worker, log tail, and Vite dev server concurrently
- `composer run setup` — full first-time setup (dependencies, .env, key, migrations, npm build)

### Testing
- `php artisan test --compact` — run all tests
- `php artisan test --compact --filter=TestName` — run specific test(s)
- `php artisan test --compact tests/Feature/SomeTest.php` — run a specific file
- `npm run build` — always run after changes that touch frontend code, routes, or Fortify features to catch build failures
- `npm run lint` — always run after changes that touch frontend code to catch lint errors

### Linting & Formatting
- `vendor/bin/pint --dirty --format agent` — format modified PHP files (run after any PHP changes)
- `npm run lint` — ESLint with auto-fix
- `npm run format` — Prettier formatting
- `npm run types:check` — TypeScript type checking
- `composer run ci:check` — full CI pipeline (all lints + tests)

### Code Generation
- `php artisan wayfinder:generate` — regenerate TypeScript route/action helpers after route changes

## Architecture

### Stack
Laravel 13 (PHP 8.4) + React 19 via Inertia.js v3, Tailwind CSS v4, TypeScript, Pest v4.

### Multi-Tenancy (Account Scoping)
- Users and Accounts have a many-to-many relationship via the `account_user` pivot table (`AccountUser` pivot model). Users no longer carry `account_id`; membership is tracked exclusively through the pivot.
- `AccountContext` (singleton) holds the current request's account. `AccountContext::resolveForUser()` reads `session('current_account_id')`, validates membership, and falls back to `User::defaultAccount()` (first pivot row by id). `SetAccountContext` middleware calls the resolver for every authenticated request.
- `AccountContext::switchTo()` is the public seam for future account-switching UI — validates membership and persists the new selection to session. No route/controller wired yet.
- `User::accounts()` (BelongsToMany), `User::defaultAccount()`, `User::currentAccount()`, `User::isMemberOf(Account)`. `Account::users()` (BelongsToMany). `Account::owner()` (BelongsTo via `owner_user_id`) is unchanged.
- Models using the `BelongsToAccount` trait automatically scope all queries to the current account and auto-fill `account_id` on creation.
- Registration (`CreateNewUser`) creates both a User and Account in a single transaction, attaching the user via the pivot.
- `UserFactory::forAccount(Account)` state attaches via pivot in `afterCreating`. `AccountFactory::configure()` auto-attaches the owner as a member.

### Sysops (System Operators)
- Sysops are super-admin users with `is_sysop = true` and no account memberships — they operate outside tenant boundaries.
- `is_sysop` is NOT mass-assignable; it can only be set via `forceFill()`, tinker, or direct DB access. No UI exists to grant/revoke sysop status.
- `EnsureSysop` middleware (aliased as `'sysop'`) gates access; sysop routes use `['auth', 'verified', 'sysop']` middleware chain.
- Sysop routes live in `routes/sysops.php`, controllers in `app/Http/Controllers/Sysops/`, pages in `resources/js/pages/sysops/`.
- Frontend: `auth.is_sysop` shared Inertia prop controls sidebar visibility; sysop pages use a `SysopsLayout` wrapper.
- `UserFactory` has a `->sysop()` state. DevSeeder creates a test sysop at `sysop@example.com` / `sysopsecretpass`.

### Sysop Impersonation
- A sysop can step into any account as an admin. State lives in the session key `impersonated_account_id` plus a request-scoped `ImpersonationContext` singleton (mirrors `AccountContext` in shape, lives in `app/Contexts/`).
- `SetAccountContext` middleware reads the session key, re-verifies `isSysop()` from the DB on every request, and sets both `AccountContext` and `ImpersonationContext`. Non-sysops with an injected session key are ignored; stale account ids are cleared.
- **Middleware priority**: `AppServiceProvider::configureMiddlewarePriority()` hoists `SetAccountContext` to run before `Inertia\Middleware` via `addToMiddlewarePriorityBefore`. Inertia's service provider forces its own middleware right after `StartSession`, which would otherwise run the Inertia `share()` callback before the impersonation context is set. Don't remove that hoist.
- **Gate authorization** (`AppServiceProvider::configureAuthorization`): real sysops (not impersonating) bypass every check. Impersonating sysops route through the shared `groupsAllow([SecurityGroup::Admin], $ability)` helper — the same matcher used for real memberships. This is the privilege-boundary invariant: "act as Admin," not "unconditional bypass." If Admin's abilities are ever narrowed, impersonation narrows in lockstep automatically. A behavioral-equivalence test in `tests/Feature/Sysops/ImpersonationTest.php` pins this — don't regress it.
- **EnsureSysop** also 403s when `ImpersonationContext::isImpersonating()` is true, so all `/sysops/*` routes are inaccessible during an impersonation session. The stop endpoint deliberately lives outside the sysop middleware group (`DELETE /impersonate` in `routes/web.php`) so the sysop can always exit.
- **Settings blocking**: the `not-impersonating` middleware alias (`BlockDuringImpersonation`) is applied to `routes/settings.php` and redirects to `/dashboard` with a flash status. Rationale: a real admin of the impersonated account cannot edit the sysop's identity (profile, password, 2FA), and letting the sysop do so during impersonation produces confused audit rows where an identity change carries the impersonated `account_id`. Any new route that operates on the sysop's own identity should also get this middleware.
- **Inertia shared props**: while impersonating, `HandleInertiaRequests::share` swaps `auth.is_sysop → false`, `auth.security_groups → ['admin']`, `auth.account → impersonated account`, and adds `auth.impersonating = { account }`. The real user stays in `auth.user`.
- **Admin controllers resolve `$account` from `AccountContext`**, not from the user's relationships. This is what makes impersonation work across the admin UI — the sysop has no account memberships, so reading from the user would return nothing. Follow this pattern for any new admin-scope controller.
- **Routes**: `POST /sysops/{account}/impersonate` (start, sysop group), `DELETE /impersonate` (stop, `auth` only — controller self-enforces `isSysop()`). Both regenerate the session id.
- **Activity events**: `ActivityEvent::SysopImpersonationStarted` and `SysopImpersonationStopped` mark session boundaries. See the Activity Log section below for the per-action `metadata.impersonated` flag.

### Security Groups
- `SecurityGroup` enum in `app/Enums/` defines roles (currently `Admin`). Each case provides `label()`, `description()`, and `abilities()`.
- Pivot model `SecurityGroupUser` links users to groups **per-account** via `account_id`. The unique constraint is `(account_id, user_id, security_group)` — a user can be Admin in one account and not another. `SecurityGroupUser::create()` always requires `account_id`.
- User helpers: `securityGroupsForAccount(?int $accountId)` returns groups filtered to the given account (empty collection when null). `hasSecurityGroup()` and `isAdmin()` filter by `AccountContext::id()` automatically.
- `Gate::before()` in `AppServiceProvider`: real sysops bypass all checks; members' groups are filtered by `AccountContext::id()` then matched via the `groupsAllow()` helper (wildcard or exact ability). Impersonating sysops are NOT a blanket bypass — they route through the same helper with `[SecurityGroup::Admin]`, so narrowing Admin's abilities narrows impersonation too. See the Sysop Impersonation section.
- `HandleInertiaRequests` shares `auth.security_groups` filtered by the current account context.
- Managed via sysop UI at `/sysops/{account}` — sysops themselves cannot have security group memberships.

### Account Admin
- Account admins (users with the `Admin` security group) can manage users within their own account.
- Admin routes live in `routes/admin.php`, controllers in `app/Http/Controllers/Admin/`, pages in `resources/js/pages/admin/`.
- Routes use `['auth', 'verified', 'can:manage-users']` middleware chain. The `manage-users` ability is implicitly granted to Admin group members via the `*` wildcard in `Gate::before()`.
- Frontend: `auth.security_groups` shared Inertia prop controls sidebar visibility; admin pages use an `AdminLayout` wrapper.
- Features: user listing, security group assignment, user activation/deactivation — all scoped to the current account.
- Defense-in-depth: both `SecurityGroupController` and `UserActivationController` explicitly reject sysop targets, even though sysops have no account memberships and would already 404 on the `isMemberOf()` check.

### User Deactivation
- Sysops and account admins can deactivate/activate users via their respective `UserActivationController`s.
- `deactivated_at` timestamp on `User` model; helper: `isDeactivated()`. Field is NOT mass-assignable — set via `forceFill()`.
- Deactivated users are blocked at login (Fortify's `authenticateUsing` callback in `FortifyServiceProvider`).
- Neither sysops nor admins can deactivate themselves or sysop users.

### Login Tracking
- `last_login_at` timestamp on `User` model, updated by `LogSuccessfulLogin` listener via `forceFill()` (NOT mass-assignable, mirrors `deactivated_at`).
- Use this field for cheap point-in-time queries like "active in the last N days". For historical/time-series questions, query `activity_logs` filtered on `event = 'user.logged_in'`.
- Caveat: the `Login` event fires on remember-me cookie rehydration too, so this reflects "was authenticated this request" rather than "typed a password just now."

### Auth
Fortify handles authentication (login, registration, password reset, email verification, 2FA). Custom actions live in `app/Actions/Fortify/`. Views are rendered via Inertia (configured in `FortifyServiceProvider`). Registration is currently disabled (returns 404) — users are added by other means.

### Routes
- `routes/web.php` — top-level routes, includes `settings.php`, `admin.php`, and `sysops.php`. Also hosts `DELETE /impersonate` (stop impersonation), which deliberately lives outside the `sysop` middleware group.
- `routes/settings.php` — profile, password, 2FA, appearance (auth + verified + `not-impersonating`)
- `routes/admin.php` — account admin routes (auth + verified + can:manage-users)
- `routes/sysops.php` — sysop admin routes (auth + verified + sysop)
- `routes/customers.php` — customer CRUD (auth + verified). Project creation is nested: `POST /customers/{customer}/projects`.
- `routes/projects.php` — project CRUD (auth + verified). Estimate upload is nested: `POST /projects/{project}/estimates`.
- `routes/estimates.php` — estimate edit/update/destroy/retry/pdf/floorplan-page + interview + line-items. No index route — Projects is the top-level workspace (`/projects`). Upload is NOT here — it lives in `projects.php`.
- `routes/quotes.php` — public approval & contract signing (token-gated, no auth) + authenticated send-quote endpoint
- `routes/admin.php` also mounts account contract editor (`/admin/contract`) and deposit overrides (`/admin/deposits`).
- `routes/sysops.php` also mounts system contract templates (`/sysops/contract-templates`) and deposit defaults (`/sysops/deposits`).
- Middleware aliases (in `bootstrap/app.php`): `sysop` → `EnsureSysop`, `not-impersonating` → `BlockDuringImpersonation`.

### Frontend
- Pages: `resources/js/pages/` — Inertia auto-discovers page components
- Layouts: `resources/js/layouts/` — `app-layout.tsx` (main), `auth-layout.tsx` (auth), `settings-layout.tsx`, `admin/layout.tsx`, `sysops-layout.tsx`, `quote-layout.tsx` (public, no auth)
- UI components: `resources/js/components/ui/` — Radix UI primitives with Tailwind
- Shared widgets: `resources/js/components/search-input.tsx` — debounced search box with a clear button. Use this on every index page; do not re-roll the debounce/clear plumbing inline.
- Wayfinder-generated route helpers: `resources/js/actions/` and `resources/js/routes/` (do not edit manually)
- Shared Inertia props (user, account, app name) configured in `HandleInertiaRequests` middleware

### Activity Log
- **Preferred API**: `ActivityLogger::event(ActivityEvent::X, description?, metadata?, account?, user?)` — writes a typed event row. Description defaults to `$event->label()`; pass an override only when interpolated context is useful for the human-facing audit UI (e.g. `"Security group added: admin"`).
- **`ActivityEvent` enum** (`app/Enums/ActivityEvent.php`) is the authoritative registry of every event we record. Adding a new event means adding a case with a `label()`. String values follow `domain.action_past_tense` (e.g. `user.logged_in`, `user.security_group_added`). The `tests/Unit/Enums/ActivityEventTest.php` guardrail enforces both rules.
- **Enum values are persisted storage**: the `event` column on `activity_logs` is cast to `ActivityEvent`, which throws `ValueError` on unknown values. Never rename or remove an existing case value — only add. Renames require a data migration to update existing rows.
- **Ad-hoc / diagnostic logging**: `ActivityLogger::info(...)` / `error(...)` remain for cases with no stable event key. Those rows write `event = null` and are excluded from metric queries that filter on the event column. Prefer `event()` for anything that should ever be counted or filtered.
- **Metric queries** filter on `event`, never `description`. The `(event, created_at)` composite index on `activity_logs` is sized for these queries. Example: "7-day active users" is `where event = 'user.logged_in' and created_at >= now() - 7d` distinct on `user_id`.
- The `ActivityLog` model does NOT use `BelongsToAccount` — sysops see all events cross-tenant.
- **Impersonation attribution is centralized**: `ActivityLogger::record()` automatically merges `metadata.impersonated = true` into every row written while `ImpersonationContext::isImpersonating()` is true (uses `array_merge`, so the context's truth wins over any caller-supplied key). All write paths — `event()`, `info()`, `error()` — inherit this for free. Do NOT re-add manual stamping in controllers; the invariant lives at the logger.
- **ActivityLogger vs ProjectEventLogger**: `ActivityLogger` is the sysop-facing global audit log (loose JSON metadata, cross-tenant). `ProjectEventLogger` writes a narrower, column-structured row into `project_events` for the contractor/customer project timeline, with a `customer_visible` flag derived from `ActivityEvent::isCustomerVisible()`. The two are companions, not substitutes — most project-scoped write paths call both (see `QuoteController::send`, `ContractSigningController`). Both apply the same impersonation-stamping invariant; if a third logger is added, collapse the duplication.
- Enum: `ActivityLogType` (Info, Error) in `app/Enums/`. `event()` always writes Info; if a typed error event is ever needed, add `errorEvent()` then.
- Sysop screen: `/sysops/activity-logs` (currently displays description, not event — event is a backend concern).
- Auth events are wired via listeners in `app/Listeners/`. Successful logins go to the activity log DB. Failed logins and lockouts log to the application log only (no DB write) to avoid database spam from brute force attacks.

#### When to fire activity log events
Any feature that changes user or account state, or represents a security-relevant action, **must** fire an activity log event via `ActivityLogger::event()` with a corresponding `ActivityEvent` case. Add a new enum case if none fits. Examples:
- **Auth**: login, password reset, 2FA enable/disable (all wired). Failed logins and lockouts go to the application log, not the activity log DB.
- **Do NOT activity-log**: failed logins, lockouts — these are high-volume under attack and go to the application log instead
- **Account lifecycle**: sign-up (currently disabled), account creation, account deletion, ownership transfer
- **Permission/security**: role changes, sysop access, authorization failures
- **Do NOT log**: page views, routine reads, search queries, background job progress, or any high-frequency action

### Projects
- A Project is the container between Customer and Estimate. It holds notes, (future) photos/attachments, and (future) change orders — artifacts that accumulate over the life of a job, not a single quote. Relationships: `Customer hasMany Project`, `Project hasMany Estimate`, `Project belongsTo Customer`.
- **Schema.** `projects` table uses `BelongsToAccount` + `SoftDeletes`. Columns: `account_id`, `customer_id` (FK, restrictOnDelete), `name` (required), site-address fields (`site_address_line_1|2`, `site_city`, `site_state`, `site_zip`, all nullable — defaults to the customer's address when blank), `notes` (text), `last_activity_at` (NOT NULL, `DEFAULT CURRENT_TIMESTAMP`), timestamps, soft_deletes. Indexes: `(account_id, customer_id)`, `(account_id, last_activity_at)`.
- **`last_activity_at` is the workspace sort key**, not `updated_at`. It's a denormalized timestamp bumped explicitly by `Project::recordActivity()` (and `Estimate::recordProjectActivity()` as a pass-through) on user/customer-visible events. `forceFill` keeps it out of mass-assignment; a `Project::creating` hook populates it in-memory at insert time because Eloquent doesn't read back server-side defaults. Bump sites: project update; estimate create/update/retry/delete; AI extraction success (`ProcessEstimatePdfJob::persistSuccess`); interview answer save; line-item `unit_price` edit; quote send. Deliberately NOT bumped by background bookkeeping: floorplan render completion, failed jobs, soft-delete cleanup. Customer-side page views are not tracked at all in this phase. `updated_at` is still the Laravel audit column; don't conflate the two.
- **Estimate FK moved.** `estimates.customer_id` is gone; `estimates.project_id` is the FK. `Estimate` retains a `customer()` relationship via `HasOneThrough(Customer, Project)` so `$estimate->customer` and `->load('customer')` keep working for every existing caller. This is the invariant that lets the frontend keep reading `estimate.customer` without change — don't break it without also updating every consumer.
- **Customer.estimates is HasManyThrough** via Project. Preserves `$customer->estimates()->exists()` and `loadCount('estimates')`. The delete guard in `CustomerController::destroy` checks `projects()->exists()` (status flash: `customer-has-projects`) — an empty project still blocks customer deletion because `projects.customer_id` is `restrictOnDelete`.
- **Soft-delete cascade.** `Project::deleting` hook iterates `$project->estimates()->get()->each->delete()` so each Estimate's own deleting hook fires and cleans up its PDF + floorplan PNGs. Never use `$project->estimates()->delete()` (a query-builder delete bypasses model events and file cleanup).
- **Activity events.** `ProjectCreated`, `ProjectUpdated`, `ProjectDeleted`. `EstimateCreated` metadata now carries `project_id` + `customer_id` (derived via `$project->customer_id`); `EstimateUpdated`/`Deleted` carry only `project_id`.
- **Controllers & routes.** `ProjectController` (index at `/projects`, store nested under `/customers/{customer}/projects`, edit/update/destroy at `/projects/{project}`). `EstimateController::store` is bound to `Project $project` and lives at `POST /projects/{project}/estimates`. `EstimateController::edit` eager-loads both `customer` (HasOneThrough) and `project` so the record header can render the customer link and the project breadcrumb from one payload — a regression test in `tests/Feature/Estimates/EstimateEditTest.php` pins this shape. `EstimateController::destroy` redirects to the project edit page (there's no estimates index anymore).
- **Workspace index.** `GET /projects` is the top-level workspace list (the sidebar "Projects" link). `ProjectController::index` eager-loads `customer:id,first_name,last_name,company`, orders by `last_activity_at DESC, id DESC`, and paginates 25/page. Search covers `projects.name`, `projects.site_address_line_1`, `customer.first_name`/`last_name`/`company`, and falls back to `customer.address_line_1` only when the project has no site override — mirroring `resolveProjectAddress()` on the frontend so search hits exactly what the user sees in the project's long title. The Inertia page (`resources/js/pages/projects/index.tsx`) renders three columns: Project Name, Last activity, Status (placeholder em-dash for now — a real project-status concept lands later). There is no `/estimates` index anymore; the `EstimateController::index` method and page were removed.
- **Quote token stays on Estimate.** `Estimate == Quote` invariant is preserved; the `approval_token` on the estimate is what gates the single customer-facing page. A change order (future) will be a new Estimate on the same Project with its own tokens.
- **Frontend entry points.** The customer record header's primary CTA is "New Project" (opens `NewProjectDialog`). The project record header's primary CTA is "New Estimate" (opens `UploadEstimateDialog`, which now takes `project` instead of `customer`). Customer edit has a Projects panel; project edit has an Estimates panel and a `ProjectEventsPanel` showing the timeline.

#### Project Events
- `project_events` captures the per-project timeline (contractor- and customer-facing). Schema: `account_id`, `project_id` (restrictOnDelete — never silently drop history), `estimate_id` (nullable, nullOnDelete), `event` (cast to `ActivityEvent`), `user_id` (nullable), `actor_type` (`ActorType` enum: `contractor`/`customer`/`system`), `customer_visible` bool, `metadata` JSON. `created_at` only — no `updated_at`. Indexes: `(account_id, project_id, created_at)` (contractor feed), `(project_id, customer_visible, created_at)` (deferred customer feed), `(event, created_at)` (cross-project metrics).
- **Write via `ProjectEventLogger::record($project|$estimate, ActivityEvent, user?, metadata?, actorType?)`.** When `subject` is an Estimate, `estimate_id` is populated and the project is derived via `$estimate->project`. `actorType` defaults to `Contractor` if `$user` is set, else `System`; guest routes (approval, signing) must pass `ActorType::Customer` explicitly. `customer_visible` is NEVER caller-supplied — it's computed from `ActivityEvent::isCustomerVisible()` so visibility is a property of the event, not the caller.
- **`ProjectEvent` model uses `BelongsToAccount`** for scoping, but `ProjectEventLogger` sets `account_id` explicitly (not via auto-fill) because guest routes have no `AccountContext`. `estimate()` relationship uses `withTrashed()` so an `EstimateDeleted` row still shows the title/filename after the estimate is soft-deleted.
- **QuoteContractViewed uniqueness**: a partial unique index (`project_events_contract_viewed_unique`) on `(estimate_id)` filtered to `event = 'quote.contract_viewed'` guarantees the customer-first-view event fires at most once per estimate. `ContractSigningController::show` has a fast-path exists check, then catches `UniqueConstraintViolationException` for concurrent-GET races.

### Estimates
- An Estimate is created when a user uploads a floor plan PDF on a **project** (not directly on a customer — see the Projects section above). The PDF is stored on the configured estimate disk (see "Estimate artifact storage" below) under `estimate-pdfs/{ulid}.pdf` and the row goes into `Processing` status. `ProcessEstimatePdfJob` runs the `FloorPlanExtractionAgent` (Laravel AI, OpenAI provider, structured output) which returns rooms with per-room `page` numbers; on success the job rewrites `estimate_rooms` inside a transaction and flips the estimate to `Ready`. Failures flip it to `Failed` and capture the full provider response in `debug_log` for operator triage. `$tries = 1` for both estimate jobs — agent calls and image rendering are not the kind of work a blind retry makes succeed, and the user can resubmit explicitly.
- **Estimate artifact storage.** Both PDFs and rendered floorplan PNGs live on the disk named by `config('estimates.disk')` (env: `ESTIMATE_DISK`, default `local`). This is intentionally a **separate config key from `FILESYSTEM_DISK`** so a misconfigured default disk (e.g. `public`) can never accidentally expose customer files — every read goes through an auth- or token-gated controller. A pre-configured `minio` disk in `config/filesystems.php` is the local stand-in for prod S3 (see `MINIO.md` for the docker-compose snippet); prod uses the standard `s3` disk. `phpunit.xml` pins both `ESTIMATE_DISK` and `FILESYSTEM_DISK` to `local` so the suite never depends on a remote bucket. The `league/flysystem-aws-s3-v3` package is required to enable the `s3`/`minio` disks. Never hard-code `'local'`; always read `config('estimates.disk')`.
- **Remote-disk materialization.** `pdftoppm`, `pdfinfo`, and the AI SDK's `Document::fromPath()` all need a real on-disk file, not a Flysystem path. The `MaterializesEstimatePdf` trait (used by both estimate jobs) returns `[$absolutePath, $cleanup]`: a no-op closure when the disk is `local` (uses `$disk->path()` directly), and a streamed temp file (`stream_copy_to_stream`, never buffered into a PHP string — 25MB uploads would OOM the worker) plus an `unlink` cleanup when the disk is remote. Always invoke the cleanup in a `finally` block. PNGs render into a sibling temp dir and are then uploaded via `$disk->put($relativePath, $stream)`; the renderer treats a `false` return from `put` as a per-page failure (Flysystem adapters with `throw => false` swallow upload errors).
- **Two-phase pipeline, two independent statuses.** After AI inference succeeds, `ProcessEstimatePdfJob::persistSuccess` dispatches `ExtractEstimateFloorplanAssetsJob` via `->afterCommit()` (avoiding the database-queue race where a worker picks up the row before the rooms transaction is visible). The render job has its own status column `floorplan_assets_status` (Pending/Ready/Failed) — render failures do NOT flip the main estimate to Failed, they're a separate failure domain. Frontend polls while either status is in flight.
- **Per-room ↔ per-page is 1:1.** The AI is prompted to return one room per logical floor-plan page, so embedding the page image directly in each room card is the canonical UI. There is no separate gallery component. If that invariant ever changes, revisit `rooms-list.tsx` and the renderer's dedupe-by-page logic in `ExtractEstimateFloorplanAssetsJob::handle`.
- **Renderer requires poppler-utils.** `FloorplanPageRenderer` shells out to `pdftoppm` (PNG render) and `pdfinfo` (page count for clamping AI-supplied page numbers) via the `Process` facade. On Fedora: `sudo dnf install poppler-utils`. The service catches `ProcessStartFailedException` and rethrows with a clear "not installed" message — production deploys must include the package or the floorplan job will surface a Failed status.
- **Per-page render resilience.** The job tolerates partial failure: pages outside `[1, pdfinfo.Pages]` are recorded in `debug_log.floorplan.pages_skipped`, individual `pdftoppm` exceptions go in `pages_failed`, and the job still marks the estimate Ready as long as at least one page rendered. Only when zero pages render does the job flip `floorplan_assets_status` to Failed and log `ActivityEvent::EstimateFloorplanAssetsFailed`. The `failed()` hook guards against double-write the same way `ProcessEstimatePdfJob` does.
- **Asset persistence and cleanup.** Rendered PNGs live at `estimate-floorplan-pages/{estimate_id}/p{page}.png` on the configured estimate disk; metadata (page, width, height, image_path) lives in `estimate_floorplan_pages` keyed by `(estimate_id, page)`. Width/height are captured via `getimagesize()` on the local temp file before upload, and rendered in `<img>` tags to avoid layout shift. The Estimate model's `deleting` hook removes both the PDF and all rendered PNGs on (soft) delete — same disposable-on-delete policy as the source PDF, since there's no estimate restore flow. The retry endpoint resets `floorplan_assets_status` to Pending and wipes stale rows + image files before re-dispatching the AI job, so the UI shows a clean skeleton during the gap.
- **Image serving.** PNGs are streamed via `EstimateController::floorplanPage($estimate, $page)` using `Storage::disk(config('estimates.disk'))->response(...)` — never expose `image_path` directly, always look up via `$estimate->floorplanPages()->where('page', $page)->firstOrFail()`. Route-model binding on `{estimate}` enforces account scoping. Response sets `Cache-Control: max-age=3600, private` so the 2-second poll loop doesn't re-fetch every PNG on each tick (images are immutable per `(estimate, page)` until a re-render wipes the row).
- **Frozen contract:** the `FloorPlanExtractionAgent` response shape (`title`, `total_sqft`, `rooms[].{name,page,sqft,perimeter}`, `errors[]`) is hardcoded in `app/Ai/Agents/FloorPlanExtractionAgent.php`. Adding a field requires code changes on both the schema and `persistSuccess`. The system prompt, however, is sysop-editable via `AiAgentSetting`.
- **Debug log namespacing.** `debug_log` is shared between the AI job and the floorplan job. The AI job writes top-level `request`/`response`/`status`/`exception`. The floorplan job writes everything under a `floorplan` key. The retry endpoint deletes the `floorplan` key but preserves the AI portion. Don't write top-level keys from the floorplan job.

#### Line Items
- **Estimate and quote are the same entity** at different stages of readiness. There is no separate quote table. Line items stay editable until a deposit is paid (future feature). The `quote_status` column (`QuoteStatus` enum: `Sent`) tracks the quote lifecycle independently of `status` (Processing/Ready/Failed). Future progression: Accepted → Deposited.
- **Line items are emitted deterministically** from interview answers on completion. `TradeLineItemEmitter` interface mirrors `TradeInterview` — one implementation per trade, dispatched via `LineItemEmitterDispatcher::for(Estimate)`. The emitter is a pure function: (rooms + answers) → ordered `LineItemDraft[]` DTOs. No AI involved.
- **Persistence & reconciliation**: `estimate_line_items` table keyed by `(estimate_id, key)`. `EstimateLineItem` model does NOT use `BelongsToAccount` — scoped transitively via estimate. `LineItemReconciler` handles all writes — it matches drafts to existing rows by `key`, creates new items, updates quantities on existing ones, and soft-deprecates items no longer emitted (sets `deprecated_at`). If a previously deprecated item's key reappears in a later emission, the reconciler un-deprecates it — preserving its `unit_price`. Reconciliation runs on every answer change while the interview is complete, not just on the first completion transition.
- **Active vs all**: `Estimate::activeLineItems()` filters to `whereNull('deprecated_at')` — used for serialization and the `updateLineItem` guard. `Estimate::lineItems()` returns all rows including deprecated — used by retry (hard-delete) and the reconciler itself.
- **Flooring emitter** (`app/Trades/Flooring/LineItems/FlooringLineItemEmitter.php`) maps each interview answer to zero or more line items. Aggregation rules: install items sum sqft by material, demo items sum sqft by existing floor type (skip `bare`), subfloor items sum sqft by prep type (skip `none`), furniture counts rooms or heavy_count, trim items use linear_feet, service items use counts or flat `1`.
- **Enums**: `LineItemCategory` (Demo, Prep, Install, Trim, Services), `LineItemKind` (Material, Labor), and `LineItemUnit` (Sqft, LinearFeet, Each) in `app/Enums/`.
- **Material/labor split.** Install and trim emissions split into paired material + labor draft pairs keyed `{base}_material` / `{base}_labor` so each side can be priced independently and deposit math can treat them differently. Material draft quantities pass through `materialWithWaste()` — a 10% cut-waste bump that rounds sqft/linear-feet up to the nearest 10 (Each units pass through unchanged). Labor-only categories (demo, prep, services, furniture, quarter-round-reuse, baseboards-remove-reinstall) stay single-row `LineItemKind::Labor`. If you add a new trade emitter, be explicit about which drafts are Material vs Labor — the default column value is `labor`, but relying on the default silently is a pricing bug.
- **Adding a new trade**: implement `TradeLineItemEmitter`, add a `case` in `LineItemEmitterDispatcher`, and create emitter-specific tests. The interface + dispatcher pattern is identical to the interview system.
- **`unit_price` column** exists on `estimate_line_items` (nullable decimal). Pricing UI is live on the estimate edit page; all active items must be priced before a quote can be sent.

#### Quotes
- **Quote = published estimate.** Setting `quote_status = Sent` makes the estimate available to the customer via a public approval link. `QuoteController::send` guards on `status === Ready`, `!isLocked()`, and all active line items having `unit_price` set. Send also emails the customer (if an email is on file) using `QuoteSentToCustomer` / `resources/views/emails/quotes/sent.blade.php`. The email is an **invitation only** — no line-item prices are embedded; the approval page is the single source of truth so a contractor can still edit prices after send without the email going stale.
- **Customer-facing surface is the approval page.** `approval_token` (ULID, unique) is minted on first send and never changes. Public URL: `GET /approve/{approval_token}` — no auth, no signature, no expiry; the 128-bit token is the access control. A sibling `/approve/{token}/sign` page hosts the full contract + signature form.
- **Approval & signing controllers.** `ApprovalFlowController::show` and `ContractSigningController::show/store` both scope via `Estimate::withoutGlobalScope('account')` (no auth context). Token lookup is centralized in `ApprovalFlowController::findEstimateByToken()` which also filters `quote_status = Sent` and bypasses the account scope on every relation touched (`account`, `project`, `customer`).
- **`QuoteSnapshot` is the canonical quote math.** `QuoteSnapshot::build($estimate)` is a pure, in-memory projection: `items[]` (id, kind, label, category, quantity, unit, unit_price, line_total), `material_subtotal`, `labor_subtotal`, `grand_total`, plus per-side deposit percent + amount and `deposit_total`. Both the approval page and the sign page render from this exact shape — do not recompute totals in a controller or view. "Snapshot" here means the **computed view**, nothing is persisted.
- **Render-to-commit change detection via `quote_hash`.** `QuoteSnapshot::hash()` is `sha256(json_encode(snapshot))`. The sign page embeds the hash as a hidden field; `ContractSigningController::store` recomputes the snapshot server-side and `hash_equals()` compares. A mismatch means the quote moved under the customer (price edit, deposit-default change) — the controller logs `ActivityEvent::EstimateQuoteChangeDetectedAtSigning` and redirects back to the approval page with a `quote_changed` flash so the customer re-reviews. Never weaken this check; it's the integrity boundary between what the customer saw and what they signed.
- **Contract signing is single-shot and atomic.** `ContractSigningController::store` uses a conditional UPDATE (`whereNull('contract_signed_at')`) inside a transaction — exactly one concurrent writer claims the signature (affected rows = 1), every other writer sees 0 and 404s. The estimate stores `contract_body_snapshot` (frozen markdown at the moment of signing, resolved via `ContractResolver::bodyForAccount`), `contract_signed_at`, `contract_signed_name`, `contract_signed_ip`, and `contract_signed_user_agent`. The signed body is what renders thereafter — never the live `ContractResolver` output, even if an admin later edits the account contract.
- **Auto-lock on sign is temporarily disabled.** `locked_at` / `Estimate::isLocked()` exist and are honored by `QuoteController::send`, `EstimateInterviewController::store`, and `EstimateController::updateLineItem` (all 409 when locked), and the approval + sign pages render read-only when locked. But `ContractSigningController::store` does not currently set `locked_at` — signing stays fluid for testing. To re-enable, add `'locked_at' => now()` to the conditional UPDATE in `ContractSigningController::store`; all other pieces are already wired.
- **Customer-first-view tracking.** `ContractSigningController::show` records `ActivityEvent::QuoteContractViewed` once per estimate via `ProjectEventLogger` with `ActorType::Customer`. The invariant is enforced by a partial unique index on `project_events`; the controller has a fast-path check + `UniqueConstraintViolationException` catch for races. The approval page itself (`ApprovalFlowController::show`) does NOT record a view event — only opening the contract does, so the semantics match the enum name.
- **Activity events.** `EstimateQuoteSent` (send/re-send), `QuoteContractViewed` (first open of contract, customer-visible), `QuoteContractSigned` (ProjectEvent, customer-visible), `EstimateQuoteAccepted` (ActivityLogger, carries totals), `EstimateQuoteChangeDetectedAtSigning` (ActivityLogger, integrity violation).
- **Frontend.** `resources/js/pages/quotes/approve.tsx` renders the itemized live breakdown (customer-facing columns) and step list (sign, deposit, confirm — deposit/confirm are still "coming soon"). `resources/js/pages/quotes/sign.tsx` shows the full contract body + signature form. Both use `quote-layout.tsx` (minimal public layout — no sidebar, no auth).
- **Internal surface.** The estimate edit page shows an `ApprovalStatusPanel` with the approval link (copy-to-clipboard) and the customer email the quote was delivered to. There is no "View Quote" button — the contractor previews by copying the approval link.

#### Contracts (template → per-account override → signed snapshot)
- Two-tier system mirroring deposits. `contract_templates` holds system defaults keyed by `kind` (currently just `default`); `account_contract_overrides` holds optional per-account bodies (one row per account, `account_id` unique). Sysops edit templates at `/sysops/contract-templates`; account admins edit overrides at `/admin/contract` (revert = delete override row).
- **`ContractResolver::bodyForAccount($account)`** is the single source of truth: override if present, else system default. Used by the admin editor (state display), the approval/signing page (customer display), and `ContractSigningController::store` (source of the snapshot). Both `ContractResolver` and `DepositResolver` use `withoutGlobalScope('account')` so guest routes can resolve without an `AccountContext`.
- **`ContractMarkdown`** renders the body with `html_input => escape` and `allow_unsafe_links => false` — markdown is the canonical storage form; HTML is always computed on read. Safe to render user-editable contract bodies without additional sanitization, but do NOT introduce a raw-HTML code path.
- **Activity events**: `ContractTemplateUpdated`, `AccountContractUpdated`, `AccountContractReverted`.

#### Deposit defaults & overrides
- Two-tier system identical in shape to contracts. `deposit_defaults` has a single `default`-kind row (seeded 100% material / 80% labor); `account_deposit_overrides` has per-account percents (one row, `account_id` unique). Either percent on the override can be null — `DepositResolver::materialPercentFor()` / `laborPercentFor()` fall back to the default **per-side independently**, so a partial override (only material set) still uses the default for labor.
- Sysops edit defaults at `/sysops/deposits`; account admins edit overrides at `/admin/deposits` (revert = delete override row).
- Deposit math lives entirely in `QuoteSnapshot::build` using integer percents — do not scatter deposit calculations elsewhere.
- Activity events: `DepositDefaultsUpdated`, `AccountDepositOverrideUpdated`, `AccountDepositOverrideReverted`.

### Validation Concerns
`PasswordValidationRules` and `ProfileValidationRules` traits in `app/Concerns/` provide reusable validation rule sets shared between Fortify actions and form requests.

### Git Commits
- Message format: `type: short description` (lowercase, no period). Types: `feature`, `fix`, `ops`, `refactor`, `test`, `docs`.  No agent attribution in commit messages.

### Database
SQLite in dev, production is PostgreSQL 18. Seeders must be idempotent (use `updateOrCreate`/`firstOrCreate`) and must never create data with timestamps in the future — clamp or skip any generated timestamp that lands after `now()`. All seed data goes in `DevSeeder`; `DatabaseSeeder` stays empty.  All FKs require indexes.  Never configure cascade-on-delete without discussion.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/ai (AI) - v0
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/nightwatch (NIGHTWATCH) - v1
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `ai-sdk-development` — TRIGGER when working with ai-sdk which is Laravel official first-party AI SDK. Activate when building, editing AI agents, chatbots, text generation, image generation, audio/TTS, transcription/STT, embeddings, RAG, vector stores, reranking, structured output, streaming, conversation memory, tools, queueing, broadcasting, and provider failover across OpenAI, Anthropic, Gemini, Azure, Groq, xAI, DeepSeek, Mistral, Ollama, ElevenLabs, Cohere, Jina, and VoyageAI. Invoke when the user references ai-sdk, the `Laravel\Ai\` namespace, or this project's AI features — not for Prism PHP or other AI packages used directly.
- `fortify-development` — ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `configure-nightwatch` — Configures Laravel Nightwatch data collection, sampling rates, filtering rules, and redaction policies. Use when setting up Nightwatch, managing data volume, protecting sensitive data (PII), or optimizing event collection for production workloads.
- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-react-development` — Develops Inertia.js v3 React client-side applications. Activates when creating React pages, forms, or navigation; using <Link>, <Form>, useForm, useHttp, setLayoutProps, or router; working with deferred props, prefetching, optimistic updates, instant visits, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
