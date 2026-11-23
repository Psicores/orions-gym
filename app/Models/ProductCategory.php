<?php

namespace App\Models;

use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoría de productos del kiosco (Bebidas, Suplementos, Ropa...).
 *
 * En la pantalla de venta cada categoría es un botón para filtrar, en el orden de "orden".
 */
#[Fillable(['nombre', 'orden'])]
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Posición como número.
            'orden' => 'integer',
        ];
    }

    /**
     * Productos de esta categoría.
     *
     * Precondiciones:
     * - Existe la tabla products con product_category_id.
     *
     * Postcondiciones:
     * - Devuelve la relación sin ejecutar la consulta. No modifica nada.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        // Busca en products las filas con este product_category_id.
        return $this->hasMany(Product::class);
    }
}
