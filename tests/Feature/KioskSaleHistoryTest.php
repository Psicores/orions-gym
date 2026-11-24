<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodEnum;
use App\Enums\SalePaymentStatusEnum;
use App\Enums\SaleStatusEnum;
use App\Enums\StockMovementTypeEnum;
use App\Exceptions\SaleException;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Kiosk\SaleService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * KIO-05: historial de ventas y anulación.
 */
class KioskSaleHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gym.kiosk.stock_mode' => 'warn']);
        $this->admin = User::factory()->admin()->create();
    }

    private function sell(Product $product, int $cantidad, PaymentMethodEnum $metodo = PaymentMethodEnum::Efectivo, ?User $seller = null): Sale
    {
        return app(SaleService::class)->checkout(
            $seller ?? $this->admin,
            [$product->id => $cantidad],
            [['metodo' => $metodo, 'monto' => $product->precio * $cantidad]],
        );
    }

    private function productWithStock(int $stock, array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);
        $product->moveStock($stock, StockMovementTypeEnum::Entrada, $this->admin);

        return $product;
    }

    public function test_voiding_returns_the_stock_and_refunds_the_payments(): void
    {
        $product = $this->productWithStock(10, ['precio' => 1000]);
        $sale = $this->sell($product, 3);

        $voided = app(SaleService::class)->void($sale, $this->admin, '  Se cobró dos veces ');

        $this->assertSame(SaleStatusEnum::Anulada, $voided->estado);
        $this->assertSame('Se cobró dos veces', $voided->motivo_anulacion);
        $this->assertTrue($voided->anuladaPor->is($this->admin));
        $this->assertNotNull($voided->anulada_at);
        $this->assertSame(SalePaymentStatusEnum::Reintegrado, $voided->payments->sole()->estado);
        $this->assertSame(10, $product->refresh()->stock);

        $movement = StockMovement::query()->where('tipo', StockMovementTypeEnum::Anulacion)->sole();
        $this->assertSame(3, $movement->cantidad);
        $this->assertSame($sale->items->sole()->id, $movement->sale_item_id);
    }

    public function test_a_sale_cannot_be_voided_twice_nor_without_a_reason(): void
    {
        $product = $this->productWithStock(5, ['precio' => 1000]);
        $sale = $this->sell($product, 1);

        try {
            app(SaleService::class)->void($sale, $this->admin, '   ');
            $this->fail('Debió pedir el motivo.');
        } catch (SaleException) {
        }

        app(SaleService::class)->void($sale, $this->admin, 'Error');

        $this->expectException(SaleException::class);
        try {
            app(SaleService::class)->void(Sale::query()->find($sale->id), $this->admin, 'Otra vez');
        } finally {
            $this->assertSame(5, $product->refresh()->stock);
        }
    }

    public function test_voiding_a_sale_of_an_untracked_product_moves_no_stock(): void
    {
        $product = Product::factory()->create(['precio' => 500, 'controla_stock' => false]);
        $sale = $this->sell($product, 2);

        app(SaleService::class)->void($sale, $this->admin, 'Devolución');

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_voiding_works_even_if_the_product_was_deleted_after(): void
    {
        $product = $this->productWithStock(5, ['precio' => 1000]);
        $sale = $this->sell($product, 2);
        $product->delete();

        app(SaleService::class)->void($sale, $this->admin, 'Devolución');

        $this->assertSame(5, Product::withTrashed()->find($product->id)->stock);
    }

    public function test_an_admin_voids_from_the_history(): void
    {
        $this->actingAs($this->admin);
        $product = $this->productWithStock(5, ['precio' => 1000]);
        $sale = $this->sell($product, 1);

        Livewire::test(ListSales::class)
            ->assertActionVisible(TestAction::make('void')->table($sale))
            ->callAction(TestAction::make('void')->table($sale), ['motivo' => ''])
            ->assertHasFormErrors(['motivo' => 'required']);

        Livewire::test(ListSales::class)
            ->callAction(TestAction::make('void')->table($sale), ['motivo' => 'Devolvió el producto'])
            ->assertHasNoFormErrors()
            ->assertNotified('Venta #'.$sale->id.' anulada');

        $this->assertSame(SaleStatusEnum::Anulada, $sale->refresh()->estado);
        $this->assertSame(5, $product->refresh()->stock);

        // Ya anulada, el botón no aparece.
        Livewire::test(ListSales::class)
            ->assertActionHidden(TestAction::make('void')->table($sale));
    }

    public function test_a_profe_sees_the_history_and_the_detail_but_cannot_void(): void
    {
        $profe = User::factory()->create();
        $this->actingAs($profe);
        $product = $this->productWithStock(5, ['nombre' => 'Agua mineral', 'precio' => 1000]);
        $sale = $this->sell($product, 2, seller: $profe);

        $this->get(SaleResource::getUrl())->assertOk()->assertSee('2× Agua mineral');

        Livewire::test(ListSales::class)
            ->assertActionHidden(TestAction::make('void')->table($sale))
            ->mountAction(TestAction::make('detail')->table($sale))
            ->assertSee('Agua mineral')
            ->assertSee('Efectivo');

        $this->assertFalse(SaleResource::canCreate());
    }

    public function test_the_history_filters_by_state_and_payment_method(): void
    {
        $this->actingAs($this->admin);
        $product = $this->productWithStock(10, ['precio' => 1000]);
        $cash = $this->sell($product, 1, PaymentMethodEnum::Efectivo);
        $transfer = $this->sell($product, 1, PaymentMethodEnum::Transferencia);
        $voided = $this->sell($product, 1, PaymentMethodEnum::Efectivo);
        app(SaleService::class)->void($voided, $this->admin, 'Error');

        Livewire::test(ListSales::class)
            ->filterTable('metodo', PaymentMethodEnum::Transferencia->value)
            ->assertCanSeeTableRecords([$transfer])
            ->assertCanNotSeeTableRecords([$cash, $voided]);

        Livewire::test(ListSales::class)
            ->filterTable('estado', SaleStatusEnum::Anulada->value)
            ->assertCanSeeTableRecords([$voided])
            ->assertCanNotSeeTableRecords([$cash, $transfer]);
    }

    public function test_the_history_filters_by_date(): void
    {
        $this->actingAs($this->admin);
        $product = $this->productWithStock(10, ['precio' => 1000]);

        $this->travelTo('2026-11-20 10:00:00');
        $old = $this->sell($product, 1);
        $this->travelTo('2026-11-23 18:00:00');
        $today = $this->sell($product, 1);

        Livewire::test(ListSales::class)
            ->filterTable('fecha', ['desde' => '2026-11-23', 'hasta' => '2026-11-23'])
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_the_footer_total_excludes_voided_sales(): void
    {
        $this->actingAs($this->admin);
        $product = $this->productWithStock(10, ['precio' => 1000]);
        $this->sell($product, 2);
        $voided = $this->sell($product, 3);
        app(SaleService::class)->void($voided, $this->admin, 'Error');

        // 2.000 cobrados; la anulada (3.000) no suma, así que nunca aparece 5.000.
        Livewire::test(ListSales::class)
            ->assertSee('Cobrado')
            ->assertSee('2.000,00')
            ->assertDontSee('5.000,00');
    }
}
