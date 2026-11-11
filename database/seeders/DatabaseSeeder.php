<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAdministrator();
        $this->seedTestUser();
    }

    /**
     * Create or update the default administrator from the ADMIN_* values in .env.
     */
    private function seedAdministrator(): void
    {
        /** @var array{name: ?string, email: ?string, password: ?string} $admin */
        $admin = config('app.admin');

        if (blank($admin['name']) || blank($admin['email']) || blank($admin['password'])) {
            throw new RuntimeException('Set ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.');
        }

        User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * Create or update the shared test account.
     */
    private function seedTestUser(): void
    {
        User::updateOrCreate(
            ['email' => 'test@test.com'],
            [
                'name' => 'test',
                'password' => 'orions40pirulos',
                'email_verified_at' => now(),
            ],
        );
    }
}
