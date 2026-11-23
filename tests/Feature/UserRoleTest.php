<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Pages\UserManual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    private function runMigrationOnUsers(): void
    {
        $migration = require database_path('migrations/2026_11_22_184619_add_role_to_users_table.php');
        $migration->down();
        $migration->up();
    }

    public function test_new_accounts_are_profes(): void
    {
        $user = User::query()->create(['name' => 'Profe', 'email' => 'profe@example.com', 'password' => 'secreto-123'])->refresh();

        $this->assertSame(UserRoleEnum::Profe, $user->role);
        $this->assertFalse($user->isSiteAdmin());
    }

    public function test_the_seeder_creates_the_env_account_as_admin(): void
    {
        config(['app.admin' => ['name' => 'Dueño', 'email' => 'dueno@example.com', 'password' => 'secreto-123']]);

        $this->seed();

        $this->assertSame(UserRoleEnum::Admin, User::query()->where('email', 'dueno@example.com')->sole()->role);
    }

    public function test_the_migration_makes_the_env_account_admin_and_the_rest_profes(): void
    {
        $profe = User::factory()->create(['email' => 'profe@example.com']);
        $owner = User::factory()->create(['email' => 'Dueno@Example.com']);
        config(['app.admin.email' => ' dueno@example.com ']);
        Log::spy();

        $this->runMigrationOnUsers();

        $this->assertSame('admin', DB::table('users')->where('id', $owner->id)->value('role'));
        $this->assertSame('profe', DB::table('users')->where('id', $profe->id)->value('role'));
        Log::shouldNotHaveReceived('warning');
    }

    public function test_without_a_matching_env_account_the_oldest_one_becomes_admin(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        config(['app.admin.email' => 'nadie@example.com']);
        Log::spy();

        $this->runMigrationOnUsers();

        $this->assertSame('admin', DB::table('users')->where('id', $first->id)->value('role'));
        $this->assertSame('profe', DB::table('users')->where('id', $second->id)->value('role'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_migration_on_an_empty_table_only_adds_the_column(): void
    {
        config(['app.admin.email' => null]);

        $this->runMigrationOnUsers();

        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertSame(0, User::query()->count());
    }

    public function test_only_admins_see_the_manual(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(UserManual::getUrl())
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(UserManual::getUrl())
            ->assertForbidden();
    }
}
