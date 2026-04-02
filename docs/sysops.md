# Sysops Management

Sysops are privileged users who can administer all accounts in the system. They operate outside the normal account tenancy model (`account_id = null`) and have the `is_sysop` flag set to `true`.

## Security Model

- The `is_sysop` column is **not mass-assignable** — it is excluded from the User model's `#[Fillable]` attribute.
- There is no UI to grant or revoke sysop access. This is intentional.
- Sysop status can only be changed via direct database access (`forceFill`, tinker, or migration).

## Granting Sysop Access

```bash
php artisan tinker --execute '
    $user = \App\Models\User::where("email", "admin@example.com")->firstOrFail();
    $user->forceFill(["is_sysop" => true, "account_id" => null])->save();
    echo "Sysop granted to: " . $user->email;
'
```

## Revoking Sysop Access

```bash
php artisan tinker --execute '
    $user = \App\Models\User::where("email", "admin@example.com")->firstOrFail();
    $user->forceFill(["is_sysop" => false])->save();
    echo "Sysop revoked for: " . $user->email;
'
```

After revoking, the user will have `account_id = null` and no sysop flag. They will need to be assigned to an account before they can use the normal application:

```bash
php artisan tinker --execute '
    $user = \App\Models\User::where("email", "admin@example.com")->firstOrFail();
    $user->update(["account_id" => 1]);
    echo "Assigned to account: " . $user->account->name;
'
```

## Dev Environment

The `DevSeeder` creates a sysop user automatically:

- **Email:** `sysop@example.com`
- **Password:** `sysopsecretpass`

Run `php artisan db:seed --class=DevSeeder` to create it.
