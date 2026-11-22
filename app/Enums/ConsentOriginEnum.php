<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Desde dónde se registró el último cambio de los permisos de aviso de un cliente
 * (columna clients.consentimiento_origen).
 *
 * Sirve para saber, ante un reclamo, quién dio el permiso: el personal en el panel, el propio
 * socio en su portal, o una carga masiva.
 */
enum ConsentOriginEnum: string implements HasLabel
{
    // El personal lo cargó o lo cambió en el panel (por ejemplo, al dar de alta al cliente).
    case AltaPanel = 'alta_panel';
    // El socio lo cambió en "Mi cuenta" del portal.
    case Portal = 'portal';
    // Vino de una carga masiva de clientes (todavía no existe en el sistema).
    case Importacion = 'importacion';

    /**
     * Devuelve el nombre del origen para mostrar en el panel.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un texto no vacío, distinto para cada origen. No modifica nada.
     *
     * @return string Nombre legible (por ejemplo "el portal").
     */
    public function getLabel(): string
    {
        // Elige el texto según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Lo cargó el personal.
            self::AltaPanel => 'el panel',
            // Lo eligió el socio.
            self::Portal => 'el portal',
            // Carga masiva.
            self::Importacion => 'una importación',
        };
    }
}
