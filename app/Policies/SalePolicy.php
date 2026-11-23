<?php

namespace App\Policies;

use App\Enums\SaleStatusEnum;
use App\Models\Sale;
use App\Models\User;

/**
 * Permisos sobre las ventas del kiosco.
 *
 * Todo el personal ve el historial (quien vende necesita consultar una venta). Las ventas se crean
 * solo desde la pantalla de venta, no se editan ni se borran; anular es solo para administradores.
 */
class SalePolicy
{
    /**
     * Ver el historial de ventas.
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
     * Ver una venta.
     *
     * Postcondiciones:
     * - Siempre true. No modifica nada.
     */
    public function view(User $user, Sale $sale): bool
    {
        // Administradores y profes.
        return true;
    }

    /**
     * Crear desde el historial: nunca (se vende en la pantalla Kiosco).
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Editar: nunca (una venta mal hecha se anula).
     */
    public function update(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Borrar: nunca (se anula).
     */
    public function delete(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Borrar varias: nunca.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Anular una venta.
     *
     * Postcondiciones:
     * - true solo para administradores y si la venta no está anulada. No modifica nada.
     */
    public function void(User $user, Sale $sale): bool
    {
        // Administrador y venta vigente.
        return $user->isSiteAdmin() && $sale->estado !== SaleStatusEnum::Anulada;
    }
}
