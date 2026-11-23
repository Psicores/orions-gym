<?php

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Producto del kiosco (bebida, suplemento, ropa...).
 *
 * precio es el de lista; precio_efectivo (opcional) es el precio pagando en efectivo, como en los
 * planes. A los socios se les descuenta config('gym.kiosk.member_discount_percent') sobre el
 * precio de lista; los descuentos no se suman: se cobra el más barato (ver priceFor()).
 *
 * stock solo se mueve con movimientos de stock (ver KIO-02), no desde el formulario. Puede quedar
 * negativo si se vende sin stock en modo flexible.
 *
 * Usa baja lógica: un producto dado de baja no se vende más, pero las ventas viejas lo conservan.
 */
#[Fillable([
    'product_category_id', 'nombre', 'sku', 'barcode', 'precio', 'precio_efectivo', 'costo',
    'controla_stock', 'stock_minimo', 'imagen_path', 'destacado', 'activo',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas.
     *
     * Precondiciones:
     * - Las columnas existen según la migración create_products_table.
     *
     * Postcondiciones:
     * - Los importes se leen como string con 2 decimales (sin errores de punto flotante); stock y
     *   stock_minimo como int; los interruptores como bool.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Precio de lista, exacto.
            'precio' => 'decimal:2',
            // Precio en efectivo; null si no tiene.
            'precio_efectivo' => 'decimal:2',
            // Costo; null si no se cargó.
            'costo' => 'decimal:2',
            // Si se cuenta el stock.
            'controla_stock' => 'boolean',
            // Unidades disponibles (puede ser negativo).
            'stock' => 'integer',
            // Umbral de stock bajo.
            'stock_minimo' => 'integer',
            // Aparece primero al vender.
            'destacado' => 'boolean',
            // Se ofrece para vender.
            'activo' => 'boolean',
        ];
    }

    /**
     * Normaliza el código de barras y el SKU antes de guardar: sin espacios y vacío = null.
     *
     * Precondiciones:
     * - Ninguna: Eloquent lo llama una vez al cargar el modelo.
     *
     * Postcondiciones:
     * - Al guardar, barcode y sku quedan sin espacios en ningún lado, o en null si quedan vacíos
     *   (así dos productos sin código no chocan con la restricción de único).
     */
    protected static function booted(): void
    {
        // Antes de cada alta o edición.
        static::saving(function (Product $product): void {
            // Los dos códigos se tratan igual.
            foreach (['barcode', 'sku'] as $column) {
                // Sin espacios (un lector o un pegado pueden traerlos).
                $value = preg_replace('/\s+/', '', (string) $product->{$column});
                // Vacío se guarda como null.
                $product->{$column} = $value === '' ? null : $value;
            }
        });
    }

    /**
     * Precio que corresponde cobrar según el medio de pago y si el comprador es socio.
     *
     * Precondiciones:
     * - El producto tiene precio cargado; precio_efectivo puede estar vacío.
     * - $metodoPago es un caso del enum, su valor de texto o null.
     *
     * Postcondiciones:
     * - Arranca del precio de lista.
     * - Si es socio, calcula el precio de lista con el descuento de socios de la configuración
     *   (redondeado al centavo) y se queda con el menor.
     * - Si el medio es efectivo y hay precio_efectivo, se queda con el menor entre lo anterior y
     *   ese precio. Los descuentos no se suman.
     * - Devuelve un string con 2 decimales (por ejemplo "1700.00"). No modifica nada.
     *
     * @param  PaymentMethodEnum|string|null  $metodoPago  Medio de pago elegido.
     * @param  bool  $esSocio  true si la venta está vinculada a un socio.
     * @return string Precio unitario a cobrar.
     */
    public function priceFor(PaymentMethodEnum|string|null $metodoPago, bool $esSocio = false): string
    {
        // Convierte el texto al caso del enum (null si está vacío o no existe).
        $metodoPago = is_string($metodoPago) ? PaymentMethodEnum::tryFrom($metodoPago) : $metodoPago;

        // Se trabaja en centavos enteros para no arrastrar errores de redondeo.
        $cents = self::toCents($this->precio);

        // Descuento de socio sobre el precio de lista.
        if ($esSocio) {
            // Porcentaje configurado, entre 0 y 100.
            $percent = max(0, min(100, (int) config('gym.kiosk.member_discount_percent', 0)));
            // Precio de lista con el descuento, redondeado al centavo.
            $cents = min($cents, (int) round($cents * (100 - $percent) / 100));
        }

        // Precio en efectivo, si corresponde y es más barato.
        if ($metodoPago === PaymentMethodEnum::Efectivo && $this->precio_efectivo !== null) {
            // El menor de los dos.
            $cents = min($cents, self::toCents($this->precio_efectivo));
        }

        // De vuelta a pesos con 2 decimales.
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Indica si el stock llegó al mínimo configurado.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve true solo si el producto controla stock, tiene stock mínimo y el stock está en ese
     *   número o por debajo. No modifica nada.
     *
     * @return bool true si hay que reponer.
     */
    public function hasLowStock(): bool
    {
        // Solo cuenta si se lleva el stock y hay un mínimo cargado.
        return $this->controla_stock && $this->stock_minimo !== null && $this->stock <= $this->stock_minimo;
    }

    /**
     * Categoría del producto (puede no tener).
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        // Columna product_category_id.
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * Pasa un importe en pesos ("1234.50") a centavos enteros.
     *
     * Precondiciones:
     * - $amount es un número o un texto numérico con hasta 2 decimales.
     *
     * Postcondiciones:
     * - Devuelve los centavos como entero (123450). No modifica nada.
     *
     * @param  string|int|float  $amount  Importe en pesos.
     * @return int Importe en centavos.
     */
    private static function toCents(string|int|float $amount): int
    {
        // Redondea para evitar que 0.1 + 0.2 deje un centavo de menos.
        return (int) round(((float) $amount) * 100);
    }
}
