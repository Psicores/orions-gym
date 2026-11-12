<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Planes base del gimnasio: nombre, precio mensual en pesos y créditos (clases) que otorga.
     * 999 créditos es la convención del sistema para un plan libre.
     *
     * @var list<array{nombre: string, precio: string, creditos_otorgados: int}>
     */
    private const PLANES = [
        ['nombre' => '3 días', 'precio' => '30000.00', 'creditos_otorgados' => 12],
        ['nombre' => '4 días', 'precio' => '36000.00', 'creditos_otorgados' => 16],
        ['nombre' => 'Pase libre', 'precio' => '45000.00', 'creditos_otorgados' => 999],
    ];

    /**
     * Carga los tres planes base del catálogo.
     *
     * Precondiciones:
     * - Las migraciones están corridas (existe la tabla plans).
     *
     * Postcondiciones:
     * - Existen los planes "3 días" (12 créditos), "4 días" (16 créditos) y "Pase libre" (999).
     * - Si un plan con ese nombre ya existía (aunque esté dado de baja), no se toca: se respetan
     *   el precio y los créditos que el dueño haya cambiado desde el panel.
     * - Correrlo varias veces no duplica planes.
     */
    public function run(): void
    {
        // Recorre los planes base.
        foreach (self::PLANES as $plan) {
            // Busca el plan por nombre, incluyendo los dados de baja, para no duplicarlo.
            Plan::withTrashed()->firstOrCreate(
                // Criterio de búsqueda: el nombre del plan.
                ['nombre' => $plan['nombre']],
                // Datos con los que se crea solo si no existía.
                ['precio' => $plan['precio'], 'creditos_otorgados' => $plan['creditos_otorgados']],
            );
        }
    }
}
