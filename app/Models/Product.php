<?php

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use App\Enums\StockMovementTypeEnum;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Producto del kiosco (bebida, suplemento, ropa...).
 *
 * precio es el de lista; precio_efectivo (opcional) es el precio pagando en efectivo, como en los
 * planes. A los socios se les descuenta config('gym.kiosk.member_discount_percent') sobre el
 * precio de lista; los descuentos no se suman: se cobra el más barato (ver priceFor()).
 *
 * stock solo se mueve con moveStock() / adjustStockTo(), que dejan cada cambio en stock_movements;
 * no se edita desde el formulario. Puede quedar negativo si se vende sin stock en modo flexible
 * (config gym.kiosk.stock_mode = warn, el de fábrica; strict bloquea la venta).
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
     * Indica si el kiosco está en modo estricto (no vende sin stock) o flexible (vende y avisa).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve true solo si config('gym.kiosk.stock_mode') es "strict"; cualquier otro valor
     *   (incluido "warn", el de fábrica) es flexible. No modifica nada.
     *
     * @return bool true si se bloquea la venta sin stock.
     */
    public static function stockIsStrict(): bool
    {
        // Solo "strict" bloquea.
        return config('gym.kiosk.stock_mode') === 'strict';
    }

    /**
     * Cuántas unidades faltarían para vender $cantidad.
     *
     * Precondiciones:
     * - $cantidad es mayor que 0.
     *
     * Postcondiciones:
     * - Devuelve 0 si alcanza el stock o si el producto no controla stock; si no, las unidades
     *   que faltan. Usa el stock cargado en el modelo. No modifica nada.
     *
     * @param  int  $cantidad  Unidades a vender.
     * @return int Unidades que faltan (0 = alcanza).
     */
    public function stockShortage(int $cantidad): int
    {
        // Sin control de stock nunca falta.
        if (! $this->controla_stock) {
            return 0;
        }

        // Lo que pasa del stock disponible (que puede ser negativo).
        return max(0, $cantidad - max(0, $this->stock));
    }

    /**
     * Registra un movimiento de stock y actualiza el stock del producto, todo junto.
     *
     * Precondiciones:
     * - El producto existe en la base y controla stock.
     * - $cantidad no es 0: positiva para entradas y anulaciones, negativa para ventas; un ajuste
     *   puede ser de cualquier signo.
     * - $user es la cuenta del personal que lo registra.
     *
     * Postcondiciones:
     * - En una transacción, bloquea la fila del producto (dos movimientos a la vez no se pisan),
     *   le suma $cantidad al stock y crea el movimiento con el stock que quedó.
     * - Este modelo queda con el stock actualizado.
     * - No valida stock suficiente: eso lo decide quien vende, según el modo (stockIsStrict()).
     *
     * @param  int  $cantidad  Unidades con signo.
     * @param  StockMovementTypeEnum  $tipo  Tipo de movimiento.
     * @param  User  $user  Quién lo registra.
     * @param  string|null  $nota  Motivo o detalle.
     * @param  int|null  $saleItemId  Renglón de venta que lo genera, si viene de una venta.
     * @return StockMovement El movimiento creado.
     *
     * @throws LogicException Si la cantidad es 0 o el producto no controla stock.
     */
    public function moveStock(int $cantidad, StockMovementTypeEnum $tipo, User $user, ?string $nota = null, ?int $saleItemId = null): StockMovement
    {
        // Un movimiento de cero unidades no dice nada.
        if ($cantidad === 0) {
            throw new LogicException('Un movimiento de stock no puede ser de 0 unidades.');
        }

        // Sin control de stock no hay nada que mover.
        if (! $this->controla_stock) {
            throw new LogicException("El producto {$this->getKey()} no controla stock.");
        }

        // Todo o nada.
        return DB::transaction(function () use ($cantidad, $tipo, $user, $nota, $saleItemId): StockMovement {
            // Vuelve a leer el producto bloqueando la fila hasta terminar.
            $locked = static::withTrashed()->lockForUpdate()->findOrFail($this->getKey());

            // Stock nuevo.
            $locked->stock += $cantidad;
            // Guarda solo el stock (sin tocar otros cambios pendientes de este modelo).
            $locked->save();

            // Este modelo refleja el stock nuevo.
            $this->stock = $locked->stock;
            // Sin marcarlo como cambio pendiente.
            $this->syncOriginalAttribute('stock');

            // El historial del movimiento.
            return StockMovement::create([
                'product_id' => $this->getKey(),
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'stock_resultante' => $locked->stock,
                'sale_item_id' => $saleItemId,
                'registrado_por_user_id' => $user->getKey(),
                // Sin espacios de más; vacío = null.
                'nota' => filled($nota) ? trim($nota) : null,
            ]);
        });
    }

    /**
     * Deja el stock en lo que se contó, registrando la diferencia como ajuste.
     *
     * Precondiciones:
     * - El producto existe y controla stock; $contado es 0 o más.
     *
     * Postcondiciones:
     * - Si el stock ya es $contado, no hace nada y devuelve null.
     * - Si no, registra un ajuste por la diferencia (con la nota) y devuelve el movimiento.
     * - La diferencia se calcula con la fila bloqueada, así una venta en el medio no se pierde.
     *
     * @param  int  $contado  Unidades contadas.
     * @param  User  $user  Quién lo registra.
     * @param  string  $nota  Motivo del ajuste.
     * @return StockMovement|null El ajuste, o null si no había diferencia.
     */
    public function adjustStockTo(int $contado, User $user, string $nota): ?StockMovement
    {
        // Todo o nada.
        return DB::transaction(function () use ($contado, $user, $nota): ?StockMovement {
            // Stock actual con la fila bloqueada.
            $actual = static::withTrashed()->lockForUpdate()->findOrFail($this->getKey())->stock;

            // Sin diferencia no hay ajuste.
            if ($contado === $actual) {
                return null;
            }

            // Registra la diferencia (la transacción interna se anida en esta).
            return $this->moveStock($contado - $actual, StockMovementTypeEnum::Ajuste, $user, $nota);
        });
    }

    /**
     * Movimientos de stock del producto.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        // Busca en stock_movements las filas con este product_id.
        return $this->hasMany(StockMovement::class);
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
