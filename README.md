# whitevan.app

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

## Release Workflow

Two long-lived branches: `dev` (integration) and `master` (production).

- **Feature PRs** land on `dev` via **squash merge** (one commit per feature, clean history on `dev`).
- **Releases** promote `dev` → `master` via **fast-forward** so `master` stays a strict ancestor of `dev`:
  ```bash
  git checkout master
  git merge --ff-only origin/dev
  git push origin master
  ```
  Or on GitHub, open a `dev` → `master` PR and use **Rebase and merge** (never squash — squashing breaks the ancestry and forces a history rewrite next time).

## More

See `CLAUDE.md` for architecture notes, conventions, and subsystem details (multi-tenancy, sysops, security groups, activity log, etc.).
