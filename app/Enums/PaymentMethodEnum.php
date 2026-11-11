<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Medios con los que un cliente puede pagar.
 *
 * El valor de texto de cada caso es lo que se guarda en la columna payments.metodo_pago.
 * Por ahora solo hay medios manuales (los registra el dueño o un profe). Cuando se sume una
 * pasarela de pago (por ejemplo Mercado Pago) se agrega acá como un caso nuevo, sin migrar
 * la tabla, porque la columna es de texto.
 */
enum PaymentMethodEnum: string implements HasColor, HasIcon, HasLabel
{
    // Plata física entregada en el gimnasio.
    case Efectivo = 'efectivo';
    // Transferencia bancaria o a billetera virtual.
    case Transferencia = 'transferencia';

    /**
     * Devuelve el nombre del medio de pago para mostrar en el panel.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un texto no vacío, distinto para cada medio.
     * - No modifica nada.
     *
     * @return string Nombre legible del medio (por ejemplo "Efectivo").
     */
    public function getLabel(): string
    {
        // Elige el texto según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Nombre para el pago en efectivo.
            self::Efectivo => 'Efectivo',
            // Nombre para el pago por transferencia.
            self::Transferencia => 'Transferencia',
        };
    }

    /**
     * Devuelve el color de Filament con el que se pinta el badge del medio de pago.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve uno de los colores del panel: success o info.
     * - No modifica nada.
     *
     * @return string Nombre del color de Filament.
     */
    public function getColor(): string
    {
        // Elige el color según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Verde: plata en mano.
            self::Efectivo => 'success',
            // Celeste: plata en cuenta.
            self::Transferencia => 'info',
        };
    }

    /**
     * Devuelve el ícono que acompaña al medio de pago en el panel.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un caso de Heroicon, distinto para cada medio.
     * - No modifica nada.
     *
     * @return Heroicon Ícono de Heroicons incluido en Filament.
     */
    public function getIcon(): Heroicon
    {
        // Elige el ícono según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Billetes.
            self::Efectivo => Heroicon::OutlinedBanknotes,
            // Flechas de ida y vuelta: movimiento entre cuentas.
            self::Transferencia => Heroicon::OutlinedArrowsRightLeft,
        };
    }
}
