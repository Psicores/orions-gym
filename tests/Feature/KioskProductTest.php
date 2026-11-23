<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodEnum;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * KIO-01: productos y categorías del kiosco, precios con descuento de socio y permisos.
 */
class KioskProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_price_is_charged_by_default(): void
    {
        $product = Product::factory()->create(['precio' => 2000, 'precio_efectivo' => 1800]);

        $this->assertSame('2000.00', $product->priceFor(PaymentMethodEnum::Transferencia));
        $this->assertSame('2000.00', $product->priceFor(null));
    }

    public function test_paying_cash_uses_the_cash_price(): void
    {
        $product = Product::factory()->create(['precio' => 2000, 'precio_efectivo' => 1800]);

        $this->assertSame('1800.00', $product->priceFor(PaymentMethodEnum::Efectivo));
        $this->assertSame('1800.00', $product->priceFor('efectivo'));
    }

    public function test_members_get_the_configured_discount(): void
    {
        config(['gym.kiosk.member_discount_percent' => 15]);
        $product = Product::factory()->create(['precio' => 2000]);

        $this->assertSame('1700.00', $product->priceFor(PaymentMethodEnum::Transferencia, esSocio: true));
    }

    public function test_discounts_do_not_add_up_the_cheapest_wins(): void
    {
        config(['gym.kiosk.member_discount_percent' => 15]);

        // Socio en efectivo: 15% (1700) es mejor que el precio en efectivo (1900).
        $product = Product::factory()->create(['precio' => 2000, 'precio_efectivo' => 1900]);
        $this->assertSame('1700.00', $product->priceFor(PaymentMethodEnum::Efectivo, esSocio: true));

        // Socio en efectivo: el precio en efectivo (1500) es mejor que el 15% (1700).
        $product = Product::factory()->create(['precio' => 2000, 'precio_efectivo' => 1500]);
        $this->assertSame('1500.00', $product->priceFor(PaymentMethodEnum::Efectivo, esSocio: true));
    }

    public function test_the_member_discount_rounds_to_the_cent_and_can_be_turned_off(): void
    {
        $product = Product::factory()->create(['precio' => 999.99]);

        config(['gym.kiosk.member_discount_percent' => 15]);
        $this->assertSame('849.99', $product->priceFor(null, esSocio: true));

        config(['gym.kiosk.member_discount_percent' => 0]);
        $this->assertSame('999.99', $product->priceFor(null, esSocio: true));
    }

    public function test_codes_are_saved_without_spaces_and_empty_codes_do_not_collide(): void
    {
        $product = Product::factory()->create(['barcode' => ' 7790 0700 12345 ', 'sku' => '']);
        Product::factory()->create(['barcode' => '', 'sku' => null]);

        $this->assertSame('7790070012345', $product->refresh()->barcode);
        $this->assertNull($product->sku);
        $this->assertSame(2, Product::query()->whereNull('sku')->count());
    }

    public function test_low_stock_only_counts_when_stock_is_tracked_and_has_a_minimum(): void
    {
        $this->assertTrue(Product::factory()->make(['stock' => 2, 'stock_minimo' => 2])->hasLowStock());
        $this->assertFalse(Product::factory()->make(['stock' => 3, 'stock_minimo' => 2])->hasLowStock());
        $this->assertFalse(Product::factory()->make(['stock' => 0, 'stock_minimo' => null])->hasLowStock());
        $this->assertFalse(Product::factory()->make(['stock' => 0, 'stock_minimo' => 5, 'controla_stock' => false])->hasLowStock());
    }

    public function test_an_admin_creates_a_product_with_its_category(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $category = ProductCategory::factory()->create(['nombre' => 'Bebidas']);

        Livewire::test(ManageProducts::class)
            ->callAction(CreateAction::class, [
                'nombre' => 'Agua 500 ml',
                'product_category_id' => $category->getKey(),
                'precio' => 1500,
                'precio_efectivo' => 1300,
                'barcode' => '7790070012345',
                'controla_stock' => true,
                'stock_minimo' => 6,
                'destacado' => true,
                'activo' => true,
            ])
            ->assertHasNoFormErrors();

        $product = Product::query()->sole();
        $this->assertSame('Agua 500 ml', $product->nombre);
        $this->assertTrue($product->category->is($category));
        $this->assertSame('1300.00', $product->precio_efectivo);
        $this->assertSame(0, $product->stock);
        $this->assertTrue($product->destacado);
    }

    public function test_a_barcode_cannot_be_repeated_even_with_a_deleted_product(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Product::factory()->create(['barcode' => '7790070012345'])->delete();

        Livewire::test(ManageProducts::class)
            ->callAction(CreateAction::class, ['nombre' => 'Otro', 'precio' => 100, 'barcode' => '7790070012345'])
            ->assertHasFormErrors(['barcode' => 'unique']);

        $this->assertSame(1, Product::withTrashed()->count());
    }

    public function test_name_and_price_are_required(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageProducts::class)
            ->callAction(CreateAction::class, ['nombre' => '', 'precio' => null])
            ->assertHasFormErrors(['nombre' => 'required', 'precio' => 'required']);
    }

    public function test_a_profe_sees_products_but_cannot_change_them(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['nombre' => 'Barrita de proteína']);

        $this->get(ProductResource::getUrl())->assertOk()->assertSee('Barrita de proteína');

        Livewire::test(ManageProducts::class)
            ->assertActionHidden(CreateAction::class)
            ->assertActionHidden(TestAction::make(EditAction::class)->table($product))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($product));
    }

    public function test_an_admin_sees_the_edit_and_delete_buttons(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->assertActionVisible(CreateAction::class)
            ->assertActionVisible(TestAction::make(EditAction::class)->table($product))
            ->assertActionVisible(TestAction::make(DeleteAction::class)->table($product));
    }

    public function test_categories_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ProductCategoryResource::getUrl())
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(ProductCategoryResource::getUrl())
            ->assertOk();
    }

    public function test_deleting_a_category_leaves_its_products_without_category(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['product_category_id' => $category->getKey()]);

        $category->delete();

        $this->assertNull($product->refresh()->product_category_id);
    }
}
