<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Permisos sobre los productos del kiosco.
 *
 * Todo el personal ve el listado (un profe vende y necesita consultar precios y stock); solo un
 * administrador da de alta, edita, da de baja o restaura. Nadie borra definitivamente: las ventas
 * viejas conservan el producto. Filament la usa para el listado y para cada botón.
 */
class ProductPolicy
{
    /**
     * Ver el listado de productos.
     *
     * Precondiciones:
     * - $user es una cuenta del personal con sesión.
     *
     * Postcondiciones:
     * - Siempre true. No modifica nada.
     */
    public function viewAny(User $user): bool
    {
        // Administradores y profes.
        return true;
    }

    /**
     * Ver un producto.
     *
     * Postcondiciones:
     * - Siempre true. No modifica nada.
     */
    public function view(User $user, Product $product): bool
    {
        // Administradores y profes.
        return true;
    }

    /**
     * Dar de alta un producto.
     *
     * Postcondiciones:
     * - true solo para administradores. No modifica nada.
     */
    public function create(User $user): bool
    {
        // Solo administradores.
        return $user->isSiteAdmin();
    }

    /**
     * Editar un producto (precio, códigos, foto...).
     *
     * Postcondiciones:
     * - true solo para administradores. No modifica nada.
     */
    public function update(User $user, Product $product): bool
    {
        // Solo administradores.
        return $user->isSiteAdmin();
    }

    /**
     * Dar de baja un producto (baja lógica).
     *
     * Postcondiciones:
     * - true solo para administradores. No modifica nada.
     */
    public function delete(User $user, Product $product): bool
    {
        // Solo administradores.
        return $user->isSiteAdmin();
    }

    /**
     * Dar de baja varios productos a la vez.
     *
     * Postcondiciones:
     * - true solo para administradores. No modifica nada.
     */
    public function deleteAny(User $user): bool
    {
        // Solo administradores.
        return $user->isSiteAdmin();
    }

    /**
     * Restaurar un producto dado de baja.
     *
     * Postcondiciones:
     * - true solo para administradores. No modifica nada.
     */
    public function restore(User $user, Product $product): bool
    {
        // Solo administradores.
        return $user->isSiteAdmin();
    }

    /**
     * Restaurar varios productos a la vez.
     *
     * Postcondiciones:
     * - true solo para administradores. No modifica nada.
     */
    public function restoreAny(User $user): bool
    {
        // Solo administradores.
        return $user->isSiteAdmin();
    }

    /**
     * Borrar definitivamente un producto.
     *
     * Postcondiciones:
     * - Siempre false: se perdería en las ventas viejas. No modifica nada.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        // Nunca.
        return false;
    }

    /**
     * Borrar definitivamente varios productos.
     *
     * Postcondiciones:
     * - Siempre false. No modifica nada.
     */
    public function forceDeleteAny(User $user): bool
    {
        // Nunca.
        return false;
    }
}
