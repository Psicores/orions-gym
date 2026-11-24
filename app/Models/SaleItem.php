<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón de una venta del kiosco: un producto, cuántas unidades y a qué precio.
 *
 * Guarda una copia del nombre y de los precios del momento: si después cambia el producto, la
 * venta sigue mostrando lo que se cobró.
 */
#[Fillable(['sale_id', 'product_id', 'nombre', 'cantidad', 'precio_lista', 'precio_unitario', 'subtotal'])]
class SaleItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Unidades.
            'cantidad' => 'integer',
            // Importes exactos.
            'precio_lista' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * Venta a la que pertenece.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        // Columna sale_id.
        return $this->belongsTo(Sale::class);
    }

    /**
     * Producto vendido (incluye los dados de baja).
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        // Columna product_id, con los dados de baja.
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
