<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipos de movimiento de stock del kiosco.
 *
 * El valor de texto es lo que se guarda en stock_movements.tipo.
 */
enum StockMovementTypeEnum: string implements HasColor, HasLabel
{
    // Mercadería que llega (compra al proveedor).
    case Entrada = 'entrada';
    // Unidades que salen por una venta.
    case Venta = 'venta';
    // Corrección después de contar (rotura, vencido, diferencia).
    case Ajuste = 'ajuste';
    // Unidades que vuelven al anular una venta.
    case Anulacion = 'anulacion';

    /**
     * Nombre del tipo para mostrar en el panel.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve un texto no vacío, distinto para cada tipo. No modifica nada.
     *
     * @return string Nombre legible.
     */
    public function getLabel(): string
    {
        // Un texto por caso.
        return match ($this) {
            self::Entrada => 'Entrada de mercadería',
            self::Venta => 'Venta',
            self::Ajuste => 'Ajuste',
            self::Anulacion => 'Venta anulada',
        };
    }

    /**
     * Color del badge del tipo (nunca rojo).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve un color del panel. No modifica nada.
     *
     * @return string Nombre del color de Filament.
     */
    public function getColor(): string
    {
        // Un color por caso.
        return match ($this) {
            // Verde: entra mercadería.
            self::Entrada => 'success',
            // Gris: lo normal del día.
            self::Venta => 'gray',
            // Ámbar: corrección a mano, para mirarla.
            self::Ajuste => 'warning',
            // Celeste: devolución.
            self::Anulacion => 'info',
        };
    }
}
