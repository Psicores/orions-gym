<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estados de una venta del kiosco. El valor de texto es lo que se guarda en sales.estado.
 */
enum SaleStatusEnum: string implements HasColor, HasLabel
{
    // Esperando que se acredite un cobro (pasarela de pago, más adelante).
    case Pendiente = 'pendiente';
    // Cobrada por completo.
    case Pagada = 'pagada';
    // Anulada: devolvió el stock y no suma en los reportes.
    case Anulada = 'anulada';

    /**
     * Nombre del estado para mostrar en el panel.
     *
     * Postcondiciones:
     * - Devuelve un texto no vacío, distinto para cada estado. No modifica nada.
     */
    public function getLabel(): string
    {
        // Un texto por caso.
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Pagada => 'Pagada',
            self::Anulada => 'Anulada',
        };
    }

    /**
     * Color del badge (nunca rojo).
     *
     * Postcondiciones:
     * - Devuelve un color del panel. No modifica nada.
     */
    public function getColor(): string
    {
        // Un color por caso.
        return match ($this) {
            // Ámbar: falta que entre la plata.
            self::Pendiente => 'warning',
            // Verde: cobrada.
            self::Pagada => 'success',
            // Gris: ya no cuenta.
            self::Anulada => 'gray',
        };
    }
}
