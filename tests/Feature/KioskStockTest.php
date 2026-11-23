<?php

namespace Tests\Feature;

use App\Enums\StockMovementTypeEnum;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Widgets\LowStockProducts;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * KIO-02: movimientos de stock, modo flexible/estricto, botones de stock y lista "Para reponer".
 */
class KioskStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_movement_updates_the_stock_and_keeps_the_history(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $movement = $product->moveStock(12, StockMovementTypeEnum::Entrada, $user, '  Remito 123 ');

        $this->assertSame(12, $product->stock);
        $this->assertSame(12, $product->refresh()->stock);
        $this->assertSame(StockMovementTypeEnum::Entrada, $movement->tipo);
        $this->assertSame(12, $movement->stock_resultante);
        $this->assertSame('Remito 123', $movement->nota);
        $this->assertTrue($movement->registradoPor->is($user));
    }

    public function test_the_stock_always_matches_the_sum_of_its_movements(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $product->moveStock(10, StockMovementTypeEnum::Entrada, $user);
        $product->moveStock(-3, StockMovementTypeEnum::Venta, $user);
        $product->adjustStockTo(5, $user, 'Conteo');
        $product->moveStock(1, StockMovementTypeEnum::Anulacion, $user);

        $this->assertSame(6, $product->refresh()->stock);
        $this->assertSame(6, (int) $product->stockMovements()->sum('cantidad'));
        $this->assertSame(-2, $product->stockMovements()->where('tipo', StockMovementTypeEnum::Ajuste)->sole()->cantidad);
    }

    public function test_selling_without_stock_leaves_it_negative_in_flexible_mode(): void
    {
        $product = Product::factory()->create();

        $product->moveStock(-2, StockMovementTypeEnum::Venta, User::factory()->create());

        $this->assertSame(-2, $product->refresh()->stock);
    }

    public function test_an_adjustment_without_difference_records_nothing(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $product->moveStock(4, StockMovementTypeEnum::Entrada, $user);

        $this->assertNull($product->adjustStockTo(4, $user, 'Conteo'));
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_zero_units_or_untracked_products_cannot_move(): void
    {
        $user = User::factory()->create();

        try {
            Product::factory()->create()->moveStock(0, StockMovementTypeEnum::Entrada, $user);
            $this->fail('Debió rechazar 0 unidades.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        Product::factory()->create(['controla_stock' => false])->moveStock(1, StockMovementTypeEnum::Entrada, $user);
    }

    public function test_the_stock_mode_is_flexible_unless_configured_strict(): void
    {
        config(['gym.kiosk.stock_mode' => 'warn']);
        $this->assertFalse(Product::stockIsStrict());

        config(['gym.kiosk.stock_mode' => 'cualquier cosa']);
        $this->assertFalse(Product::stockIsStrict());

        config(['gym.kiosk.stock_mode' => 'strict']);
        $this->assertTrue(Product::stockIsStrict());
    }

    public function test_the_shortage_counts_missing_units_only_when_stock_is_tracked(): void
    {
        $this->assertSame(0, Product::factory()->make(['stock' => 5])->stockShortage(5));
        $this->assertSame(2, Product::factory()->make(['stock' => 3])->stockShortage(5));
        $this->assertSame(5, Product::factory()->make(['stock' => -1])->stockShortage(5));
        $this->assertSame(0, Product::factory()->make(['stock' => 0, 'controla_stock' => false])->stockShortage(5));
    }

    public function test_a_profe_can_receive_goods(): void
    {
        $profe = User::factory()->create();
        $this->actingAs($profe);
        $product = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->callAction(TestAction::make('receiveStock')->table($product), ['cantidad' => 24, 'nota' => 'Proveedor'])
            ->assertHasNoFormErrors();

        $this->assertSame(24, $product->refresh()->stock);
        $this->assertTrue(StockMovement::query()->sole()->registradoPor->is($profe));
    }

    public function test_receiving_needs_a_positive_amount(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->callAction(TestAction::make('receiveStock')->table($product), ['cantidad' => 0])
            ->assertHasFormErrors(['cantidad']);

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_only_an_admin_can_adjust_and_needs_a_reason(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create());
        Livewire::test(ManageProducts::class)
            ->assertActionHidden(TestAction::make('adjustStock')->table($product));

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ManageProducts::class)
            ->callAction(TestAction::make('adjustStock')->table($product), ['contado' => 7, 'nota' => ''])
            ->assertHasFormErrors(['nota' => 'required']);

        Livewire::test(ManageProducts::class)
            ->callAction(TestAction::make('adjustStock')->table($product), ['contado' => 7, 'nota' => 'Conteo inicial'])
            ->assertHasNoFormErrors();

        $this->assertSame(7, $product->refresh()->stock);
    }

    public function test_stock_buttons_are_hidden_for_untracked_or_deleted_products(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $untracked = Product::factory()->create(['controla_stock' => false]);
        $deleted = Product::factory()->create();
        $deleted->delete();

        Livewire::test(ManageProducts::class)
            ->assertActionHidden(TestAction::make('receiveStock')->table($untracked))
            ->assertActionHidden(TestAction::make('adjustStock')->table($untracked));
    }

    public function test_the_history_is_read_only_and_can_be_filtered_by_product(): void
    {
        $user = User::factory()->create(['name' => 'Profe Ana']);
        $agua = Product::factory()->create(['nombre' => 'Agua mineral']);
        $barrita = Product::factory()->create(['nombre' => 'Barrita de cereal']);
        $deAgua = $agua->moveStock(6, StockMovementTypeEnum::Entrada, $user);
        $deBarrita = $barrita->moveStock(3, StockMovementTypeEnum::Entrada, $user);

        // Un profe abre el enlace "Movimientos" del producto.
        $this->actingAs(User::factory()->create())
            ->get(StockMovementResource::getUrl(parameters: ['filters' => ['product_id' => ['value' => $agua->getKey()]]]))
            ->assertOk()
            ->assertSee('Profe Ana');

        Livewire::test(ListStockMovements::class)
            ->filterTable('product_id', $agua->getKey())
            ->assertCanSeeTableRecords([$deAgua])
            ->assertCanNotSeeTableRecords([$deBarrita]);

        $this->assertFalse(StockMovementResource::canCreate());
        $this->assertFalse(StockMovementResource::canEdit($deAgua));
        $this->assertFalse(StockMovementResource::canDelete($deAgua));
    }

    public function test_the_restock_list_shows_only_what_needs_restocking(): void
    {
        $this->assertFalse(LowStockProducts::canView());

        $empty = Product::factory()->create(['nombre' => 'Sin stock']);
        $atMinimum = Product::factory()->create(['nombre' => 'En el mínimo', 'stock_minimo' => 3]);
        $fine = Product::factory()->create(['nombre' => 'Con stock', 'stock_minimo' => 3]);
        $inactive = Product::factory()->inactive()->create(['nombre' => 'Apagado']);
        $untracked = Product::factory()->create(['nombre' => 'Sin control', 'controla_stock' => false]);

        $user = User::factory()->create();
        $atMinimum->moveStock(3, StockMovementTypeEnum::Entrada, $user);
        $fine->moveStock(10, StockMovementTypeEnum::Entrada, $user);

        $this->assertTrue(LowStockProducts::canView());
        $this->assertEqualsCanonicalizing(
            [$empty->getKey(), $atMinimum->getKey()],
            LowStockProducts::query()->pluck('id')->all(),
        );
        $this->assertNotContains($inactive->getKey(), LowStockProducts::query()->pluck('id'));
        $this->assertNotContains($untracked->getKey(), LowStockProducts::query()->pluck('id'));
    }
}
