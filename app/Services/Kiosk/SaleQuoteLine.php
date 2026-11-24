<?php

namespace App\Services\Kiosk;

use App\Models\Product;

/**
 * Un renglón del presupuesto de una venta: producto, unidades, precios en centavos y cuántas
 * unidades faltan de stock (0 si alcanza).
 */
final readonly class SaleQuoteLine
{
    /**
     * Precondiciones:
     * - $cantidad es mayor que 0; los precios están en centavos y no son negativos.
     *
     * Postcondiciones:
     * - Queda el renglón armado, sin modificar nada.
     *
     * @param  Product  $product  Producto.
     * @param  int  $cantidad  Unidades.
     * @param  int  $precioListaCents  Precio de lista unitario.
     * @param  int  $precioUnitarioCents  Precio unitario a cobrar.
     * @param  int  $faltante  Unidades que faltan de stock.
     */
    public function __construct(
        public Product $product,
        public int $cantidad,
        public int $precioListaCents,
        public int $precioUnitarioCents,
        public int $faltante,
    ) {}

    /**
     * Total del renglón a precio de lista, en centavos.
     */
    public function subtotalListaCents(): int
    {
        // Precio de lista por unidades.
        return $this->precioListaCents * $this->cantidad;
    }

    /**
     * Total del renglón a cobrar, en centavos.
     */
    public function subtotalCents(): int
    {
        // Precio cobrado por unidades.
        return $this->precioUnitarioCents * $this->cantidad;
    }
}
