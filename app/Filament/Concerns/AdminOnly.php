<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Deja una pantalla o recurso del panel solo para las cuentas con rol de administrador.
 *
 * Lo usan el manual, la pantalla Personal y las secciones del sitio. Un profe no ve el enlace en
 * el menú y, si abre la dirección a mano, recibe 403 (Prohibido).
 */
trait AdminOnly
{
    /**
     * Decide si la cuenta con sesión puede ver y usar la pantalla.
     *
     * Filament la consulta para armar el menú lateral y antes de abrir la pantalla.
     *
     * Precondiciones:
     * - Ninguna: se puede llamar con o sin sesión iniciada.
     *
     * Postcondiciones:
     * - Devuelve true solo si hay una cuenta del personal con sesión y es administradora
     *   (ver User::isSiteAdmin()).
     * - No modifica nada.
     *
     * @return bool true si puede verla.
     */
    public static function canAccess(): bool
    {
        // Cuenta con sesión en el panel (null si no hay).
        $user = Auth::guard('web')->user();

        // Solo un administrador.
        return $user instanceof User && $user->isSiteAdmin();
    }
}
