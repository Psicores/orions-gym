<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodEnum;
use App\Enums\StockMovementTypeEnum;
use App\Filament\Pages\Kiosk;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;
use App\Services\Kiosk\SaleService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * KIO-04: pantalla de venta del kiosco.
 */
class KioskPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gym.kiosk.member_discount_percent' => 15, 'gym.kiosk.stock_mode' => 'warn']);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(int $stock = 10, array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);

        if ($stock > 0) {
            $product->moveStock($stock, StockMovementTypeEnum::Entrada, $this->user);
        }

        return $product;
    }

    public function test_a_profe_opens_the_kiosk_and_the_topbar_has_the_button(): void
    {
        $this->product(attributes: ['nombre' => 'Agua mineral']);

        $this->get(Kiosk::getUrl())->assertOk()->assertSee('Agua mineral');

        $this->get(Filament::getPanel('admin')->getUrl())
            ->assertOk()
            ->assertSee(Kiosk::getUrl(), escape: false)
            ->assertSee('Vender en el kiosco');
    }

    public function test_tapping_a_product_adds_it_and_tapping_again_adds_one_more(): void
    {
        $product = $this->product(attributes: ['precio' => 1500]);

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->call('add', $product->id)
            ->assertSet('cart', [$product->id => 2])
            ->assertSee('$ 3.000');
    }

    public function test_the_usb_reader_adds_the_product_by_barcode(): void
    {
        $product = $this->product(attributes: ['barcode' => '7790070012345']);

        Livewire::test(Kiosk::class)
            ->set('search', '7790070012345')
            ->call('addFromSearch')
            ->assertSet('cart', [$product->id => 1])
            ->assertSet('search', '')
            ->assertDispatched('kiosk-focus-search');
    }

    public function test_enter_adds_the_only_product_that_matches_the_search(): void
    {
        $agua = $this->product(attributes: ['nombre' => 'Agua mineral']);
        $this->product(attributes: ['nombre' => 'Barrita']);

        Livewire::test(Kiosk::class)
            ->set('search', 'agua')
            ->call('addFromSearch')
            ->assertSet('cart', [$agua->id => 1]);
    }

    public function test_an_unknown_code_warns_and_keeps_the_text(): void
    {
        $this->product();

        Livewire::test(Kiosk::class)
            ->set('search', '0000000000000')
            ->call('addFromSearch')
            ->assertSet('cart', [])
            ->assertSet('search', '0000000000000')
            ->assertNotified('No hay ningún producto con «0000000000000».');
    }

    public function test_inactive_products_are_not_shown_nor_added(): void
    {
        $inactive = Product::factory()->inactive()->create(['nombre' => 'Producto apagado']);

        Livewire::test(Kiosk::class)
            ->assertDontSee('Producto apagado')
            ->call('add', $inactive->id)
            ->assertSet('cart', []);
    }

    public function test_quantities_go_up_down_and_out(): void
    {
        $product = $this->product();

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->call('changeQuantity', $product->id, 2)
            ->assertSet('cart', [$product->id => 3])
            ->call('changeQuantity', $product->id, -3)
            ->assertSet('cart', []);
    }

    public function test_the_grid_filters_by_category_and_shows_featured_and_best_sellers_first(): void
    {
        $bebidas = ProductCategory::factory()->create(['nombre' => 'Bebidas']);
        $agua = $this->product(attributes: ['nombre' => 'Agua', 'product_category_id' => $bebidas->id]);
        $gatorade = $this->product(attributes: ['nombre' => 'Gatorade', 'product_category_id' => $bebidas->id]);
        $destacado = $this->product(attributes: ['nombre' => 'Zapatilla destacada', 'destacado' => true]);

        // Gatorade se vende más que Agua.
        app(SaleService::class)->checkout($this->user, [$gatorade->id => 3], [['metodo' => 'efectivo', 'monto' => $gatorade->precio * 3]]);

        $component = Livewire::test(Kiosk::class);
        $this->assertSame([$destacado->id, $gatorade->id, $agua->id], $component->instance()->products->pluck('id')->all());

        $component->set('categoryId', $bebidas->id);
        $this->assertSame([$gatorade->id, $agua->id], $component->instance()->products->pluck('id')->all());
    }

    public function test_a_member_up_to_date_gets_the_discount_and_the_sale_is_charged(): void
    {
        $client = Client::factory()->entrenando()->create(['dni' => '30111222', 'apellido' => 'Pérez']);
        $product = $this->product(attributes: ['precio' => 2000]);

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->set('clientSearch', '30111')
            ->assertSee('Pérez')
            ->call('selectClient', $client->id)
            ->assertSee('15 % de descuento de socio')
            ->set('metodo', 'transferencia')
            ->assertSee('$ 1.700')
            ->call('checkout')
            ->assertNotified()
            ->assertSet('cart', [])
            ->assertSet('clientId', null);

        $sale = Sale::query()->sole();
        $this->assertSame('1700.00', $sale->total);
        $this->assertTrue($sale->client->is($client));
        $this->assertTrue($sale->vendidoPor->is($this->user));
        $this->assertSame(PaymentMethodEnum::Transferencia, $sale->payments->sole()->metodo);
        $this->assertSame(9, $product->refresh()->stock);
    }

    public function test_a_member_not_up_to_date_is_told_there_is_no_discount(): void
    {
        $client = Client::factory()->create();
        $product = $this->product(attributes: ['precio' => 2000]);

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->call('selectClient', $client->id)
            ->assertSee('Sin descuento: no está al día')
            ->assertSee('$ 2.000');
    }

    public function test_cash_uses_the_cash_price_and_shows_the_change(): void
    {
        $product = $this->product(attributes: ['precio' => 2000, 'precio_efectivo' => 1800]);

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->set('metodo', 'efectivo')
            ->set('pagaCon', '2000')
            ->assertSee('Vuelto: $ 200')
            ->call('checkout');

        $this->assertSame('1800.00', Sale::query()->sole()->total);
    }

    public function test_mixed_payment_splits_cash_and_transfer(): void
    {
        $product = $this->product(attributes: ['precio' => 2000, 'precio_efectivo' => 1800]);

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->set('metodo', Kiosk::MIXED)
            ->set('montoEfectivo', '500')
            ->assertSee('Por transferencia: $ 1.500')
            ->call('checkout');

        $sale = Sale::query()->sole();
        $this->assertSame('2000.00', $sale->total);
        $this->assertEqualsCanonicalizing(['500.00', '1500.00'], $sale->payments->pluck('monto')->all());
    }

    public function test_a_mixed_payment_without_a_valid_cash_part_is_not_charged(): void
    {
        $product = $this->product(attributes: ['precio' => 2000]);

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->set('metodo', Kiosk::MIXED)
            ->set('montoEfectivo', '2000')
            ->call('checkout')
            ->assertNotified('No se pudo cobrar')
            ->assertSet('cart', [$product->id => 1]);

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_flexible_mode_warns_and_sells_without_stock(): void
    {
        $product = $this->product(stock: 0);

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->assertSee('Hay 0 en stock. Se vende igual.')
            ->call('checkout');

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(-1, $product->refresh()->stock);
    }

    public function test_strict_mode_warns_and_does_not_sell_without_stock(): void
    {
        config(['gym.kiosk.stock_mode' => 'strict']);
        $product = $this->product(stock: 0, attributes: ['nombre' => 'Agua']);

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->assertSee('No alcanza para vender.')
            ->call('checkout')
            ->assertNotified('No se pudo cobrar')
            ->assertSet('cart', [$product->id => 1]);

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_a_product_turned_off_while_in_the_cart_is_reported(): void
    {
        $product = $this->product(attributes: ['nombre' => 'Agua']);

        $component = Livewire::test(Kiosk::class)->call('add', $product->id);
        $product->update(['activo' => false]);

        $component->call('$refresh')
            ->assertSee('Agua no está a la venta.')
            ->call('checkout')
            ->assertNotified('No se pudo cobrar');

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_clearing_resets_the_sale(): void
    {
        $product = $this->product();
        $client = Client::factory()->create();

        Livewire::test(Kiosk::class)
            ->call('add', $product->id)
            ->call('selectClient', $client->id)
            ->set('metodo', 'transferencia')
            ->call('clear')
            ->assertSet('cart', [])
            ->assertSet('clientId', null)
            ->assertSet('metodo', 'efectivo');
    }
}
