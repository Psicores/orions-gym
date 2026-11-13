<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_screen_loads_and_links_the_pwa_manifest(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('DNI o email')
            ->assertSee('rel="manifest"', escape: false)
            ->assertSee('<meta name="theme-color" content="#161817">', escape: false);
    }

    public function test_the_landing_sends_members_to_the_login_screen(): void
    {
        $this->get(route('inicio'))->assertOk()->assertSee('href="'.route('login').'"', escape: false);
    }

    public function test_a_member_logs_in_with_their_dni_as_user_and_password(): void
    {
        $client = Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456'])
            ->assertRedirect(route('member.dashboard'));

        $this->assertAuthenticatedAs($client, 'client');
        $this->assertGuest('web');
    }

    public function test_a_member_can_type_the_dni_with_dots(): void
    {
        $client = Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);

        $this->post(route('login.store'), ['usuario' => '30.123.456', 'password' => '30.123.456'])
            ->assertRedirect(route('member.dashboard'));

        $this->assertAuthenticatedAs($client, 'client');
    }

    public function test_a_member_can_log_in_with_their_email_instead_of_the_dni(): void
    {
        $client = Client::factory()->withDniAsPassword()->create(['dni' => '30123456', 'email' => 'juan@example.com']);

        $this->post(route('login.store'), ['usuario' => 'Juan@Example.com', 'password' => '30123456'])
            ->assertRedirect(route('member.dashboard'));

        $this->assertAuthenticatedAs($client, 'client');
    }

    public function test_the_member_is_remembered_only_when_they_ask_for_it(): void
    {
        $client = Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);
        $recaller = auth()->guard('client')->getRecallerName();

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456'])
            ->assertRedirect(route('member.dashboard'))
            ->assertCookieMissing($recaller);
        $this->assertNull($client->refresh()->remember_token);

        auth()->guard('client')->logout();

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456', 'remember' => '1'])
            ->assertRedirect(route('member.dashboard'))
            ->assertCookie($recaller);
    }

    public function test_the_login_screen_offers_to_keep_the_session_open(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Mantener la sesión iniciada')->assertSee('name="remember"', escape: false);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);

        $this->from(route('login'))
            ->post(route('login.store'), ['usuario' => '30123456', 'password' => '11111111'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['usuario' => 'El DNI o la contraseña no son correctos.']);

        $this->assertGuest('client');
    }

    public function test_once_the_member_chose_a_password_the_dni_stops_working(): void
    {
        $client = Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);
        $client->password = 'fierros-2026';
        $client->save();

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456'])
            ->assertSessionHasErrors('usuario');
        $this->assertGuest('client');

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => 'fierros-2026'])
            ->assertRedirect(route('member.dashboard'));
        $this->assertAuthenticatedAs($client, 'client');
    }

    public function test_a_deleted_member_cannot_log_in(): void
    {
        Client::factory()->withDniAsPassword()->create(['dni' => '30123456'])->delete();

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456'])
            ->assertSessionHasErrors('usuario');

        $this->assertGuest('client');
    }

    public function test_staff_log_in_with_their_email_and_go_to_the_admin_panel(): void
    {
        $user = User::factory()->create(['email' => 'profe@example.com', 'password' => 'secreto-123']);

        $this->post(route('login.store'), ['usuario' => 'profe@example.com', 'password' => 'secreto-123'])
            ->assertRedirect(Filament::getPanel('admin')->getUrl());

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('client');
    }

    public function test_staff_without_access_to_the_panel_are_rejected(): void
    {
        User::factory()->unverified()->create(['email' => 'profe@example.com', 'password' => 'secreto-123']);

        $this->post(route('login.store'), ['usuario' => 'profe@example.com', 'password' => 'secreto-123'])
            ->assertSessionHasErrors('usuario');

        $this->assertGuest('web');
    }

    public function test_login_is_blocked_after_five_failed_attempts(): void
    {
        Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['usuario' => '30123456', 'password' => 'mal']);
        }

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456'])
            ->assertSessionHasErrors('usuario');

        $this->assertGuest('client');
    }

    public function test_guests_are_sent_to_the_login_and_back_to_the_screen_they_wanted(): void
    {
        Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);

        $this->get(route('member.history'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['usuario' => '30123456', 'password' => '30123456'])
            ->assertRedirect(route('member.history'));
    }

    public function test_staff_cannot_open_the_member_portal(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->get(route('member.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_a_member_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(Client::factory()->withDniAsPassword()->create(), 'client')
            ->get(Filament::getPanel('admin')->getUrl())
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
    }

    public function test_someone_already_logged_in_skips_the_login_screen(): void
    {
        $this->actingAs(Client::factory()->withDniAsPassword()->create(), 'client')
            ->get(route('login'))
            ->assertRedirect(route('member.dashboard'));
    }

    public function test_staff_already_logged_in_are_sent_to_the_panel(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->get(route('login'))
            ->assertRedirect(Filament::getPanel('admin')->getUrl());
    }

    public function test_a_member_can_log_out(): void
    {
        $this->actingAs(Client::factory()->withDniAsPassword()->create(), 'client')
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest('client');
    }
}
