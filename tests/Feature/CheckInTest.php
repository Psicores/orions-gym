<?php

namespace Tests\Feature;

use App\Enums\MembershipStatusEnum;
use App\Filament\Pages\CheckInQr;
use App\Livewire\MemberScan;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private const string TOKEN = 'orions-ingreso-sede-1';

    public function test_the_scan_screen_loads_the_camera_scanner(): void
    {
        $this->actingAs(Client::factory()->create(), 'client')
            ->get(route('member.scan'))
            ->assertOk()
            ->assertSee('x-data="escanerQr"', escape: false)
            ->assertSee('id="lector-qr"', escape: false)
            ->assertSee('Escanear');
    }

    /**
     * @return array<string, array{MembershipStatusEnum}>
     */
    public static function statusesThatAllowAccess(): array
    {
        return [
            'entrenando' => [MembershipStatusEnum::Entrenando],
            'activo flex' => [MembershipStatusEnum::ActivoFlex],
        ];
    }

    #[DataProvider('statusesThatAllowAccess')]
    public function test_scanning_the_gym_qr_registers_the_attendance_and_discounts_one_credit(MembershipStatusEnum $status): void
    {
        $this->freezeTime();
        $client = Client::factory()->create(['nombre' => 'Juan']);
        $membership = Membership::factory()->for($client)->create(['estado' => $status, 'creditos_disponibles' => 12]);

        Livewire::actingAs($client, 'client')
            ->test(MemberScan::class)
            ->call('procesarIngreso', self::TOKEN)
            ->assertSet('resultado', 'ok')
            ->assertSet('creditos', 11)
            ->assertSee('Ingreso registrado')
            ->assertSee('¡Buen entrenamiento, Juan!')
            ->assertDispatched('ingreso-registrado')
            ->assertNotDispatched('ingreso-rechazado');

        $this->assertDatabaseHas(Attendance::class, [
            'client_id' => $client->id,
            'membership_id' => $membership->id,
            'fecha_hora' => now(),
        ]);
        $this->assertSame(11, $membership->refresh()->creditos_disponibles);
    }

    public function test_a_member_without_credits_still_gets_in_and_the_balance_goes_negative(): void
    {
        $client = Client::factory()->create();
        $membership = Membership::factory()->for($client)->create(['creditos_disponibles' => 0]);

        Livewire::actingAs($client, 'client')
            ->test(MemberScan::class)
            ->call('procesarIngreso', self::TOKEN)
            ->assertSet('resultado', 'ok')
            ->assertSet('creditos', -1);

        $this->assertDatabaseCount(Attendance::class, 1);
        $this->assertSame(-1, $membership->refresh()->creditos_disponibles);
    }

    public function test_a_paused_membership_is_rejected_with_the_overdue_fee_alert(): void
    {
        $client = Client::factory()->create();
        $membership = Membership::factory()->for($client)->create([
            'estado' => MembershipStatusEnum::EnPausa,
            'creditos_disponibles' => 5,
        ]);

        Livewire::actingAs($client, 'client')
            ->test(MemberScan::class)
            ->call('procesarIngreso', self::TOKEN)
            ->assertSet('resultado', 'error')
            ->assertSee('Cuota vencida. Pasá por recepción.')
            ->assertDispatched('ingreso-rechazado')
            ->assertNotDispatched('ingreso-registrado');

        $this->assertDatabaseCount(Attendance::class, 0);
        $this->assertSame(5, $membership->refresh()->creditos_disponibles);
    }

    public function test_a_member_without_a_current_membership_is_rejected(): void
    {
        $client = Client::factory()->create();
        Membership::factory()->for($client)->create(['estado' => MembershipStatusEnum::Vencida]);

        Livewire::actingAs($client, 'client')
            ->test(MemberScan::class)
            ->call('procesarIngreso', self::TOKEN)
            ->assertSet('resultado', 'error')
            ->assertSee('No tenés una membresía vigente. Pasá por recepción.');

        $this->assertDatabaseCount(Attendance::class, 0);
    }

    public function test_a_qr_that_is_not_the_gyms_is_rejected_without_touching_anything(): void
    {
        $client = Client::factory()->create();
        $membership = Membership::factory()->for($client)->create(['creditos_disponibles' => 12]);

        Livewire::actingAs($client, 'client')
            ->test(MemberScan::class)
            ->call('procesarIngreso', 'https://otro-sitio.example/qr')
            ->assertSet('resultado', 'error')
            ->assertSee('Ese código no es el del gimnasio.')
            ->assertDispatched('ingreso-rechazado');

        $this->assertDatabaseCount(Attendance::class, 0);
        $this->assertSame(12, $membership->refresh()->creditos_disponibles);
    }

    public function test_the_qr_must_match_the_configured_token(): void
    {
        config(['gym.checkin_token' => 'otro-codigo']);
        $client = Client::factory()->create();
        Membership::factory()->for($client)->create();

        Livewire::actingAs($client, 'client')
            ->test(MemberScan::class)
            ->call('procesarIngreso', self::TOKEN)
            ->assertSet('resultado', 'error')
            ->call('procesarIngreso', 'otro-codigo')
            ->assertSet('resultado', 'ok');
    }

    public function test_scanning_again_within_minutes_does_not_discount_a_second_credit(): void
    {
        $this->travelTo('2026-11-12 18:30:00');
        $client = Client::factory()->create();
        $membership = Membership::factory()->for($client)->create(['creditos_disponibles' => 12]);
        $component = Livewire::actingAs($client, 'client')->test(MemberScan::class)->call('procesarIngreso', self::TOKEN);

        $this->travel(Client::ATTENDANCE_REPEAT_MINUTES - 1)->minutes();

        $component->call('procesarIngreso', self::TOKEN)
            ->assertSet('resultado', 'repetido')
            ->assertSet('creditos', 11)
            ->assertSee('Tu ingreso ya estaba registrado a las 18:30.')
            ->assertDispatched('ingreso-registrado');

        $this->assertDatabaseCount(Attendance::class, 1);
        $this->assertSame(11, $membership->refresh()->creditos_disponibles);
    }

    public function test_a_later_visit_counts_as_a_new_attendance(): void
    {
        $client = Client::factory()->create();
        $membership = Membership::factory()->for($client)->create(['creditos_disponibles' => 12]);
        $component = Livewire::actingAs($client, 'client')->test(MemberScan::class)->call('procesarIngreso', self::TOKEN);

        $this->travel(Client::ATTENDANCE_REPEAT_MINUTES + 1)->minutes();

        $component->call('procesarIngreso', self::TOKEN)->assertSet('resultado', 'ok')->assertSet('creditos', 10);

        $this->assertDatabaseCount(Attendance::class, 2);
        $this->assertSame(10, $membership->refresh()->creditos_disponibles);
    }

    public function test_the_history_lists_only_the_members_attendances_newest_first(): void
    {
        $client = Client::factory()->create();
        $membership = Membership::factory()->for($client)->create();
        Attendance::factory()->for($client)->for($membership)->create(['fecha_hora' => '2026-11-10 07:15:00']);
        Attendance::factory()->for($client)->for($membership)->create(['fecha_hora' => '2026-11-12 18:40:00']);
        Attendance::factory()->create(['fecha_hora' => '2026-11-11 23:59:00']);

        $this->actingAs($client, 'client')
            ->get(route('member.history'))
            ->assertOk()
            ->assertSeeInOrder(['Mis asistencias', '12/11/2026', '18:40', '10/11/2026', '07:15'])
            ->assertDontSee('23:59')
            ->assertDontSee('Todavía no registraste ingresos.');
    }

    public function test_the_history_says_when_there_are_no_attendances(): void
    {
        $this->actingAs(Client::factory()->create(), 'client')
            ->get(route('member.history'))
            ->assertSee('Todavía no registraste ingresos.');
    }

    public function test_staff_can_open_the_printable_reception_qr_from_the_sidebar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(CheckInQr::getUrl())
            ->assertOk()
            ->assertSee('QR de ingreso')
            ->assertSee('src="data:image/svg+xml;base64,', escape: false)
            ->assertSee(self::TOKEN)
            ->assertSee('Imprimir');
    }

    public function test_members_cannot_open_the_reception_qr_page(): void
    {
        $this->actingAs(Client::factory()->create(), 'client')
            ->get(CheckInQr::getUrl())
            ->assertRedirect(filament()->getLoginUrl());
    }
}
