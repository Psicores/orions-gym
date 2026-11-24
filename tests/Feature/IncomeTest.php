<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodEnum;
use App\Filament\Pages\Income;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Income\IncomeEntry;
use App\Services\Income\IncomeReport;
use App\Services\Kiosk\SaleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * KIO-06: ingresos consolidados de cuotas y kiosco.
 */
class IncomeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $profe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-23 18:00:00');
        $this->admin = User::factory()->admin()->create(['name' => 'Dueño']);
        $this->profe = User::factory()->create(['name' => 'Profe Ana']);
    }

    private function cuota(User $user, int $monto, PaymentMethodEnum $metodo, string $fecha = '2026-11-23 10:00:00'): Payment
    {
        return Payment::factory()->create([
            'monto' => $monto,
            'metodo_pago' => $metodo,
            'fecha_pago' => $fecha,
            'registrado_por_user_id' => $user->id,
        ]);
    }

    private function venta(User $user, int $monto, PaymentMethodEnum $metodo): Sale
    {
        $product = Product::factory()->create(['precio' => $monto, 'controla_stock' => false]);

        return app(SaleService::class)->checkout($user, [$product->id => 1], [['metodo' => $metodo, 'monto' => $monto]]);
    }

    public function test_the_report_joins_fees_and_kiosk_by_method(): void
    {
        $this->cuota($this->admin, 30000, PaymentMethodEnum::Efectivo);
        $this->cuota($this->profe, 25000, PaymentMethodEnum::Transferencia);
        $this->venta($this->profe, 1500, PaymentMethodEnum::Efectivo);
        $this->venta($this->admin, 2000, PaymentMethodEnum::Transferencia);

        $report = app(IncomeReport::class);
        $totals = $report->byOriginAndMethod($report->entries(CarbonImmutable::today(), CarbonImmutable::today()));

        $this->assertSame(3000000, $totals[IncomeEntry::CUOTA]['efectivo']);
        $this->assertSame(2500000, $totals[IncomeEntry::CUOTA]['transferencia']);
        $this->assertSame(150000, $totals[IncomeEntry::KIOSCO]['efectivo']);
        $this->assertSame(200000, $totals[IncomeEntry::KIOSCO]['transferencia']);
        $this->assertSame(3150000, $totals['total']['efectivo']);
        $this->assertSame(5850000, $totals['total']['total']);
    }

    public function test_voided_sales_and_other_days_do_not_count(): void
    {
        $voided = $this->venta($this->admin, 5000, PaymentMethodEnum::Efectivo);
        app(SaleService::class)->void($voided, $this->admin, 'Error');
        $this->cuota($this->admin, 30000, PaymentMethodEnum::Efectivo, '2026-11-22 23:59:00');
        $this->venta($this->admin, 1000, PaymentMethodEnum::Efectivo);

        $report = app(IncomeReport::class);
        $entries = $report->entries(CarbonImmutable::today(), CarbonImmutable::today());

        $this->assertCount(1, $entries);
        $this->assertSame(100000, $report->byOriginAndMethod($entries)['total']['total']);
    }

    public function test_totals_by_person_and_by_day(): void
    {
        $this->cuota($this->profe, 20000, PaymentMethodEnum::Efectivo, '2026-11-22 09:00:00');
        $this->cuota($this->profe, 10000, PaymentMethodEnum::Transferencia);
        $this->venta($this->admin, 1500, PaymentMethodEnum::Efectivo);

        $report = app(IncomeReport::class);
        $entries = $report->entries(CarbonImmutable::parse('2026-11-22'), CarbonImmutable::today());

        $byUser = collect($report->byUser($entries))->keyBy('name');
        $this->assertSame(3000000, $byUser['Profe Ana']['total']);
        $this->assertSame(3000000, $byUser['Profe Ana']['cuota']);
        $this->assertSame(150000, $byUser['Dueño']['kiosco']);

        $byDay = $report->byDay($entries);
        $this->assertSame(['2026-11-23', '2026-11-22'], array_column($byDay, 'day'));
        $this->assertSame(1150000, $byDay[0]['total']);
    }

    public function test_an_admin_sees_everyone_and_can_filter_by_person(): void
    {
        $this->actingAs($this->admin);
        $client = Client::factory()->create(['apellido' => 'Gómez', 'nombre' => 'Laura']);
        Payment::factory()->create(['client_id' => $client->id, 'monto' => 30000, 'metodo_pago' => PaymentMethodEnum::Efectivo, 'fecha_pago' => now(), 'registrado_por_user_id' => $this->profe->id]);
        $this->venta($this->admin, 1500, PaymentMethodEnum::Transferencia);

        $this->get(Income::getUrl())
            ->assertOk()
            ->assertSee('Gómez, Laura')
            ->assertSee('Por persona')
            ->assertSee('$ 31.500');

        Livewire::test(Income::class)
            ->set('persona', $this->profe->id)
            ->assertSee('Gómez, Laura')
            ->assertDontSee('Venta #')
            ->assertSee('$ 30.000');
    }

    public function test_a_profe_only_sees_what_they_charged(): void
    {
        $this->actingAs($this->profe);
        $this->cuota($this->profe, 10000, PaymentMethodEnum::Efectivo);
        $this->cuota($this->admin, 99000, PaymentMethodEnum::Efectivo);

        Livewire::test(Income::class)
            // Aunque intente elegir a otra persona.
            ->set('persona', $this->admin->id)
            ->assertSee('Lo que cobraste vos')
            ->assertSee('$ 10.000')
            ->assertDontSee('$ 99.000')
            ->assertDontSee('Por persona');
    }

    public function test_period_shortcuts_and_invalid_dates(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Income::class)
            ->assertSet('desde', '2026-11-23')
            ->call('period', 'mes')
            ->assertSet('desde', '2026-11-01')
            ->assertSet('hasta', '2026-11-23')
            ->call('period', 'ayer')
            ->assertSet('desde', '2026-11-22')
            ->assertSet('hasta', '2026-11-22');

        $page = new Income;
        $page->desde = 'cualquier cosa';
        $page->hasta = '2026-11-20';
        [$from, $to] = $page->range();
        $this->assertSame('2026-11-20', $from->toDateString());
        $this->assertSame('2026-11-23', $to->toDateString());
    }
}
