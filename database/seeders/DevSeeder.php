<?php

namespace Database\Seeders;

use App\Enums\ActivityEvent;
use App\Enums\ActivityLogType;
use App\Enums\AiAgentKind;
use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\AiAgentSetting;
use App\Models\ContractTemplate;
use App\Models\Customer;
use App\Models\DepositDefaults;
use App\Models\SecurityGroupUser;
use App\Models\User;
use Carbon\CarbonImmutable;
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

            $account->users()->syncWithoutDetaching([$user->id]);

            foreach ($userData['members'] ?? [] as $memberData) {
                $member = User::updateOrCreate(
                    ['email' => $memberData['email']],
                    ['name' => $memberData['name'], 'password' => $memberData['password']],
                );

                $account->users()->syncWithoutDetaching([$member->id]);
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

            $account->users()->syncWithoutDetaching([$owner->id]);

            foreach ($accountData['users'] as $memberData) {
                $member = User::updateOrCreate(
                    ['email' => $memberData['email']],
                    ['name' => $memberData['name'], 'password' => $memberData['password']],
                );

                $account->users()->syncWithoutDetaching([$member->id]);
            }
        }

        foreach ($this->sysopUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => $userData['password']],
            );

            if (! $user->is_sysop) {
                $user->forceFill(['is_sysop' => true])->save();
            }
        }

        $this->seedSecurityGroups();
        $this->seedActivityLogs();
        $this->seedCustomers();
        $this->seedAiAgentSettings();
        $this->seedContractTemplates();
        $this->seedDepositDefaults();
    }

    /**
     * Seed the sysop-managed default deposit percentages. Idempotent — reruns
     * use firstOrCreate so sysop edits are never overwritten.
     */
    private function seedDepositDefaults(): void
    {
        DepositDefaults::firstOrCreate(
            ['kind' => DepositDefaults::DEFAULT_KIND],
            [
                'material_deposit_percent' => 100,
                'labor_deposit_percent' => 80,
            ],
        );
    }

    /**
     * Seed the sysop-managed default contract template. Idempotent — reruns
     * use firstOrCreate so sysop edits are never overwritten.
     */
    private function seedContractTemplates(): void
    {
        ContractTemplate::firstOrCreate(
            ['kind' => ContractTemplate::DEFAULT_KIND],
            [
                'label' => 'Default contract',
                'body' => <<<'MARKDOWN'
                    # Service agreement

                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Proin vitae
                    tempor lacus. Etiam pretium lectus ut neque tempor, at tincidunt
                    nulla sagittis. Aliquam erat volutpat. In hac habitasse platea
                    dictumst.

                    ## Scope of work

                    - Lorem ipsum dolor sit amet.
                    - Consectetur adipiscing elit.
                    - Sed do eiusmod tempor incididunt ut labore.

                    ## Payment

                    Nulla facilisi. Curabitur ut nibh nec nunc feugiat tincidunt. Sed
                    ultrices, urna ac dictum volutpat, leo lorem tincidunt magna, vitae
                    porta orci magna vitae felis.

                    ## Warranty

                    Mauris vitae volutpat elit. Integer sit amet risus nec lorem
                    dignissim pretium. Vivamus at lectus sit amet orci rhoncus
                    sollicitudin vitae non nisi.
                    MARKDOWN,
            ],
        );
    }

    /**
     * Seed the editable AI agent configuration rows. Must be idempotent —
     * reruns should not overwrite a sysop's edits.
     */
    private function seedAiAgentSettings(): void
    {
        foreach (AiAgentKind::cases() as $kind) {
            AiAgentSetting::firstOrCreate(
                ['kind' => $kind->value],
                [
                    'label' => $kind->label(),
                    'description' => $kind->description(),
                    'system_prompt' => $kind->defaultSystemPrompt(),
                ],
            );
        }
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
            $user = User::where('email', $email)->with('accounts:id')->first();

            if ($user) {
                $accountId = $user->accounts->first()?->id;

                if ($accountId) {
                    SecurityGroupUser::firstOrCreate([
                        'account_id' => $accountId,
                        'user_id' => $user->id,
                        'security_group' => SecurityGroup::Admin,
                    ]);
                }
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
            ['event' => ActivityEvent::UserLoggedIn, 'metadata' => ['ip' => '192.168.1.11', 'email' => $bob?->email], 'account_id' => $acme?->id, 'user_id' => $bob?->id],
            ['event' => ActivityEvent::UserTwoFactorEnabled, 'metadata' => ['ip' => '192.168.1.11'], 'account_id' => $acme?->id, 'user_id' => $bob?->id],
            ['event' => ActivityEvent::UserPasswordReset, 'metadata' => ['ip' => '172.16.0.3'], 'account_id' => $acme?->id, 'user_id' => $alice?->id],
            ['event' => ActivityEvent::UserLoggedIn, 'metadata' => ['ip' => '10.0.0.2', 'email' => $dan?->email], 'account_id' => $globex?->id, 'user_id' => $dan?->id],
            ['event' => ActivityEvent::UserTwoFactorDisabled, 'metadata' => ['ip' => '10.0.0.2'], 'account_id' => $globex?->id, 'user_id' => $dan?->id],
            ['event' => ActivityEvent::UserDeactivated, 'metadata' => ['target_user_id' => $eve?->id, 'target_user_email' => $eve?->email], 'account_id' => $globex?->id, 'user_id' => $dan?->id],
            ['event' => ActivityEvent::UserActivated, 'metadata' => ['target_user_id' => $eve?->id, 'target_user_email' => $eve?->email], 'account_id' => $globex?->id, 'user_id' => $dan?->id],
            ['event' => ActivityEvent::UserSecurityGroupAdded, 'metadata' => ['security_group' => 'admin', 'target_user_id' => $bob?->id, 'target_user_email' => $bob?->email], 'account_id' => $acme?->id, 'user_id' => $alice?->id],
            ['event' => ActivityEvent::UserSecurityGroupRemoved, 'metadata' => ['security_group' => 'admin', 'target_user_id' => $bob?->id, 'target_user_email' => $bob?->email], 'account_id' => $acme?->id, 'user_id' => $alice?->id],
        ];

        foreach ($logs as $log) {
            ActivityLog::firstOrCreate(
                ['event' => $log['event'], 'user_id' => $log['user_id']],
                [
                    'type' => ActivityLogType::Info,
                    'description' => $log['event']->label(),
                    'metadata' => $log['metadata'],
                    'account_id' => $log['account_id'],
                ],
            );
        }

        $this->seedHistoricalLogins();
    }

    /**
     * Seed sample customers for the dev user's account, covering the mix of
     * states we want visible in local UI: fully populated, minimal-required,
     * recently viewed (sorted to the top), older views, never viewed, and an
     * archived (soft-deleted) row.
     */
    private function seedCustomers(): void
    {
        $devUser = User::where('email', 'dev@example.com')->first();
        $account = $devUser?->ownedAccount;

        if ($account === null) {
            return;
        }

        $now = CarbonImmutable::now();

        $customers = [
            [
                'first_name' => 'Zephyr',
                'last_name' => 'Zimmerman',
                'company' => 'Zimmerman & Sons Plumbing',
                'email' => 'zephyr@zimmermanplumbing.example.com',
                'phone' => '(555) 010-2200',
                'address_line_1' => '4201 Industrial Blvd',
                'address_line_2' => 'Suite 12',
                'city' => 'Austin',
                'state' => 'TX',
                'zip' => '78702',
                'notes' => 'Long-time customer. Prefers text messages for scheduling. Has a standing annual maintenance agreement.',
                'last_accessed_at' => $now->subMinutes(15),
                'archived' => false,
            ],
            [
                'first_name' => 'Hannah',
                'last_name' => 'Okonkwo',
                'company' => 'Bright Horizons Daycare',
                'email' => 'hannah.o@brighthorizons.example.com',
                'phone' => '(555) 010-4412',
                'address_line_1' => '88 Oak Street',
                'address_line_2' => null,
                'city' => 'Portland',
                'state' => 'OR',
                'zip' => '97205',
                'notes' => 'Needs 24-hour advance notice for on-site work (children present).',
                'last_accessed_at' => $now->subHours(6),
                'archived' => false,
            ],
            [
                'first_name' => 'Marcus',
                'last_name' => 'Delgado',
                'company' => null,
                'email' => 'marcus.delgado@example.com',
                'phone' => '(555) 010-7788',
                'address_line_1' => '122 Elm Avenue',
                'address_line_2' => 'Apt 4B',
                'city' => 'Denver',
                'state' => 'CO',
                'zip' => '80202',
                'notes' => null,
                'last_accessed_at' => $now->subDays(3),
                'archived' => false,
            ],
            [
                'first_name' => 'Priya',
                'last_name' => 'Raman',
                'company' => 'Raman Consulting LLC',
                'email' => 'priya@ramanconsulting.example.com',
                'phone' => null,
                'address_line_1' => null,
                'address_line_2' => null,
                'city' => null,
                'state' => null,
                'zip' => null,
                'notes' => 'Email-only contact preference.',
                'last_accessed_at' => $now->subWeeks(1),
                'archived' => false,
            ],
            [
                'first_name' => 'Alex',
                'last_name' => 'Astro',
                'company' => null,
                'email' => null,
                'phone' => '(555) 010-9911',
                'address_line_1' => '7 Sunset Lane',
                'address_line_2' => null,
                'city' => 'Santa Monica',
                'state' => 'CA',
                'zip' => '90401',
                'notes' => 'Phone-only lead from trade show.',
                'last_accessed_at' => null,
                'archived' => false,
            ],
            [
                'first_name' => 'Brenda',
                'last_name' => 'Beckett',
                'company' => 'Beckett Properties',
                'email' => null,
                'phone' => null,
                'address_line_1' => null,
                'address_line_2' => null,
                'city' => null,
                'state' => null,
                'zip' => null,
                'notes' => null,
                'last_accessed_at' => null,
                'archived' => false,
            ],
            [
                'first_name' => 'Charlie',
                'last_name' => 'Chavez',
                'company' => 'Chavez Auto Body',
                'email' => 'charlie@chavezauto.example.com',
                'phone' => '(555) 010-3344',
                'address_line_1' => '915 Main Street',
                'address_line_2' => null,
                'city' => 'Albuquerque',
                'state' => 'NM',
                'zip' => '87102',
                'notes' => 'Referred by Zimmerman. Fleet of 4 service vehicles.',
                'last_accessed_at' => null,
                'archived' => false,
            ],
            [
                'first_name' => 'Echo',
                'last_name' => 'Edwards',
                'company' => 'Edwards & Co',
                'email' => 'echo@edwardsco.example.com',
                'phone' => '(555) 010-6677',
                'address_line_1' => '300 Lakeshore Drive',
                'address_line_2' => null,
                'city' => 'Chicago',
                'state' => 'IL',
                'zip' => '60601',
                'notes' => 'Relationship closed — kept for historical reference.',
                'last_accessed_at' => $now->subMonths(2),
                'archived' => true,
            ],
        ];

        foreach ($customers as $data) {
            $archived = $data['archived'];
            unset($data['archived']);

            $customer = Customer::withTrashed()->updateOrCreate(
                [
                    'account_id' => $account->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                ],
                [...$data, 'account_id' => $account->id],
            );

            if ($archived && $customer->deleted_at === null) {
                $customer->delete();
            }
        }
    }

    /**
     * Seed deterministic historical login events across the last two weeks for
     * every non-sysop user, so the sysop dashboard has something to render.
     *
     * Idempotent: keyed on (event, user_id, created_at). The same user/day pair
     * always produces the same timestamp, so reruns are no-ops.
     */
    private function seedHistoricalLogins(): void
    {
        $users = User::query()
            ->where('is_sysop', false)
            ->whereHas('accounts')
            ->with('accounts:id')
            ->get(['id', 'email']);

        if ($users->isEmpty()) {
            return;
        }

        $now = CarbonImmutable::now();
        $today = $now->startOfDay();

        for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
            $day = $today->subDays($daysAgo);

            foreach ($users as $user) {
                $activation = crc32('activate-'.$user->email) % 14;
                if ($daysAgo > 13 - $activation) {
                    continue;
                }

                $bucket = crc32($user->id.'-'.$day->toDateString()) % 5;
                if ($bucket >= 2) {
                    continue;
                }

                $hour = 8 + (crc32('hour-'.$user->id.'-'.$day->toDateString()) % 10);
                $minute = crc32('min-'.$user->id.'-'.$day->toDateString()) % 60;
                $loggedInAt = $day->setTime($hour, $minute);

                if ($loggedInAt->greaterThan($now)) {
                    continue;
                }

                ActivityLog::firstOrCreate(
                    [
                        'event' => ActivityEvent::UserLoggedIn,
                        'user_id' => $user->id,
                        'created_at' => $loggedInAt,
                    ],
                    [
                        'type' => ActivityLogType::Info,
                        'description' => ActivityEvent::UserLoggedIn->label(),
                        'metadata' => ['ip' => '127.0.0.1', 'email' => $user->email, 'seeded' => true],
                        'account_id' => $user->accounts->first()?->id,
                    ],
                );
            }
        }
    }
}
