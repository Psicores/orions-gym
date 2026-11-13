<?php

namespace Tests\Feature;

use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DemoClientSeeder;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_the_security_headers(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_https_responses_tell_the_browser_to_always_use_https(): void
    {
        $this->get('https://localhost/ingresar')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_one_ip_cannot_try_one_dni_after_another(): void
    {
        $client = Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);

        foreach (range(1, 10) as $attempt) {
            $this->post(route('login.store'), ['usuario' => (string) (20000000 + $attempt), 'password' => (string) (20000000 + $attempt)]);
        }

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456'])
            ->assertSessionHasErrors('usuario');
        $this->assertGuest('client');

        $this->travel(61)->seconds();

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456'])
            ->assertRedirect(route('member.dashboard'));
        $this->assertAuthenticatedAs($client, 'client');
    }

    public function test_another_ip_is_not_affected_by_the_limit(): void
    {
        Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);

        foreach (range(1, 10) as $attempt) {
            $this->post(route('login.store'), ['usuario' => (string) (20000000 + $attempt), 'password' => 'x']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456'])
            ->assertRedirect(route('member.dashboard'));
    }

    public function test_the_shared_test_account_is_removed_when_there_is_another_staff_account(): void
    {
        User::factory()->create();
        User::factory()->create(['email' => 'pandu@pandu.com']);

        $this->runRemoveTestAccountMigration();

        $this->assertDatabaseMissing(User::class, ['email' => 'pandu@pandu.com']);
        $this->assertSame(1, User::query()->count());
    }

    public function test_the_shared_test_account_is_kept_when_it_is_the_only_one(): void
    {
        User::factory()->create(['email' => 'pandu@pandu.com']);

        $this->runRemoveTestAccountMigration();

        $this->assertDatabaseHas(User::class, ['email' => 'pandu@pandu.com']);
    }

    public function test_the_shared_test_account_is_locked_instead_of_removed_when_it_registered_payments(): void
    {
        User::factory()->create();
        $account = User::factory()->create(['email' => 'pandu@pandu.com', 'password' => 'vieja-clave-1']);
        Payment::factory()->create(['registrado_por_user_id' => $account->id]);

        $this->runRemoveTestAccountMigration();

        $account->refresh();
        $this->assertNull($account->email_verified_at);
        $this->assertFalse(password_verify('vieja-clave-1', $account->password));
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_the_demo_data_can_be_removed_without_touching_real_data(): void
    {
        Storage::fake('public');
        config(['app.admin' => ['name' => 'Dueño', 'email' => 'dueno@example.com', 'password' => 'secreto-123']]);
        config(['gym.seed_demo_data' => true]);
        $this->seed();
        $realClient = Client::factory()->create();
        Attendance::factory()->for($realClient)->create();
        $realAnnouncement = Announcement::factory()->create();
        $keptDemoClient = Client::query()->where('dni', '30100002')->firstOrFail();
        $keptDemoClient->update(['email' => 'martin.real@example.com']);

        $this->artisan('demo:remove', ['--force' => true])->assertSuccessful();

        $this->assertSame(
            [$keptDemoClient->id, $realClient->id],
            Client::withTrashed()->orderBy('id')->pluck('id')->all(),
        );
        $this->assertSame(0, Client::withTrashed()->where('email', DemoClientSeeder::emailFor('30100001'))->count());
        $this->assertSame(0, Payment::query()->where('client_id', '!=', $keptDemoClient->id)->count());
        $this->assertSame(0, Membership::query()->whereNotIn('client_id', [$keptDemoClient->id, $realClient->id])->count());
        $this->assertSame(1, Attendance::query()->count());
        $this->assertSame([$realAnnouncement->id], Announcement::query()->pluck('id')->all());
        $this->assertTrue(Plan::query()->where('nombre', '3 días')->exists());
        $this->assertSame(1, User::query()->count());
        Storage::disk('public')->assertMissing('announcements/demo-banner-gimnasio.jpg');
    }

    public function test_removing_the_demo_data_asks_for_confirmation(): void
    {
        Storage::fake('public');
        config(['app.admin' => ['name' => 'Dueño', 'email' => 'dueno@example.com', 'password' => 'secreto-123']]);
        config(['gym.seed_demo_data' => true]);
        $this->seed();

        $this->artisan('demo:remove')
            ->expectsConfirmation('Se van a borrar definitivamente los clientes y anuncios de ejemplo. ¿Seguir?', 'no')
            ->assertSuccessful();

        $this->assertSame(11, Client::withTrashed()->count());
        $this->assertSame(6, Announcement::query()->count());
    }

    public function test_an_svg_cannot_be_uploaded_as_an_announcement_image(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageAnnouncements::class)
            ->callAction(CreateAction::class, [
                'titulo' => 'Con dibujo',
                'imagen_path' => UploadedFile::fake()->createWithContent('dibujo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            ])
            ->assertHasFormErrors(['imagen_path']);

        $this->assertSame(0, Announcement::query()->count());
    }

    private function runRemoveTestAccountMigration(): void
    {
        (require database_path('migrations/2026_11_12_234810_remove_shared_test_account_from_users_table.php'))->up();
    }
}
