# Juniper

## Setup

```bash
composer run setup
```

Installs dependencies, copies `.env`, generates a key, runs migrations, and builds frontend assets.

## Development

```bash
composer run dev
```

Runs the PHP server, queue worker, log tail (Pail), and Vite dev server concurrently.

## Testing & CI

```bash
php artisan test --compact   # run tests
composer run ci:check        # full CI: lint, format, types, tests
```

## Branches

```bash
php artisan git:new-branch
```

Starts a new branch off the latest `dev`. Rejects a dirty working tree, fetches `origin/dev`, fast-forwards local `dev` (aborts on divergence rather than merging), then prompts for a prefix (with examples) and a kebab-case slug validated against the branch convention. Upstream is set the first time you run `git:push`.

```bash
php artisan git:push
```

Runs `ci:check` against the current branch and, if green, pushes it to origin. Refuses to run on `master`/`main`/`dev` (prompts for a new branch name), rejects a dirty working tree, and enforces the `<prefix>/<kebab-name>` branch convention (`feature`, `fix`, `ops`, `refactor`, `test`, `docs`, `chore`).

## More

See `CLAUDE.md` for architecture notes, conventions, and subsystem details (multi-tenancy, sysops, security groups, activity log, etc.).
