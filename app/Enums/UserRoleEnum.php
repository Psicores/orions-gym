<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Rol de una cuenta del personal (columna users.role).
 *
 * El valor de texto de cada caso es lo que se guarda en la base. Dos roles alcanzan por ahora;
 * si algún día hacen falta permisos sueltos, conviene pasar a un paquete de permisos.
 */
enum UserRoleEnum: string implements HasColor, HasLabel
{
    // Dueño o encargado: maneja todo el panel, incluido el sitio y las cuentas del personal.
    case Admin = 'admin';
    // Profe: atiende a los socios (clientes, cobros, membresías).
    case Profe = 'profe';

    /**
     * Devuelve el nombre del rol para mostrar en el panel.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un texto no vacío, distinto para cada rol. No modifica nada.
     *
     * @return string Nombre legible del rol.
     */
    public function getLabel(): string
    {
        // Elige el texto según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Cuenta con acceso completo.
            self::Admin => 'Administrador',
            // Cuenta de atención a socios.
            self::Profe => 'Profe',
        };
    }

    /**
     * Devuelve el color de Filament con el que se pinta el badge del rol.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un nombre de color de Filament. No modifica nada.
     *
     * @return string Color del badge.
     */
    public function getColor(): string
    {
        // Elige el color según el caso actual.
        return match ($this) {
            // Destaca al administrador.
            self::Admin => 'primary',
            // Neutro para los profes.
            self::Profe => 'gray',
        };
    }
}
