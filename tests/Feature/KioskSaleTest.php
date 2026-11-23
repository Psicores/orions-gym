<?php

namespace Tests\Feature;

use App\Enums\ClientStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\SalePaymentStatusEnum;
use App\Enums\SaleStatusEnum;
use App\Enums\StockMovementTypeEnum;
use App\Exceptions\SaleException;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Kiosk\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * KIO-03: ventas del kiosco (SaleService), precios, stock y cobros.
 */
class KioskSaleTest extends TestCase
{
    use RefreshDatabase;

    private SaleService $service;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gym.kiosk.member_discount_percent' => 15, 'gym.kiosk.stock_mode' => 'warn']);
        $this->service = app(SaleService::class);
        $this->seller = User::factory()->create();
    }

    /**
     * Producto con stock cargado por una entrada.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function productWithStock(int $stock, array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);

        if ($stock > 0) {
            $product->moveStock($stock, StockMovementTypeEnum::Entrada, $this->seller);
        }

        return $product;
    }

    /**
     * @return list<array{metodo: PaymentMethodEnum, monto: string}>
     */
    private function pay(PaymentMethodEnum $metodo, string $monto): array
    {
        return [['metodo' => $metodo, 'monto' => $monto]];
    }

    public function test_a_counter_sale_saves_items_payments_and_moves_stock(): void
    {
        $agua = $this->productWithStock(10, ['nombre' => 'Agua', 'precio' => 1500]);
        $barrita = $this->productWithStock(5, ['nombre' => 'Barrita', 'precio' => 2000]);

        $sale = $this->service->checkout($this->seller, [$agua->id => 2, $barrita->id => 1], $this->pay(PaymentMethodEnum::Transferencia, '5000'));

        $this->assertSame(SaleStatusEnum::Pagada, $sale->estado);
        $this->assertNull($sale->client_id);
        $this->assertTrue($sale->vendidoPor->is($this->seller));
        $this->assertSame('5000.00', $sale->subtotal);
        $this->assertSame('0.00', $sale->descuento);
        $this->assertSame('5000.00', $sale->total);
        $this->assertCount(2, $sale->items);
        $this->assertSame('3000.00', $sale->items->firstWhere('product_id', $agua->id)->subtotal);

        $payment = $sale->payments->sole();
        $this->assertSame(SalePaymentStatusEnum::Aprobado, $payment->estado);
        $this->assertSame('5000.00', $payment->monto);
        $this->assertNotNull($payment->pagado_at);
        $this->assertNull($payment->proveedor);

        $this->assertSame(8, $agua->refresh()->stock);
        $this->assertSame(4, $barrita->refresh()->stock);
        $movement = StockMovement::query()->where('tipo', StockMovementTypeEnum::Venta)->where('product_id', $agua->id)->sole();
        $this->assertSame(-2, $movement->cantidad);
        $this->assertSame($sale->items->firstWhere('product_id', $agua->id)->id, $movement->sale_item_id);
    }

    public function test_a_member_up_to_date_gets_the_discount_and_it_is_recorded(): void
    {
        $client = Client::factory()->entrenando()->create();
        $product = $this->productWithStock(5, ['precio' => 2000]);

        $sale = $this->service->checkout($this->seller, [$product->id => 2], $this->pay(PaymentMethodEnum::Transferencia, '3400'), $client);

        $this->assertTrue($sale->client->is($client));
        $this->assertSame('4000.00', $sale->subtotal);
        $this->assertSame('600.00', $sale->descuento);
        $this->assertSame('3400.00', $sale->total);
        $this->assertSame('2000.00', $sale->items->sole()->precio_lista);
        $this->assertSame('1700.00', $sale->items->sole()->precio_unitario);
    }

    public function test_the_cash_price_applies_only_when_everything_is_paid_in_cash(): void
    {
        $product = $this->productWithStock(5, ['precio' => 2000, 'precio_efectivo' => 1800]);

        $cash = $this->service->checkout($this->seller, [$product->id => 1], $this->pay(PaymentMethodEnum::Efectivo, '1800'));
        $this->assertSame('1800.00', $cash->total);

        $mixed = $this->service->checkout($this->seller, [$product->id => 1], [
            ['metodo' => PaymentMethodEnum::Efectivo, 'monto' => '1000'],
            ['metodo' => 'transferencia', 'monto' => '1000'],
        ]);
        $this->assertSame('2000.00', $mixed->total);
        $this->assertCount(2, $mixed->payments);
    }

    public function test_payments_must_add_up_to_the_total_and_nothing_is_saved_otherwise(): void
    {
        $product = $this->productWithStock(5, ['precio' => 2000]);

        try {
            $this->service->checkout($this->seller, [$product->id => 1], $this->pay(PaymentMethodEnum::Efectivo, '1500'));
            $this->fail('Debió rechazar cobros que no suman el total.');
        } catch (SaleException $exception) {
            $this->assertStringContainsString('$ 1.500,00', $exception->getMessage());
            $this->assertStringContainsString('$ 2.000,00', $exception->getMessage());
        }

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(5, $product->refresh()->stock);
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_flexible_mode_sells_without_stock_and_leaves_it_negative(): void
    {
        $product = $this->productWithStock(1, ['precio' => 1000]);

        $quote = $this->service->quote([$product->id => 3]);
        $this->assertSame(2, $quote->shortages()[0]->faltante);

        $this->service->checkout($this->seller, [$product->id => 3], $this->pay(PaymentMethodEnum::Efectivo, '3000'));

        $this->assertSame(-2, $product->refresh()->stock);
    }

    public function test_strict_mode_does_not_sell_what_is_missing(): void
    {
        config(['gym.kiosk.stock_mode' => 'strict']);
        $product = $this->productWithStock(1, ['nombre' => 'Agua', 'precio' => 1000]);

        try {
            $this->service->checkout($this->seller, [$product->id => 2], $this->pay(PaymentMethodEnum::Efectivo, '2000'));
            $this->fail('Debió rechazar la venta sin stock.');
        } catch (SaleException $exception) {
            $this->assertSame('No hay stock suficiente de: Agua (hay 1).', $exception->getMessage());
        }

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(1, $product->refresh()->stock);

        // La última unidad sí se vende.
        $this->service->checkout($this->seller, [$product->id => 1], $this->pay(PaymentMethodEnum::Efectivo, '1000'));
        $this->assertSame(0, $product->refresh()->stock);
    }

    public function test_untracked_products_sell_without_stock_movements(): void
    {
        config(['gym.kiosk.stock_mode' => 'strict']);
        $product = Product::factory()->create(['precio' => 500, 'controla_stock' => false]);

        $this->service->checkout($this->seller, [$product->id => 4], $this->pay(PaymentMethodEnum::Efectivo, '2000'));

        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, $product->refresh()->stock);
    }

    public function test_the_sale_keeps_the_price_and_name_it_was_sold_at(): void
    {
        $product = $this->productWithStock(5, ['nombre' => 'Agua', 'precio' => 1000]);
        $sale = $this->service->checkout($this->seller, [$product->id => 1], $this->pay(PaymentMethodEnum::Efectivo, '1000'));

        $product->update(['nombre' => 'Agua grande', 'precio' => 1500]);

        $item = $sale->items->sole()->refresh();
        $this->assertSame('Agua', $item->nombre);
        $this->assertSame('1000.00', $item->precio_unitario);
    }

    public function test_inactive_or_deleted_products_cannot_be_sold(): void
    {
        $inactive = Product::factory()->inactive()->create(['nombre' => 'Apagado']);
        $deleted = Product::factory()->create();
        $deleted->delete();

        try {
            $this->service->quote([$inactive->id => 1]);
            $this->fail('Debió rechazar el producto apagado.');
        } catch (SaleException $exception) {
            $this->assertSame('Apagado no está a la venta.', $exception->getMessage());
        }

        $this->expectException(SaleException::class);
        $this->service->quote([$deleted->id => 1]);
    }

    /**
     * @return array<string, array{array<int|string, mixed>}>
     */
    public static function invalidCarts(): array
    {
        return [
            'vacío' => [[]],
            'cero unidades' => [[1 => 0]],
            'negativo' => [[1 => -1]],
            'demasiadas' => [[1 => 1000]],
            'decimal' => [[1 => '1.5']],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $cart
     */
    #[DataProvider('invalidCarts')]
    public function test_invalid_carts_are_rejected(array $cart): void
    {
        Product::factory()->create();

        $this->expectException(SaleException::class);
        $this->service->quote($cart);
    }

    public function test_invalid_payments_are_rejected(): void
    {
        $product = $this->productWithStock(5, ['precio' => 1000]);

        foreach ([[], [['metodo' => 'bitcoin', 'monto' => 1000]], [['metodo' => 'efectivo', 'monto' => 0]], [['metodo' => 'efectivo', 'monto' => 'mil']]] as $payments) {
            try {
                $this->service->checkout($this->seller, [$product->id => 1], $payments);
                $this->fail('Debió rechazar los cobros: '.json_encode($payments));
            } catch (SaleException) {
            }
        }

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_a_deleted_member_cannot_buy_as_member(): void
    {
        $client = Client::factory()->create();
        $client->delete();
        $product = $this->productWithStock(5, ['precio' => 1000]);

        $this->expectException(SaleException::class);
        $this->service->checkout($this->seller, [$product->id => 1], $this->pay(PaymentMethodEnum::Efectivo, '850'), $client);
    }

    /**
     * @return array<string, array{ClientStatusEnum}>
     */
    public static function statusesWithoutDiscount(): array
    {
        return [
            'en pausa' => [ClientStatusEnum::EnPausa],
            'activo temporal' => [ClientStatusEnum::ActivoTemporal],
            'inactivo' => [ClientStatusEnum::Inactivo],
        ];
    }

    #[DataProvider('statusesWithoutDiscount')]
    public function test_a_member_not_up_to_date_pays_list_price_but_the_sale_stays_linked(ClientStatusEnum $estado): void
    {
        $client = Client::factory()->create(['estado' => $estado]);
        $product = $this->productWithStock(5, ['precio' => 2000]);

        $sale = $this->service->checkout($this->seller, [$product->id => 1], $this->pay(PaymentMethodEnum::Transferencia, '2000'), $client);

        $this->assertSame('2000.00', $sale->total);
        $this->assertSame('0.00', $sale->descuento);
        $this->assertTrue($sale->client->is($client));
    }

    public function test_the_member_discount_rounds_per_unit_and_totals_stay_consistent(): void
    {
        $client = Client::factory()->entrenando()->create();
        $product = $this->productWithStock(10, ['precio' => 999.99]);

        $sale = $this->service->checkout($this->seller, [$product->id => 3], $this->pay(PaymentMethodEnum::Transferencia, '2549.97'), $client);

        $this->assertSame('849.99', $sale->items->sole()->precio_unitario);
        $this->assertSame('2549.97', $sale->total);
        $this->assertSame('2999.97', $sale->subtotal);
        $this->assertSame('450.00', $sale->descuento);
    }
}
