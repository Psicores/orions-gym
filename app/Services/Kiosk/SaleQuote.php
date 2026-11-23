<?php

namespace App\Services\Kiosk;

/**
 * Presupuesto de una venta: los renglones con sus precios y los totales, en centavos.
 *
 * Lo arma SaleService::quote() para mostrar el carrito y lo vuelve a armar checkout() con los
 * productos bloqueados antes de guardar.
 */
final readonly class SaleQuote
{
    /**
     * @param  list<SaleQuoteLine>  $lines  Renglones, en el orden del carrito.
     */
    public function __construct(public array $lines) {}

    /**
     * Suma a precio de lista, en centavos.
     */
    public function subtotalCents(): int
    {
        // Suma de cada renglón a precio de lista.
        return array_sum(array_map(fn (SaleQuoteLine $line): int => $line->subtotalListaCents(), $this->lines));
    }

    /**
     * Total a cobrar, en centavos.
     */
    public function totalCents(): int
    {
        // Suma de cada renglón a precio cobrado.
        return array_sum(array_map(fn (SaleQuoteLine $line): int => $line->subtotalCents(), $this->lines));
    }

    /**
     * Descuento total (socio o efectivo), en centavos.
     */
    public function descuentoCents(): int
    {
        // Lo que va de lista a cobrado.
        return $this->subtotalCents() - $this->totalCents();
    }

    /**
     * Renglones con stock insuficiente.
     *
     * Postcondiciones:
     * - Devuelve solo los renglones con faltante mayor que 0. No modifica nada.
     *
     * @return list<SaleQuoteLine>
     */
    public function shortages(): array
    {
        // Solo los que no alcanzan.
        return array_values(array_filter($this->lines, fn (SaleQuoteLine $line): bool => $line->faltante > 0));
    }

    /**
     * Pasa centavos a un importe con 2 decimales ("1234.50"), como se guarda en la base.
     *
     * @param  int  $cents  Centavos.
     * @return string Importe.
     */
    public static function toAmount(int $cents): string
    {
        // Punto decimal, sin separador de miles.
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Pasa un importe ("1234.5", 1234.5) a centavos enteros.
     *
     * @param  string|int|float  $amount  Importe en pesos.
     * @return int Centavos.
     */
    public static function toCents(string|int|float $amount): int
    {
        // Redondea para no perder un centavo por el punto flotante.
        return (int) round(((float) $amount) * 100);
    }
}
