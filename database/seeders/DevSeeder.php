<?php

namespace Database\Seeders;

use App\Enums\ActivityLogType;
use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\SecurityGroupUser;
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
     * @var array<int, array{name: string, email: string, password: string, members?: array<int, array{name: string, email: string, password: string}>}>
     */
    protected array $devUsers = [
        [
            'name' => 'Dev User',
            'email' => 'dev@example.com',
            'password' => 'retryfilterqueue',
            'members' => [
                ['name' => 'Jamie Rivera', 'email' => 'jamie@example.com', 'password' => 'jamierivapass'],
                ['name' => 'Morgan Chen', 'email' => 'morgan@example.com', 'password' => 'morganchenpass'],
                ['name' => 'Taylor Brooks', 'email' => 'taylor@example.com', 'password' => 'taylorbrookspass'],
            ],
        ],
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

            foreach ($userData['members'] ?? [] as $memberData) {
                $member = User::updateOrCreate(
                    ['email' => $memberData['email']],
                    ['name' => $memberData['name'], 'password' => $memberData['password']],
                );

                if ($member->account_id !== $account->id) {
                    $member->forceFill(['account_id' => $account->id])->save();
                }
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

        $this->seedSecurityGroups();
        $this->seedActivityLogs();
    }

    /**
     * Seed security group memberships for development.
     */
    private function seedSecurityGroups(): void
    {
        $admins = [
            'dev@example.com',
            'alice@acme.example.com',
            'dan@globex.example.com',
        ];

        foreach ($admins as $email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                SecurityGroupUser::firstOrCreate([
                    'user_id' => $user->id,
                    'security_group' => SecurityGroup::Admin,
                ]);
            }
        }
    }

    /**
     * Seed sample activity log entries for development.
     */
    private function seedActivityLogs(): void
    {
        $acme = Account::where('name', 'Acme Corp')->first();
        $globex = Account::where('name', 'Globex Inc')->first();
        $alice = User::where('email', 'alice@acme.example.com')->first();
        $bob = User::where('email', 'bob@acme.example.com')->first();
        $dan = User::where('email', 'dan@globex.example.com')->first();
        $eve = User::where('email', 'eve@globex.example.com')->first();

        $logs = [
            ['type' => ActivityLogType::Info, 'description' => 'User signed up', 'metadata' => ['ip' => '192.168.1.10'], 'account_id' => $acme?->id, 'user_id' => $alice?->id],
            ['type' => ActivityLogType::Info, 'description' => 'User logged in', 'metadata' => ['ip' => '192.168.1.11'], 'account_id' => $acme?->id, 'user_id' => $bob?->id],
            ['type' => ActivityLogType::Error, 'description' => 'Login lockout after 5 failed attempts', 'metadata' => ['ip' => '10.0.0.5', 'attempts' => 5], 'account_id' => $globex?->id, 'user_id' => $eve?->id],
            ['type' => ActivityLogType::Info, 'description' => 'User signed up', 'metadata' => ['ip' => '10.0.0.1'], 'account_id' => $globex?->id, 'user_id' => $dan?->id],
            ['type' => ActivityLogType::Info, 'description' => 'Password reset requested', 'metadata' => ['ip' => '172.16.0.3'], 'account_id' => $acme?->id, 'user_id' => $alice?->id],
            ['type' => ActivityLogType::Error, 'description' => 'Email verification failed — token expired', 'metadata' => null, 'account_id' => $globex?->id, 'user_id' => $eve?->id],
            ['type' => ActivityLogType::Info, 'description' => 'Two-factor authentication enabled', 'metadata' => null, 'account_id' => $acme?->id, 'user_id' => $bob?->id],
            ['type' => ActivityLogType::Error, 'description' => 'Queue worker restarted unexpectedly', 'metadata' => ['signal' => 'SIGTERM'], 'account_id' => null, 'user_id' => null],
            ['type' => ActivityLogType::Info, 'description' => 'Account created', 'metadata' => null, 'account_id' => $acme?->id, 'user_id' => $alice?->id],
            ['type' => ActivityLogType::Info, 'description' => 'User logged in', 'metadata' => ['ip' => '10.0.0.2'], 'account_id' => $globex?->id, 'user_id' => $dan?->id],
        ];

        foreach ($logs as $log) {
            ActivityLog::firstOrCreate(
                ['type' => $log['type'], 'description' => $log['description'], 'user_id' => $log['user_id']],
                ['metadata' => $log['metadata'], 'account_id' => $log['account_id']],
            );
        }
    }
}
