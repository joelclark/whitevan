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
- `Account` model owns `User`s. Each user belongs to one account.
- `AccountContext` (singleton) holds the current request's account, set by `SetAccountContext` middleware.
- Models using the `BelongsToAccount` trait automatically scope all queries to the current account and auto-fill `account_id` on creation.
- Registration (`CreateNewUser`) creates both a User and Account in a single transaction.

### Sysops (System Operators)
- Sysops are super-admin users with `is_sysop = true` and `account_id = null` — they operate outside tenant boundaries.
- `is_sysop` is NOT mass-assignable; it can only be set via `forceFill()`, tinker, or direct DB access. No UI exists to grant/revoke sysop status.
- `EnsureSysop` middleware (aliased as `'sysop'`) gates access; sysop routes use `['auth', 'verified', 'sysop']` middleware chain.
- Sysop routes live in `routes/sysops.php`, controllers in `app/Http/Controllers/Sysops/`, pages in `resources/js/pages/sysops/`.
- Frontend: `auth.is_sysop` shared Inertia prop controls sidebar visibility; sysop pages use a `SysopsLayout` wrapper.
- `UserFactory` has a `->sysop()` state. DevSeeder creates a test sysop at `sysop@example.com` / `sysopsecretpass`.

### Security Groups
- `SecurityGroup` enum in `app/Enums/` defines roles (currently `Admin`). Each case provides `label()`, `description()`, and `abilities()`.
- Pivot model `SecurityGroupUser` links users to groups. User helpers: `hasSecurityGroup()`, `isAdmin()`.
- `Gate::before()` in `AppServiceProvider`: sysops bypass all checks; `Admin` group members get all abilities (`*`).
- Managed via sysop UI at `/sysops/{account}` — sysops themselves cannot have security group memberships.

### Account Admin
- Account admins (users with the `Admin` security group) can manage users within their own account.
- Admin routes live in `routes/admin.php`, controllers in `app/Http/Controllers/Admin/`, pages in `resources/js/pages/admin/`.
- Routes use `['auth', 'verified', 'can:manage-users']` middleware chain. The `manage-users` ability is implicitly granted to Admin group members via the `*` wildcard in `Gate::before()`.
- Frontend: `auth.security_groups` shared Inertia prop controls sidebar visibility; admin pages use an `AdminLayout` wrapper.
- Features: user listing, security group assignment, user activation/deactivation — all scoped to the current account.
- Defense-in-depth: both `SecurityGroupController` and `UserActivationController` explicitly reject sysop targets, even though sysops have `account_id = null` and would already 404 on the account scope check.

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
- `routes/web.php` — top-level routes, includes `settings.php`, `admin.php`, and `sysops.php`
- `routes/settings.php` — profile, password, 2FA, appearance (auth + verified)
- `routes/admin.php` — account admin routes (auth + verified + can:manage-users)
- `routes/sysops.php` — sysop admin routes (auth + verified + sysop)

### Frontend
- Pages: `resources/js/pages/` — Inertia auto-discovers page components
- Layouts: `resources/js/layouts/` — `app-layout.tsx` (main), `auth-layout.tsx` (auth), `settings-layout.tsx`, `admin/layout.tsx`, `sysops-layout.tsx`
- UI components: `resources/js/components/ui/` — Radix UI primitives with Tailwind
- Wayfinder-generated route helpers: `resources/js/actions/` and `resources/js/routes/` (do not edit manually)
- Shared Inertia props (user, account, app name) configured in `HandleInertiaRequests` middleware

### Activity Log
- **Preferred API**: `ActivityLogger::event(ActivityEvent::X, description?, metadata?, account?, user?)` — writes a typed event row. Description defaults to `$event->label()`; pass an override only when interpolated context is useful for the human-facing audit UI (e.g. `"Security group added: admin"`).
- **`ActivityEvent` enum** (`app/Enums/ActivityEvent.php`) is the authoritative registry of every event we record. Adding a new event means adding a case with a `label()`. String values follow `domain.action_past_tense` (e.g. `user.logged_in`, `user.security_group_added`). The `tests/Unit/Enums/ActivityEventTest.php` guardrail enforces both rules.
- **Enum values are persisted storage**: the `event` column on `activity_logs` is cast to `ActivityEvent`, which throws `ValueError` on unknown values. Never rename or remove an existing case value — only add. Renames require a data migration to update existing rows.
- **Ad-hoc / diagnostic logging**: `ActivityLogger::info(...)` / `error(...)` remain for cases with no stable event key. Those rows write `event = null` and are excluded from metric queries that filter on the event column. Prefer `event()` for anything that should ever be counted or filtered.
- **Metric queries** filter on `event`, never `description`. The `(event, created_at)` composite index on `activity_logs` is sized for these queries. Example: "7-day active users" is `where event = 'user.logged_in' and created_at >= now() - 7d` distinct on `user_id`.
- The `ActivityLog` model does NOT use `BelongsToAccount` — sysops see all events cross-tenant.
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

### Validation Concerns
`PasswordValidationRules` and `ProfileValidationRules` traits in `app/Concerns/` provide reusable validation rule sets shared between Fortify actions and form requests.

### Git Commits
- Message format: `type: short description` (lowercase, no period). Types: `feature`, `fix`, `ops`, `refactor`, `test`, `docs`.

### Database
SQLite in dev, production is PostgreSQL 18. Seeders must be idempotent (use `updateOrCreate`/`firstOrCreate`). All seed data goes in `DevSeeder`; `DatabaseSeeder` stays empty.  All FKs require indexes.  Never configure cascade-on-delete without discussion.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
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
- Do not claim co-authorship in commit messages.

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

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

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
