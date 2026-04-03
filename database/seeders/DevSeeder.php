<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    /**
     * Dev accounts to seed. Add new entries here to create additional dev users.
     * Each entry requires: name, email, password.
     * Passwords are automatically hashed by the User model's password cast.
     *
     * IMPORTANT: This seeder must be idempotent — safe to run multiple times. Existing
     * records are updated, new records added; no records are ever deleted.
     *
     * @var array<int, array{name: string, email: string, password: string}>
     */
    protected array $devUsers = [
        ['name' => 'Dev User', 'email' => 'dev@example.com', 'password' => 'retryfilterqueue'],
    ];

    /**
     * Additional accounts to seed with their owner and member users.
     *
     * @var array<int, array{name: string, owner: array{name: string, email: string, password: string}, users: array<int, array{name: string, email: string, password: string}>}>
     */
    protected array $seedAccounts = [
        [
            'name' => 'Acme Corp',
            'owner' => ['name' => 'Alice Owner', 'email' => 'alice@acme.example.com', 'password' => 'acmeownerpass'],
            'users' => [
                ['name' => 'Bob Smith', 'email' => 'bob@acme.example.com', 'password' => 'bobsmithpass'],
                ['name' => 'Carol Jones', 'email' => 'carol@acme.example.com', 'password' => 'caroljonespass'],
            ],
        ],
        [
            'name' => 'Globex Inc',
            'owner' => ['name' => 'Dan Owner', 'email' => 'dan@globex.example.com', 'password' => 'danownerpass'],
            'users' => [
                ['name' => 'Eve Adams', 'email' => 'eve@globex.example.com', 'password' => 'eveadamspass'],
                ['name' => 'Frank Lee', 'email' => 'frank@globex.example.com', 'password' => 'frankleepass'],
                ['name' => 'Grace Kim', 'email' => 'grace@globex.example.com', 'password' => 'gracekimpass'],
            ],
        ],
    ];

    /**
     * Sysop users to seed. These users have no account and is_sysop = true.
     * Uses forceFill to bypass mass assignment protection on is_sysop.
     *
     * @var array<int, array{name: string, email: string, password: string}>
     */
    protected array $sysopUsers = [
        ['name' => 'Sysop User', 'email' => 'sysop@example.com', 'password' => 'sysopsecretpass'],
    ];

    /**
     * Run the database seeds. Uses updateOrCreate keyed on email so this
     * can be run repeatedly without duplicating users.
     */
    public function run(): void
    {
        foreach ($this->devUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => $userData['password']],
            );

            $account = Account::firstOrCreate(
                ['owner_user_id' => $user->id],
                ['name' => $user->name."'s Account"],
            );

            if ($user->account_id !== $account->id) {
                $user->forceFill(['account_id' => $account->id])->save();
            }
        }

        foreach ($this->seedAccounts as $accountData) {
            $owner = User::updateOrCreate(
                ['email' => $accountData['owner']['email']],
                ['name' => $accountData['owner']['name'], 'password' => $accountData['owner']['password']],
            );

            $account = Account::firstOrCreate(
                ['name' => $accountData['name']],
                ['owner_user_id' => $owner->id],
            );

            if ($owner->account_id !== $account->id) {
                $owner->forceFill(['account_id' => $account->id])->save();
            }

            foreach ($accountData['users'] as $memberData) {
                $member = User::updateOrCreate(
                    ['email' => $memberData['email']],
                    ['name' => $memberData['name'], 'password' => $memberData['password']],
                );

                if ($member->account_id !== $account->id) {
                    $member->forceFill(['account_id' => $account->id])->save();
                }
            }
        }

        foreach ($this->sysopUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => $userData['password']],
            );

            if (! $user->is_sysop) {
                $user->forceFill(['is_sysop' => true, 'account_id' => null])->save();
            }
        }
    }
}
