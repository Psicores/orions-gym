<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula clients.fecha_vencimiento a partir de las membresías, para los clientes que ya
 * existían antes de que ese vencimiento pasara a calcularse solo.
 *
 * Aplica la misma regla que Client::syncFechaVencimiento(), con la tabla directa para no
 * depender de cómo evolucione el modelo.
 */
return new class extends Migration
{
    /**
     * Sincroniza el vencimiento de cada cliente que tiene membresías.
     *
     * Precondiciones:
     * - Existen las tablas clients y memberships.
     *
     * Postcondiciones:
     * - Cada cliente con membresías (incluidos los dados de baja) queda con fecha_vencimiento =
     *   la más lejana entre sus membresías no vencidas o, si no tiene ninguna abierta, la más
     *   lejana entre todas.
     * - Los clientes sin membresías conservan el valor que tenían.
     */
    public function up(): void
    {
        // Recorre los clientes que tienen al menos una membresía.
        DB::table('memberships')
            // Un registro por cliente.
            ->select('client_id')
            // Agrupa por cliente.
            ->groupBy('client_id')
            // Orden estable para procesar de a tandas.
            ->orderBy('client_id')
            // Procesa de a 100 clientes.
            ->each(function (object $row): void {
                // Vencimiento más lejano entre las membresías abiertas (no vencidas) del cliente.
                $fechaVencimiento = DB::table('memberships')
                    // Solo las del cliente.
                    ->where('client_id', $row->client_id)
                    // Excluye las cerradas.
                    ->where('estado', '!=', 'vencida')
                    // Fecha máxima (null si no hay abiertas).
                    ->max('fecha_vencimiento')
                    // Si no hay abiertas, la fecha máxima entre todas sus membresías.
                    ?? DB::table('memberships')->where('client_id', $row->client_id)->max('fecha_vencimiento');

                // Guarda el vencimiento calculado en el cliente.
                DB::table('clients')->where('id', $row->client_id)->update(['fecha_vencimiento' => $fechaVencimiento]);
            });
    }

    /**
     * No se puede deshacer: los vencimientos cargados a mano antes no se guardaron.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - No cambia nada.
     */
    public function down(): void
    {
        // Sin cambios: el valor anterior no se puede recuperar.
    }
};
