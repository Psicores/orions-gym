<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Estados posibles de un cliente (socio) del gimnasio según su situación de pago.
 *
 * El valor de texto de cada caso es lo que se guarda en la columna clients.estado.
 * Implementa HasLabel, HasDescription y HasColor para que Filament lo muestre solo
 * (opciones de un Select, badges de la tabla) sin repetir textos ni colores.
 */
enum ClientStatusEnum: string implements HasColor, HasDescription, HasLabel
{
    // Cuota al día: puede entrenar normalmente.
    case Entrenando = 'entrenando';
    // Debe abonar la cuota.
    case EnPausa = 'en_pausa';
    // Tiene un acuerdo de pago atrasado y se le permite seguir entrenando.
    case ActivoTemporal = 'activo_temporal';
    // Sin actividad; es el estado con el que se crea todo cliente nuevo.
    case Inactivo = 'inactivo';

    /**
     * Devuelve el nombre del estado para mostrar en el panel.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un texto no vacío, distinto para cada estado.
     * - No modifica nada.
     *
     * @return string Nombre legible del estado (por ejemplo "En pausa").
     */
    public function getLabel(): string
    {
        // Elige el texto según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Nombre para el estado Entrenando.
            self::Entrenando => 'Entrenando',
            // Nombre para el estado En pausa.
            self::EnPausa => 'En pausa',
            // Nombre para el estado Activo temporal.
            self::ActivoTemporal => 'Activo temporal',
            // Nombre para el estado Inactivo.
            self::Inactivo => 'Inactivo',
        };
    }

    /**
     * Devuelve qué significa el estado en términos de pago del cliente.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un texto no vacío que explica el estado.
     * - No modifica nada.
     *
     * @return string Explicación del estado (por ejemplo "Debe abonar").
     */
    public function getDescription(): string
    {
        // Elige la explicación según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // El cliente tiene la cuota paga.
            self::Entrenando => 'Cuota al día',
            // El cliente tiene la cuota impaga.
            self::EnPausa => 'Debe abonar',
            // El cliente acordó pagar más tarde.
            self::ActivoTemporal => 'Acuerdo de pago atrasado',
            // El cliente no está viniendo al gimnasio.
            self::Inactivo => 'Sin actividad en el gimnasio',
        };
    }

    /**
     * Devuelve el color de Filament con el que se pinta el badge del estado.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve uno de los colores del panel: success, warning, info o gray (nunca rojo).
     * - No modifica nada.
     *
     * @return string Nombre del color de Filament.
     */
    public function getColor(): string
    {
        // Elige el color según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Verde: todo en orden.
            self::Entrenando => 'success',
            // Amarillo: debe la cuota. A propósito no es rojo, para avisar sin alarmar.
            self::EnPausa => 'warning',
            // Celeste: situación provisoria (mismo color que la membresía Activo flex).
            self::ActivoTemporal => 'info',
            // Gris: sin actividad.
            self::Inactivo => 'gray',
        };
    }
}
