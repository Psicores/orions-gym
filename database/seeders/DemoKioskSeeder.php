<?php

namespace Database\Seeders;

use App\Enums\StockMovementTypeEnum;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Productos de ejemplo del kiosco para probar la pantalla de venta, el stock y los avisos.
 *
 * Cubre todas las situaciones: stock de sobra, en el mínimo, en cero, sin control de stock,
 * apagado, con y sin precio en efectivo, destacados y con código de barras (para probar un lector
 * USB escribiendo o escaneando el código). Solo para desarrollo: DatabaseSeeder no lo carga.
 * A mano: php artisan db:seed --class=DemoKioskSeeder
 */
class DemoKioskSeeder extends Seeder
{
    /** Prefijo del SKU de los productos de ejemplo: así se reconocen y no se duplican. */
    public const string SKU_PREFIX = 'DEMO-';

    /**
     * Carga categorías y productos de ejemplo con su stock.
     *
     * Precondiciones:
     * - Existen las tablas del kiosco y al menos una cuenta del personal (registra las entradas).
     *
     * Postcondiciones:
     * - Existen las categorías Bebidas, Suplementos, Snacks, Indumentaria y Accesorios.
     * - Existen los productos de ejemplo (SKU DEMO-…). Un producto que ya existía no se toca, así
     *   que correrlo de nuevo no duplica ni pisa cambios hechos desde el panel.
     * - El stock de cada producto nuevo entra con un movimiento de entrada, como en el panel.
     *
     * @throws RuntimeException Si no hay ninguna cuenta del personal.
     */
    public function run(): void
    {
        // Quién registra las entradas: el primer administrador, o la primera cuenta.
        $user = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();

        if ($user === null) {
            throw new RuntimeException('Creá primero una cuenta del personal (php artisan db:seed).');
        }

        // Categorías, en el orden de los botones.
        $categories = [];
        foreach (['Bebidas', 'Suplementos', 'Snacks', 'Indumentaria', 'Accesorios'] as $orden => $nombre) {
            $categories[$nombre] = ProductCategory::query()->firstOrCreate(['nombre' => $nombre], ['orden' => $orden]);
        }

        // [categoría, nombre, precio, precio en efectivo, código de barras, stock inicial, stock mínimo, destacado, controla stock, activo]
        $products = [
            // Bebidas: mucho stock, destacadas, con código para el lector.
            ['Bebidas', 'Agua mineral 500 ml', 1200, 1000, '7790315000011', 48, 12, true, true, true],
            ['Bebidas', 'Agua saborizada 500 ml', 1500, null, '7790315000028', 20, 6, false, true, true],
            ['Bebidas', 'Gatorade 500 ml', 2500, 2200, '7792170000035', 15, 6, true, true, true],
            ['Bebidas', 'Powerade 500 ml', 2400, null, '7790895000042', 6, 6, false, true, true],   // en el mínimo
            ['Bebidas', 'Speed 250 ml', 2000, 1800, '7790955000059', 0, 4, false, true, true],    // sin stock
            ['Bebidas', 'Café de máquina', 1000, null, null, 0, null, false, false, true],         // no controla stock
            // Suplementos: caros, poco stock.
            ['Suplementos', 'Proteína whey 1 kg', 38000, 35000, '7798012000066', 4, 2, true, true, true],
            ['Suplementos', 'Creatina 300 g', 24000, 22000, '7798012000073', 2, 2, false, true, true], // en el mínimo
            ['Suplementos', 'Proteína en sobre (1 toma)', 2500, null, '7798012000080', 30, 10, false, true, true],
            ['Suplementos', 'Pre-entreno 300 g', 29000, null, '7798012000097', 0, 1, false, true, true],  // sin stock
            // Snacks: baratos, se venden mucho.
            ['Snacks', 'Barrita de proteína', 1800, 1600, '7790040000103', 36, 10, true, true, true],
            ['Snacks', 'Barrita de cereal', 900, null, '7790040000110', 3, 8, false, true, true],    // por debajo del mínimo
            ['Snacks', 'Mix de frutos secos 50 g', 1600, null, '7790040000127', 12, 5, false, true, true],
            ['Snacks', 'Banana', 500, null, null, 10, null, false, true, true],
            // Indumentaria: sin código de barras, se busca por nombre.
            ['Indumentaria', 'Remera Orions', 15000, 13500, null, 8, 3, false, true, true],
            ['Indumentaria', 'Musculosa Orions', 13000, null, null, 5, 2, false, true, true],
            ['Indumentaria', 'Buzo Orions', 32000, 29000, null, 1, 2, false, true, true],            // por debajo del mínimo
            // Accesorios.
            ['Accesorios', 'Toalla de mano', 6000, null, '7791234000134', 10, 3, false, true, true],
            ['Accesorios', 'Shaker Orions', 7000, 6500, '7791234000141', 7, 2, false, true, true],
            ['Accesorios', 'Candado para locker', 4500, null, '7791234000158', 4, 2, false, true, true],
            ['Accesorios', 'Guantes de entrenamiento', 14000, null, null, 0, 1, false, true, false], // apagado: no aparece al vender
        ];

        foreach ($products as $index => [$category, $nombre, $precio, $efectivo, $barcode, $stock, $minimo, $destacado, $controla, $activo]) {
            // SKU de ejemplo, fijo por posición.
            $sku = self::SKU_PREFIX.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            // Ya existe: no se toca.
            if (Product::withTrashed()->where('sku', $sku)->exists()) {
                continue;
            }

            $product = Product::query()->create([
                'product_category_id' => $categories[$category]->id,
                'nombre' => $nombre,
                'sku' => $sku,
                'barcode' => $barcode,
                'precio' => $precio,
                'precio_efectivo' => $efectivo,
                'controla_stock' => $controla,
                'stock_minimo' => $minimo,
                'destacado' => $destacado,
                'activo' => $activo,
            ]);

            // Stock inicial como una entrada de mercadería.
            if ($controla && $stock > 0) {
                $product->moveStock($stock, StockMovementTypeEnum::Entrada, $user, 'Carga de ejemplo');
            }
        }
    }
}
