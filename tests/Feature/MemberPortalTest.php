<?php

namespace Tests\Feature;

use App\Enums\MembershipStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Livewire\MemberAccount;
use App\Livewire\MemberDashboard;
use App\Models\Announcement;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_the_state_credits_and_expiration_of_the_current_membership(): void
    {
        $client = Client::factory()->create(['nombre' => 'Juan']);
        $plan = Plan::factory()->create(['nombre' => 'Tres veces por semana', 'creditos_otorgados' => 12]);
        Membership::factory()->for($client)->for($plan)->create([
            'estado' => MembershipStatusEnum::Entrenando,
            'fecha_vencimiento' => today()->addDays(10),
        ]);

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Juan', 'Entrenando', '12', 'Créditos disponibles', 'Tres veces por semana', today()->addDays(10)->format('d/m/Y'), 'Te quedan 10 días.'])
            ->assertSee('text-salvia-light', escape: false)
            ->assertDontSee('Sin membresía vigente');
    }

    public function test_a_paused_membership_is_shown_in_the_warning_colour(): void
    {
        $client = Client::factory()->create();
        Membership::factory()->for($client)->create(['estado' => MembershipStatusEnum::EnPausa]);

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertSee('En pausa')
            ->assertSee('bg-oxido/15 text-oxido', escape: false)
            ->assertSee('Tu membresía está en pausa.');
    }

    public function test_an_unlimited_plan_shows_a_free_pass_instead_of_a_number(): void
    {
        $client = Client::factory()->create();
        Membership::factory()->for($client)->for(Plan::factory()->create(['creditos_otorgados' => 999]))->create();

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertSee('Pase libre')
            ->assertDontSeeText('999');
    }

    public function test_the_dashboard_says_when_there_is_no_current_membership(): void
    {
        $client = Client::factory()->create();
        Membership::factory()->for($client)->create(['estado' => MembershipStatusEnum::Vencida]);

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Sin membresía vigente')
            ->assertDontSee('Créditos disponibles');
    }

    public function test_the_dashboard_never_shows_another_members_membership(): void
    {
        $client = Client::factory()->create();
        Membership::factory()->for(Plan::factory()->create(['nombre' => 'Plan ajeno']))->create();

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertSee('Sin membresía vigente')
            ->assertDontSee('Plan ajeno');
    }

    public function test_a_member_whose_password_is_still_the_dni_must_change_it_before_using_the_portal(): void
    {
        $client = Client::factory()->withDniAsPassword()->create();

        foreach (['member.dashboard', 'member.history', 'member.scan'] as $route) {
            $this->actingAs($client, 'client')->get(route($route))->assertRedirect(route('member.account'));
        }

        $this->actingAs($client, 'client')
            ->get(route('member.account'))
            ->assertOk()
            ->assertSee('Antes de seguir, elegí una contraseña propia.');

        $client->password = 'fierros-2026';
        $client->save();

        $this->actingAs($client, 'client')->get(route('member.dashboard'))->assertOk();
        $this->actingAs($client, 'client')
            ->get(route('member.account'))
            ->assertDontSee('Antes de seguir, elegí una contraseña propia.');
    }

    public function test_the_dashboard_shows_the_active_announcements_with_safe_content(): void
    {
        Storage::fake('public');
        $client = Client::factory()->create();
        Announcement::factory()->create([
            'titulo' => 'Feriado del lunes',
            'contenido' => '<p onclick="robar()">Abrimos <strong>de 9 a 13</strong>.</p><script>alert(1)</script>',
            'imagen_path' => 'announcements/banner.jpg',
        ]);
        Announcement::factory()->create(['titulo' => 'Solo banner', 'contenido' => '<p></p>']);
        Announcement::factory()->inactive()->create(['titulo' => 'Anuncio apagado']);
        Announcement::factory()->scheduled()->create(['titulo' => 'Anuncio futuro']);
        Announcement::factory()->expired()->create(['titulo' => 'Anuncio terminado']);

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Feriado del lunes')
            ->assertSee('<p>Abrimos <strong>de 9 a 13</strong>.</p>', escape: false)
            ->assertSee(Storage::disk('public')->url('announcements/banner.jpg'), escape: false)
            ->assertSee('Solo banner')
            ->assertDontSee('robar()', escape: false)
            ->assertDontSee('alert(1)', escape: false)
            ->assertDontSee('Anuncio apagado')
            ->assertDontSee('Anuncio futuro')
            ->assertDontSee('Anuncio terminado');
    }

    public function test_a_member_can_dismiss_an_announcement_and_only_they_stop_seeing_it(): void
    {
        $this->freezeTime();
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $announcement = Announcement::factory()->create(['titulo' => 'Feriado del lunes']);

        Livewire::actingAs($client, 'client')
            ->test(MemberDashboard::class)
            ->assertSee('Feriado del lunes')
            ->call('dismissAnnouncement', $announcement->id)
            ->assertDontSee('Feriado del lunes');

        $this->assertDatabaseHas('announcement_client', [
            'announcement_id' => $announcement->id,
            'client_id' => $client->id,
            'read_at' => now(),
        ]);
        $this->actingAs($client, 'client')->get(route('member.dashboard'))->assertDontSee('Feriado del lunes');
        $this->actingAs($other, 'client')->get(route('member.dashboard'))->assertSee('Feriado del lunes');
    }

    public function test_dismissing_twice_or_dismissing_a_hidden_announcement_changes_nothing(): void
    {
        $client = Client::factory()->create();
        $announcement = Announcement::factory()->create();
        $hidden = Announcement::factory()->inactive()->create();
        $component = Livewire::actingAs($client, 'client')->test(MemberDashboard::class);

        $component->call('dismissAnnouncement', $announcement->id);
        $this->travel(5)->minutes();
        $firstReadAt = $client->announcements()->first()->pivot->read_at;

        $component->call('dismissAnnouncement', $announcement->id)
            ->call('dismissAnnouncement', $hidden->id)
            ->call('dismissAnnouncement', 999999)
            ->assertOk();

        $this->assertDatabaseCount('announcement_client', 1);
        $this->assertSame($firstReadAt, $client->announcements()->first()->pivot->read_at);
    }

    public function test_the_bottom_navigation_has_the_three_sections_and_marks_the_current_one(): void
    {
        $html = $this->actingAs(Client::factory()->create(), 'client')
            ->get(route('member.history'))
            ->assertOk()
            ->assertSeeInOrder(['Inicio', 'Escanear QR', 'Historial'])
            ->assertSee('rel="manifest"', escape: false)
            ->getContent();

        $this->assertSame(1, preg_match_all('/<nav.*?<\/nav>/s', $html, $nav));
        $this->assertSame(1, preg_match_all('/href="([^"]+)"[^>]*aria-current="page"/s', $nav[0][0], $current));
        $this->assertSame(route('member.history'), $current[1][0]);
    }

    public function test_the_history_lists_only_the_members_payments_newest_first(): void
    {
        $client = Client::factory()->create();
        $membership = Membership::factory()->for($client)->for(Plan::factory()->create(['nombre' => 'Plan propio']))->create();
        Payment::factory()->for($client)->for($membership)->create([
            'monto' => 25000.50,
            'metodo_pago' => PaymentMethodEnum::Transferencia,
            'fecha_pago' => '2026-10-05 10:00:00',
        ]);
        Payment::factory()->for($client)->for($membership)->create([
            'monto' => 30000,
            'metodo_pago' => PaymentMethodEnum::Efectivo,
            'fecha_pago' => '2026-11-05 10:00:00',
        ]);
        Payment::factory()->create(['monto' => 77777]);

        $this->actingAs($client, 'client')
            ->get(route('member.history'))
            ->assertOk()
            ->assertSeeInOrder(['Mis pagos', 'Mis asistencias', '05/11/2026', 'Efectivo', '$ 30.000,00', '05/10/2026', 'Transferencia', '$ 25.000,50'])
            ->assertSee('Plan propio')
            ->assertDontSee('77.777');
    }

    public function test_the_history_says_when_there_are_no_payments(): void
    {
        $this->actingAs(Client::factory()->create(), 'client')
            ->get(route('member.history'))
            ->assertSee('Todavía no tenés pagos registrados.');
    }

    public function test_the_account_screen_shows_the_members_data(): void
    {
        $client = Client::factory()->create(['nombre' => 'Juan', 'apellido' => 'Pérez', 'dni' => '30123456']);

        $this->actingAs($client, 'client')
            ->get(route('member.account'))
            ->assertOk()
            ->assertSee('Juan Pérez')
            ->assertSee('30123456')
            ->assertSee($client->email)
            ->assertSee('Cerrar sesión');
    }

    public function test_a_member_can_change_the_dni_password_for_their_own(): void
    {
        $client = Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);

        Livewire::actingAs($client, 'client')
            ->test(MemberAccount::class)
            ->set('current_password', '30123456')
            ->set('password', 'fierros-2026')
            ->set('password_confirmation', 'fierros-2026')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('saved', true)
            ->assertSet('password', '')
            ->assertSee('tu contraseña quedó cambiada');

        $client->refresh();
        $this->assertTrue(Hash::check('fierros-2026', $client->password));
        $this->assertTrue($client->passwordMatches('fierros-2026'));
        $this->assertFalse($client->passwordMatches('30123456'));
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $client = Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);

        Livewire::actingAs($client, 'client')
            ->test(MemberAccount::class)
            ->set('current_password', 'no-es')
            ->set('password', 'fierros-2026')
            ->set('password_confirmation', 'fierros-2026')
            ->call('updatePassword')
            ->assertHasErrors('current_password')
            ->assertSet('saved', false);

        $this->assertFalse($client->refresh()->hasCustomPassword());
    }

    public function test_the_new_password_must_be_confirmed_long_enough_and_different_from_the_dni(): void
    {
        $client = Client::factory()->withDniAsPassword()->create(['dni' => '30123456']);
        $component = Livewire::actingAs($client, 'client')->test(MemberAccount::class)->set('current_password', '30123456');

        $component->set('password', 'fierros-2026')->set('password_confirmation', 'otra-cosa')
            ->call('updatePassword')->assertHasErrors(['password' => 'confirmed']);

        $component->set('password', 'corta')->set('password_confirmation', 'corta')
            ->call('updatePassword')->assertHasErrors('password');

        $component->set('password', 'sololetras')->set('password_confirmation', 'sololetras')
            ->call('updatePassword')->assertHasErrors('password');

        $component->set('password', '30.123.456')->set('password_confirmation', '30.123.456')
            ->call('updatePassword')->assertHasErrors('password');

        $this->assertFalse($client->refresh()->hasCustomPassword());
    }

    public function test_the_password_never_leaves_the_model_when_serialized(): void
    {
        $client = Client::factory()->create();
        $client->password = 'fierros-2026';
        $client->save();

        $this->assertArrayNotHasKey('password', $client->toArray());
        $this->assertArrayNotHasKey('remember_token', $client->toArray());
    }

    public function test_the_manifest_makes_the_portal_installable(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#161817', $manifest['theme_color']);
        $this->assertSame(route('member.dashboard', absolute: false), $manifest['start_url']);
        $this->assertEqualsCanonicalizing(['192x192', '512x512', '512x512'], array_column($manifest['icons'], 'sizes'));

        foreach ($manifest['icons'] as $icon) {
            [$width, $height] = getimagesize(public_path($icon['src']));

            $this->assertSame($icon['sizes'], "{$width}x{$height}");
        }
    }
}
