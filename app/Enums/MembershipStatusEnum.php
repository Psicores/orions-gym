<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Estados de una membresía: indican si el cliente puede entrar físicamente al gimnasio,
 * independientemente de si pagó o no (eso lo indica ClientStatusEnum).
 *
 * El valor de texto de cada caso es lo que se guarda en la columna memberships.estado.
 * Implementa las interfaces de Filament para que el panel muestre nombre, descripción,
 * color e ícono sin repetirlos en cada pantalla.
 */
enum MembershipStatusEnum: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    // Membresía vigente: el cliente entra normalmente.
    case Entrenando = 'entrenando';
    // Membresía pausada: el cliente no puede entrar hasta reactivarla.
    case EnPausa = 'en_pausa';
    // Excepción habilitada por el dueño: el cliente entra aunque su situación no sea la normal.
    case ActivoFlex = 'activo_flex';
    // Membresía terminada: el cliente no puede entrar con ella.
    case Vencida = 'vencida';

    /**
     * Indica si una membresía en este estado habilita la entrada física al gimnasio.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve true para Entrenando y ActivoFlex, false para EnPausa y Vencida.
     * - No modifica nada.
     *
     * @return bool true si el estado permite entrar al gimnasio.
     */
    public function allowsGymAccess(): bool
    {
        // Solo los estados vigentes (normal o con excepción) dejan entrar.
        return match ($this) {
            // Vigente o con excepción del dueño: puede entrar.
            self::Entrenando, self::ActivoFlex => true,
            // Pausada o terminada: no puede entrar.
            self::EnPausa, self::Vencida => false,
        };
    }

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
     * @return string Nombre legible del estado (por ejemplo "Activo flex").
     */
    public function getLabel(): string
    {
        // Elige el texto según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Nombre para el estado Entrenando.
            self::Entrenando => 'Entrenando',
            // Nombre para el estado En pausa.
            self::EnPausa => 'En pausa',
            // Nombre para el estado Activo flex.
            self::ActivoFlex => 'Activo flex',
            // Nombre para el estado Vencida.
            self::Vencida => 'Vencida',
        };
    }

    /**
     * Devuelve qué significa el estado en términos de acceso al gimnasio.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un texto no vacío que explica el estado.
     * - No modifica nada.
     *
     * @return string Explicación del estado (por ejemplo "Puede entrar").
     */
    public function getDescription(): string
    {
        // Elige la explicación según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Acceso normal.
            self::Entrenando => 'Puede entrar',
            // Acceso suspendido temporalmente.
            self::EnPausa => 'No puede entrar hasta reactivarla',
            // Acceso por excepción.
            self::ActivoFlex => 'Puede entrar por excepción del dueño',
            // Acceso terminado.
            self::Vencida => 'Terminó, no puede entrar',
        };
    }

    /**
     * Devuelve el color de Filament con el que se pinta el badge del estado.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve uno de los colores del panel: success, warning, info o gray.
     * - No modifica nada.
     *
     * @return string Nombre del color de Filament.
     */
    public function getColor(): string
    {
        // Elige el color según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Verde: acceso normal.
            self::Entrenando => 'success',
            // Amarillo: acceso suspendido, a revisar.
            self::EnPausa => 'warning',
            // Celeste: acceso por excepción.
            self::ActivoFlex => 'info',
            // Gris: terminada.
            self::Vencida => 'gray',
        };
    }

    /**
     * Devuelve el ícono que acompaña al estado en el panel.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un caso de Heroicon, distinto para cada estado.
     * - No modifica nada.
     *
     * @return Heroicon Ícono de Heroicons incluido en Filament.
     */
    public function getIcon(): Heroicon
    {
        // Elige el ícono según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Tilde: todo en orden.
            self::Entrenando => Heroicon::OutlinedCheckCircle,
            // Pausa.
            self::EnPausa => Heroicon::OutlinedPauseCircle,
            // Rayo: excepción.
            self::ActivoFlex => Heroicon::OutlinedBolt,
            // Cruz: terminada.
            self::Vencida => Heroicon::OutlinedXCircle,
        };
    }
}
