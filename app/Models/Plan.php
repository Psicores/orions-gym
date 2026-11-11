<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan del catálogo: lo que el gimnasio vende (por ejemplo "3 veces por semana" o "Libre").
 *
 * creditos_otorgados es la cantidad de clases que habilita el plan; por convención,
 * 999 representa un plan libre (sin límite práctico).
 */
#[Fillable(['nombre', 'precio', 'creditos_otorgados'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - La columna precio es decimal(10,2) y creditos_otorgados es un entero sin signo.
     *
     * Postcondiciones:
     * - $plan->precio devuelve un string con exactamente 2 decimales (por ejemplo "25000.00"),
     *   sin errores de redondeo de punto flotante.
     * - $plan->creditos_otorgados devuelve un int.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // El precio se maneja como decimal con 2 decimales (string exacto, no float).
            'precio' => 'decimal:2',
            // Los créditos se manejan como número entero.
            'creditos_otorgados' => 'integer',
        ];
    }
}
