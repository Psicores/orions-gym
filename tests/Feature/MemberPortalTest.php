<?php

namespace Tests\Feature;

use App\Enums\MembershipStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Livewire\MemberAccount;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            ->assertDontSee('999');
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

    public function test_the_dashboard_suggests_changing_the_password_only_while_it_is_the_dni(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertSee('Tu contraseña todavía es tu DNI.');

        $client->password = 'fierros-2026';
        $client->save();

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertDontSee('Tu contraseña todavía es tu DNI.');
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
        $client = Client::factory()->create(['dni' => '30123456']);

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
        $client = Client::factory()->create(['dni' => '30123456']);

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
        $client = Client::factory()->create(['dni' => '30123456']);
        $component = Livewire::actingAs($client, 'client')->test(MemberAccount::class)->set('current_password', '30123456');

        $component->set('password', 'fierros-2026')->set('password_confirmation', 'otra-cosa')
            ->call('updatePassword')->assertHasErrors(['password' => 'confirmed']);

        $component->set('password', 'corta')->set('password_confirmation', 'corta')
            ->call('updatePassword')->assertHasErrors(['password' => 'min']);

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
