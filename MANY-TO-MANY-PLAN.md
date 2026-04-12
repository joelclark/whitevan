# Many-to-many users ↔ accounts (session-backed current account)



## Context



Today the users ↔ accounts relation is modeled as 1:n at both the DB and model layer (`users.account_id`, `User::account()` as `BelongsTo`). We want to rewrite this as many-to-many so future flexibility — multi-account users, account switching — is a UX change rather than a rewrite.



The UX stays unchanged: a user still sees "the account." But the DB, models, **and request-scoping mechanism** should look as if we designed m:n from day one. Per the follow-up: stash "current account id" in the session now, so the security model and tests cover the realistic switching path from the start — when we later add a UI for switching, nothing changes below the controller layer.



Pre-prod app → rewrite the original migrations in place and reset data.



## Shape of the change



```

BEFORE                              AFTER

──────                              ─────

users                               users

 ├ id                                ├ id

 ├ account_id ───┐                   └ (no account_id)

 └ ...           │

                 ▼                   account_user (new pivot)

accounts                             ├ id

 ├ id                                ├ account_id ───► accounts.id

 ├ owner_user_id                     ├ user_id    ───► users.id

 └ ...                               ├ created_at

                                     └ UNIQUE(account_id, user_id)



User::account() BelongsTo       →   User::accounts()        BelongsToMany

                                    User::defaultAccount()  ?Account   (first pivot row)

                                    User::currentAccount()  ?Account   (session-backed)

                                    User::isMemberOf(Account): bool



Account::users() HasMany        →   Account::users() BelongsToMany

```



### Request flow for "current account"



```

HTTP request ──► StartSession ──► auth ──► SetAccountContext ──► controller

                                               │

                                               ▼

                 ┌─────────────────────────────────────────────────────────┐

                 │ AccountContext::resolveForUser($user):                   │

                 │  1. read session('current_account_id')                   │

                 │  2. if set + user->isMemberOf($account) → use it         │

                 │  3. else → $user->defaultAccount(), write back to session│

                 │  4. AccountContext::set($account)                        │

                 └─────────────────────────────────────────────────────────┘

                                               │

                                               ▼

                                controller / Inertia share()

                                uses AccountContext::get()

                                or $user->currentAccount()

```



`AccountContext` remains a request singleton holding a single Account. It is the one source of truth for "which account is this request operating under"; `BelongsToAccount` trait and all resource scoping stay unchanged on top of it.



## Files to modify



### Schema (rewrite in place; data reset OK)



- `database/migrations/2026_04_01_193834_add_account_id_to_users_table.php` — rename to `2026_04_01_193834_create_account_user_table.php` and replace its body. Schema:

  - `id`

  - `foreignId('account_id')->constrained()->restrictOnDelete()`

  - `foreignId('user_id')->constrained()->restrictOnDelete()`

  - `timestamp('created_at')->useCurrent()` (no `updated_at` — pivot rows are immutable)

  - `unique(['account_id', 'user_id'])`

  - `index('user_id')` (the unique covers `account_id`-first lookups but not `user_id`-first)

  - Per CLAUDE.md: do not use `cascadeOnDelete` without explicit discussion; `restrictOnDelete` matches the existing style for `accounts.owner_user_id`. Detach explicitly when we later add user/account deletion flows.

- `database/migrations/2026_04_02_184539_add_is_sysop_to_users_table.php` — change `->after('account_id')` to `->after('remember_token')`.

- `database/migrations/2026_04_01_193833_create_accounts_table.php` — unchanged.

- `database/migrations/2026_04_03_163200_create_activity_logs_table.php` — unchanged (activity logs still belong to one account).



Reset with `./db_reset` (runs `migrate:fresh --seed`).



### Models



