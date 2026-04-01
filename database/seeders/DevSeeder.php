<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    /**
     * Dev accounts to seed. Add new entries here to create additional dev users.
     * Each entry requires: name, email, password.
     * Passwords are automatically hashed by the User model's password cast.
     *
     * @var array<int, array{name: string, email: string, password: string}>
     */
    protected array $devUsers = [
        ['name' => 'Dev User', 'email' => 'dev@example.com', 'password' => 'retryfilterqueue'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->devUsers as $user) {
            User::factory()->create($user);
        }
    }
}
