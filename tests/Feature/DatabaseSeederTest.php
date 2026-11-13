<?php

namespace Tests\Feature;

use App\Enums\ClientStatusEnum;
use App\Models\Announcement;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['app.admin' => ['name' => 'Dueño', 'email' => 'dueno@example.com', 'password' => 'secreto-123']]);
        config(['gym.seed_demo_data' => true]);
    }

    public function test_a_single_seed_on_an_empty_database_loads_everything(): void
    {
        $this->seed();

        $this->assertDatabaseHas(User::class, ['email' => 'dueno@example.com']);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(3, Plan::query()->count());
        $this->assertSame(11, Client::withTrashed()->count());
        $this->assertSame(1, Client::onlyTrashed()->count());
        $this->assertGreaterThan(0, Membership::query()->count());
        $this->assertGreaterThan(0, Payment::query()->count());
        $this->assertSame(6, Announcement::query()->count());
        $this->assertSame(3, Announcement::query()->active()->count());

        foreach (Announcement::query()->whereNotNull('imagen_path')->get() as $announcement) {
            Storage::disk('public')->assertExists($announcement->imagen_path);
        }
    }

    public function test_seeding_again_duplicates_nothing_and_keeps_changes_made_from_the_panel(): void
    {
        $this->seed();
        $counts = $this->counts();
        Plan::query()->where('nombre', '3 días')->update(['precio' => 99999]);
        Client::query()->where('dni', '30100001')->update(['nombre' => 'Editada', 'estado' => ClientStatusEnum::Inactivo]);
        Announcement::query()->where('titulo', 'Llegaron las barras nuevas')->update(['is_active' => false]);

        $this->seed();

        $this->assertSame($counts, $this->counts());
        $this->assertEquals(99999, Plan::query()->where('nombre', '3 días')->value('precio'));
        $this->assertSame('Editada', Client::query()->where('dni', '30100001')->value('nombre'));
        $this->assertFalse(Announcement::query()->where('titulo', 'Llegaron las barras nuevas')->value('is_active'));
    }

    public function test_the_seed_also_loads_the_demo_data_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(11, Client::withTrashed()->count());
        $this->assertSame(6, Announcement::query()->count());
    }

    public function test_the_demo_data_can_be_switched_off(): void
    {
        config(['gym.seed_demo_data' => false]);

        $this->seed();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(3, Plan::query()->count());
        $this->assertSame(0, Client::withTrashed()->count());
        $this->assertSame(0, Announcement::query()->count());
    }

    public function test_the_seed_does_not_break_when_the_admin_variables_are_missing(): void
    {
        config(['app.admin' => ['name' => null, 'email' => null, 'password' => null]]);

        $this->seed();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(3, Plan::query()->count());
        $this->assertSame(0, Client::withTrashed()->count());
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'users' => User::query()->count(),
            'plans' => Plan::withTrashed()->count(),
            'clients' => Client::withTrashed()->count(),
            'memberships' => Membership::query()->count(),
            'payments' => Payment::query()->count(),
            'announcements' => Announcement::query()->count(),
        ];
    }
}