- `app/Models/User.php`

  - Remove `account(): BelongsTo`.

  - Add `accounts(): BelongsToMany` → `belongsToMany(Account::class)->using(AccountUser::class)`.

  - Add `defaultAccount(): ?Account` — `$this->accounts()->orderBy('account_user.id')->first()`. Stable fallback when the session has no selection. Kept as a method (not an accessor) so callers understand it hits the DB.

  - Add `currentAccount(): ?Account` — delegates to `app(AccountContext::class)->resolveForUser($this)`. This is the "what account is this user acting under right now" question; outside of a request context it still works via `defaultAccount()` because the resolver handles the null-session case.

  - Add `isMemberOf(Account $account): bool` — `$this->accounts()->whereKey($account->getKey())->exists()`.

  - `ownedAccount()` unchanged.

- `app/Models/Account.php` — `users()` becomes `belongsToMany(User::class)`. `owner()` unchanged.

- `app/Models/AccountUser.php` **(new)** — pivot model extending `Illuminate\Database\Eloquent\Relations\Pivot`, `$table = 'account_user'`, `UPDATED_AT = null`, `$incrementing = true`. Exists so `->using()` has a home for future pivot-level logic.



### AccountContext (the resolver)



`app/Contexts/AccountContext.php` — extend with the resolver:



```php

public function resolveForUser(User $user): ?Account

{

    $sessionId = session('current_account_id');



    if ($sessionId !== null) {

        $account = $user->accounts()->whereKey($sessionId)->first();

        if ($account !== null) {

            $this->set($account);

            return $account;

        }

        // Stale session value — user was removed from the account, or it was deleted.

        session()->forget('current_account_id');

    }



    $default = $user->defaultAccount();



    if ($default !== null) {

        session(['current_account_id' => $default->id]);

    }



    $this->set($default);

    return $default;

}



public function switchTo(User $user, Account $account): Account

{

    abort_unless($user->isMemberOf($account), 403);

    session(['current_account_id' => $account->id]);

    $this->set($account);

    return $account;

}

```



