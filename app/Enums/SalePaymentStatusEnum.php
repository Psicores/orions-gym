<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estados de un cobro de una venta del kiosco. El valor de texto es lo que se guarda en
 * sale_payments.estado.
 *
 * Un cobro manual (efectivo o transferencia en el mostrador) nace Aprobado. Un cobro por pasarela
 * nace Pendiente y lo pasa a Aprobado o Rechazado el aviso de la pasarela.
 */
enum SalePaymentStatusEnum: string implements HasColor, HasLabel
{
    // Esperando confirmación de la pasarela.
    case Pendiente = 'pendiente';
    // Plata recibida.
    case Aprobado = 'aprobado';
    // La pasarela no lo aprobó.
    case Rechazado = 'rechazado';
    // Se devolvió (venta anulada).
    case Reintegrado = 'reintegrado';

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
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
            self::Reintegrado => 'Reintegrado',
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
            self::Pendiente => 'warning',
            self::Aprobado => 'success',
            // Ámbar también: hay que mirarlo, sin alarmar.
            self::Rechazado => 'warning',
            self::Reintegrado => 'gray',
        };
    }
}
