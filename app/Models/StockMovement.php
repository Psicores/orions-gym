<?php

namespace App\Models;

use App\Enums\StockMovementTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de stock de un producto del kiosco: entrada, venta, ajuste o anulación.
 *
 * Se crea solo con Product::moveStock() (o adjustStockTo()), que además actualiza el stock del
 * producto en la misma transacción. No se edita ni se borra: es el historial del stock.
 */
#[Fillable(['product_id', 'tipo', 'cantidad', 'stock_resultante', 'sale_item_id', 'registrado_por_user_id', 'nota'])]
class StockMovement extends Model
{
    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            // Tipo como caso del enum.
            'tipo' => StockMovementTypeEnum::class,
            // Unidades con signo.
            'cantidad' => 'integer',
            // Stock después del movimiento.
            'stock_resultante' => 'integer',
        ];
    }

    /**
     * Producto movido (incluye los dados de baja, para que el historial no quede vacío).
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        // Columna product_id, con los dados de baja.
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Cuenta del personal que lo registró (incluye cuentas dadas de baja).
     *
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        // Columna registrado_por_user_id, con las cuentas dadas de baja.
        return $this->belongsTo(User::class, 'registrado_por_user_id')->withTrashed();
    }
}
