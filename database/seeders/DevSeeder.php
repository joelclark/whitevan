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
                $user->update(['account_id' => $account->id]);
            }
        }
    }
}