`switchTo()` isn't reachable from any UI yet, but it's the public seam the future switcher will use and it gives tests a clean way to assert the security model (a user cannot switch into an account they don't belong to). No route/controller is added — we can wire those up when the UX actually exists.



Session access uses the `session()` helper; the middleware runs inside the `web` group after `StartSession`, so the session is always available in the request path. For CLI/seeder contexts, `session()` is a no-op (the facade has a null driver under the console kernel without web state) — the resolver then falls through to `defaultAccount()` without persisting, which is the desired behavior.



### Middleware



- `app/Http/Middleware/SetAccountContext.php` — replace the body:

  ```php

  if ($user = $request->user()) {

      $this->accountContext->resolveForUser($user);

  }

  ```

  All the conditional `$user?->account` handling is gone — the resolver owns that logic.

- `app/Http/Middleware/HandleInertiaRequests.php` — `'account' => $request->user()?->account` becomes `'account' => app(AccountContext::class)->get()`. By the time `share()` runs, `SetAccountContext` has already resolved and set the context (middleware wraps the controller). Inertia prop stays named `account` — frontend types unchanged.



### Controllers



- `app/Http/Controllers/Admin/UserController.php`

  - `$account = $request->user()->account;` → `$account = app(AccountContext::class)->get();`

  - `$account->users()->select('id', 'account_id', 'name', ...)` → `select('users.id', 'users.name', 'users.email', 'users.deactivated_at', 'users.created_at')` (qualified for the belongsToMany join; drop `account_id`).

- `app/Http/Controllers/Admin/SecurityGroupController.php`

  - Same context swap.

  - `abort_if($user->account_id !== $account->id, 404)` → `abort_if(! $user->isMemberOf($account), 404)`.

- `app/Http/Controllers/Admin/UserActivationController.php` — same two changes.

- `app/Http/Controllers/Sysops/AccountController.php::show()` — eager-load closure for `'users'`: qualify columns (`users.id`, `users.name`, …), drop `account_id`, order by `users.name`.

- `app/Http/Controllers/Sysops/SecurityGroupController.php` and `.../UserActivationController.php` — `isMemberOf` swap.

- `app/Http/Controllers/Sysops/ActivityLogController.php` — unchanged.



### Listeners / Fortify actions



These run in a request context with AccountContext already resolved, so use `AccountContext::get()` — it's the account the user is acting under, which is exactly what the audit log wants:



- `app/Actions/Fortify/CreateNewUser.php` — inside the transaction, after `Account::create(...)`, replace `$user->forceFill(['account_id' => $account->id])->save();` with `$account->users()->attach($user);`.

- `app/Actions/Fortify/ResetUserPassword.php` — `account: $user->account` → `account: app(AccountContext::class)->resolveForUser($user)`. The reset flow doesn't have an established session yet (password reset link), so `resolveForUser` is the right entry point: it will fall back to the user's default account.

- `app/Listeners/LogSuccessfulLogin.php` — same: `account: app(AccountContext::class)->resolveForUser($user)`. On fresh login there's no prior session selection, so the resolver writes the default to session — which is exactly what we want for "the account you logged into."

- `app/Listeners/LogTwoFactorEnabled.php` / `LogTwoFactorDisabled.php` — these run inside a normal authenticated request; use `app(AccountContext::class)->get()` directly (middleware has already resolved).



### Settings / profile



- `app/Http/Requests/Settings/ProfileDeleteRequest.php` — unchanged (`ownedAccount()` driven by `accounts.owner_user_id`).



### Factories



- `database/factories/UserFactory.php`

  - Drop `'account_id' => null` from `definition()` and `sysop()`.

  - Add state `forAccount(Account $account): static` that attaches via pivot in `afterCreating`:

    ```php

    public function forAccount(Account $account): static

    {

        return $this->afterCreating(fn (User $user) => $account->users()->attach($user));

    }

    ```

- `database/factories/AccountFactory.php`

  - `configure()` afterCreating: replace the `forceFill` line with `$account->users()->attach($account->owner_user_id);`. Owners are auto-attached as members.

- `database/factories/ActivityLogFactory.php` — unchanged.



### Seeder



`database/seeders/DevSeeder.php`:

- All `$user->forceFill(['account_id' => $account->id])->save()` / `$member->forceFill(...)` lines → `$account->users()->syncWithoutDetaching([$user->id])` (idempotent, matches the seeder's contract).

- Sysop loop: drop `'account_id' => null` from the `forceFill` call.

- `seedHistoricalLogins()`:

  - Query: `User::query()->where('is_sysop', false)->whereHas('accounts')->with('accounts:id')->get(['id', 'email'])`.

  - Inside the loop: `'account_id' => $user->account_id` → `'account_id' => $user->accounts->first()?->id` (eager-loaded above). Seeder runs in CLI with no session, so we use the loaded relation directly rather than the resolver.



### Frontend types



`resources/js/types/auth.ts` — drop `account_id: number | null` from the `User` type. `ActivityLog.account_id` stays. No page component changes — `auth.account` still serializes as a single `Account | null`, and `account.users[]` still renders on `sysops/accounts/show.tsx`.



### Tests



**Global find-and-replace**: `User::factory()->create(['account_id' => $account->id])` → `User::factory()->forAccount($account)->create()`. Files hit:



- `tests/Feature/Admin/UserIndexTest.php` — plus replace the `.account_id` assertions with `->has('users', 2)` (scoping is the actual subject; the FK check is dead weight now that users don't carry `account_id`).

- `tests/Feature/Admin/SecurityGroupControllerTest.php`

- `tests/Feature/Admin/UserActivationControllerTest.php`

- `tests/Feature/Sysops/SecurityGroupControllerTest.php`

- `tests/Feature/Sysops/UserActivationControllerTest.php`

- `tests/Feature/Sysops/AccountShowTest.php` — the `$member->forceFill(['account_id' => ...])` line → `User::factory()->forAccount($account)->create()`.



Assertion / helper updates:



- `tests/Feature/AccountScopingTest.php`

  - `User::factory()->create(['account_id' => null])` → `User::factory()->create()` (no accounts is the default).

  - `expect($account->owner->account_id)->toBe($account->id)` → `expect($account->owner->isMemberOf($account))->toBeTrue()`.

- `tests/Feature/DevSeederTest.php`

  - `$user->account` / `$user->account->name` / `$user->account->owner_user_id` → use `$user->defaultAccount()` (CLI context) for these assertions.

  - `$user->account_id` on the sysop assertion → `expect($user->accounts)->toBeEmpty()`.

- `tests/Feature/Auth/RegistrationTest.php` (skipped) — cosmetic updates: `$user->account_id` → `$user->accounts->isNotEmpty()`, etc.



**New tests (for the session-backed resolver — this is the point of the exercise):**



Add a new file `tests/Feature/AccountContextResolverTest.php` (or extend `AccountScopingTest.php`). Cases:



1. **No session value → resolver picks the user's default account and persists it.**

   Act as a user with one account membership, call `resolveForUser`, assert it returns that account and `session('current_account_id')` is set.

2. **Valid session value → resolver uses it unchanged.**

   Give the user two memberships (via `forAccount` twice), pre-seed `session(['current_account_id' => $secondAccount->id])`, assert `resolveForUser` returns `$secondAccount`.

3. **Stale session value (account the user no longer belongs to) → resolver forgets it and falls back.**

   Create two accounts, make the user a member of one, pre-seed session with the OTHER account's id, assert the resolver returns the user's default account and clears the session key.

4. **User with no memberships → resolver returns null.**

5. **`switchTo` rejects accounts the user isn't a member of** → 403.

6. **`switchTo` persists the new selection to session.**

7. **Middleware integration**: `actingAs($user)->get(route('dashboard'))` — `session('current_account_id')` is set after the request, and a subsequent request with the session cookie uses the same value.

8. **Resource scoping follows the current account**: create two accounts for a multi-account user, resources seeded in both; after a request with `session(['current_account_id' => $a->id])`, querying a `BelongsToAccount` model returns only $a's rows. (Guarantees the multi-account security model is actually enforced end-to-end.)



All other tests (Listeners, ActivityLogger, Sysops/ActivityLogIndex, Auth/*) keep working once the factories are updated, because they don't touch `account_id` directly.



## Verification



1. `./db_reset` — fresh schema + seed runs clean; verify `account_user` has the expected rows:

   ```

   php artisan tinker --execute 'App\Models\Account::with("users:id,email")->get()->each(fn($a) => print($a->name.": ".$a->users->pluck("email")->join(",")."\n"));'

   ```

2. `php artisan test --compact` — full suite green, including the new resolver tests.

3. Log in as `dev@example.com`; confirm dashboard loads, sidebar shows "Dev User's Account", `/admin/users` lists the dev user + members, `/admin` security-group and activation mutations work.

4. Log in as `sysop@example.com`; confirm `/sysops/{account}` renders the member list (proves the belongsToMany eager-load with qualified columns works).

5. Manual session sanity check in tinker:

   ```

   php artisan tinker --execute '$u = App\Models\User::where("email","alice@acme.example.com")->first(); dd($u->accounts->pluck("name"), $u->isMemberOf(App\Models\Account::where("name","Globex Inc")->first()));'

   ```

   Expect `["Acme Corp"]` and `false`.

6. `vendor/bin/pint --dirty --format agent`, `npm run lint`, `npm run types:check`, `npm run build` — clean.



## Notes / decisions left



- FK policy on the pivot: `restrictOnDelete()` (no cascade without explicit approval per CLAUDE.md). Deletions will need to detach explicitly when we add those flows.

- `accounts.owner_user_id` is still a single-user FK. Owner semantics are orthogonal to membership; keep as-is.

- No account-switch route/UI in this PR — only the `AccountContext::switchTo()` seam. Adding a UI is a future task that only touches controllers + frontend, not the model layer.

