<?php

namespace Tests\Feature;

use App\Enums\MembershipStatusEnum;
use App\Livewire\MemberCheckIn;
use App\Models\AccessPoint;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ingreso abriendo el QR con la cámara del celular (/ingreso/{token}).
 */
class MemberCheckInTest extends TestCase
{
    use RefreshDatabase;

    private AccessPoint $accessPoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessPoint = AccessPoint::factory()->create(['nombre' => 'Mostrador']);
    }

    private function memberWithCredits(int $credits = 12): Client
    {
        $client = Client::factory()->create(['nombre' => 'Lucía', 'apellido' => 'Fernández']);
        Membership::factory()->for($client)->create(['creditos_disponibles' => $credits, 'estado' => MembershipStatusEnum::Entrenando]);

        return $client;
    }

    public function test_opening_the_link_only_asks_for_confirmation_and_never_discounts(): void
    {
        $client = $this->memberWithCredits();

        $this->actingAs($client, 'client')
            ->get($this->accessPoint->checkInUrl())
            ->assertOk()
            ->assertSee('Mostrador')
            ->assertSee('Hola, Lucía')
            ->assertSee('Registrar ingreso');

        $this->assertDatabaseCount(Attendance::class, 0);
    }

    public function test_a_link_preview_without_session_goes_to_login_and_comes_back_after_it(): void
    {
        $client = $this->memberWithCredits();
        $client->update(['password' => 'clave-propia-1']);

        $this->get($this->accessPoint->checkInUrl())->assertRedirect(route('login'));
        $this->assertDatabaseCount(Attendance::class, 0);

        $this->post(route('login.store'), ['usuario' => $client->dni, 'password' => 'clave-propia-1'])
            ->assertRedirect($this->accessPoint->checkInUrl());
    }

    public function test_confirming_registers_the_attendance_through_that_point_and_shows_it_big(): void
    {
        $client = $this->memberWithCredits(12);
        $this->travelTo('2026-11-23 18:05:00');

        Livewire::actingAs($client, 'client')
            ->test(MemberCheckIn::class, ['token' => $this->accessPoint->token])
            ->call('confirmar')
            ->assertSet('resultado', 'ok')
            ->assertSet('creditos', 11)
            ->assertSet('hora', '18:05')
            ->assertSee('Lucía Fernández')
            ->assertSee('Ingreso registrado')
            ->assertSeeHtml('border-salvia bg-salvia/20');

        $this->assertSame($this->accessPoint->id, Attendance::query()->sole()->access_point_id);
    }

    public function test_two_confirmations_within_the_window_discount_a_single_credit(): void
    {
        $client = $this->memberWithCredits(12);

        $component = Livewire::actingAs($client, 'client')
            ->test(MemberCheckIn::class, ['token' => $this->accessPoint->token])
            ->call('confirmar');

        $this->travel(3)->minutes();
        $component->call('confirmar')
            ->assertSet('resultado', 'repetido')
            ->assertSet('creditos', 11)
            ->assertSee('Ya estabas adentro');

        $this->assertDatabaseCount(Attendance::class, 1);
    }

    public function test_a_member_left_owing_credits_sees_amber_not_green(): void
    {
        $client = $this->memberWithCredits(0);

        Livewire::actingAs($client, 'client')
            ->test(MemberCheckIn::class, ['token' => $this->accessPoint->token])
            ->call('confirmar')
            ->assertSet('creditos', -1)
            ->assertSeeHtml('border-oxido bg-oxido/20')
            ->assertSee('Quedaste debiendo créditos');
    }

    public function test_an_overdue_member_is_rejected_in_amber(): void
    {
        $client = Client::factory()->create();
        Membership::factory()->for($client)->create(['estado' => MembershipStatusEnum::EnPausa]);

        Livewire::actingAs($client, 'client')
            ->test(MemberCheckIn::class, ['token' => $this->accessPoint->token])
            ->call('confirmar')
            ->assertSet('resultado', 'error')
            ->assertSee('Cuota vencida')
            ->assertDontSeeHtml('red-');

        $this->assertDatabaseCount(Attendance::class, 0);
    }

    public function test_a_turned_off_or_unknown_qr_does_not_work(): void
    {
        $client = $this->memberWithCredits();
        $this->accessPoint->update(['activo' => false]);

        $this->actingAs($client, 'client')
            ->get($this->accessPoint->checkInUrl())
            ->assertOk()
            ->assertSee('Este QR no está habilitado');

        Livewire::actingAs($client, 'client')
            ->test(MemberCheckIn::class, ['token' => $this->accessPoint->token])
            ->call('confirmar')
            ->assertSet('resultado', 'error');

        $this->assertDatabaseCount(Attendance::class, 0);
    }

    public function test_a_new_code_invalidates_the_printed_qr(): void
    {
        $client = $this->memberWithCredits();
        $oldUrl = $this->accessPoint->checkInUrl();

        $this->accessPoint->regenerateToken();

        $this->actingAs($client, 'client')->get($oldUrl)->assertSee('Este QR no está habilitado');
        $this->actingAs($client, 'client')->get($this->accessPoint->checkInUrl())->assertSee('Registrar ingreso');
    }

    public function test_too_many_confirmations_in_a_minute_are_stopped(): void
    {
        $client = $this->memberWithCredits();
        $component = Livewire::actingAs($client, 'client')
            ->test(MemberCheckIn::class, ['token' => $this->accessPoint->token]);

        foreach (range(1, MemberCheckIn::MAX_PER_MINUTE) as $attempt) {
            $component->call('confirmar');
        }

        $component->call('confirmar')
            ->assertSet('resultado', 'error')
            ->assertSee('Demasiados intentos seguidos');
    }

    public function test_the_token_cannot_be_changed_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->memberWithCredits(), 'client')
            ->test(MemberCheckIn::class, ['token' => $this->accessPoint->token])
            ->set('token', 'otro');
    }
}
