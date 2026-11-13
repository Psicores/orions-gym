<?php

namespace App\Livewire;

use App\Models\Attendance;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Termómetro de ocupación: le cuenta al socio qué tan lleno está el gimnasio ahora, sin mostrarle
 * cuánta gente hay. Se incrusta en otra pantalla (hoy, arriba del inicio del portal) y se
 * actualiza solo cada 5 minutos.
 */
class GymOccupancyMeter extends Component
{
    /** Minutos que dura un entrenamiento promedio: quien ingresó hace menos se cuenta como presente. */
    public const WORKOUT_MINUTES = 90;

    /** Hasta cuántos presentes el gimnasio está "tranqui". */
    public const QUIET_MAX = 15;

    /** Hasta cuántos presentes está "movido" (son unas 25 estaciones); con más, está "a pleno". */
    public const BUSY_MAX = 25;

    /**
     * Cuenta cuánta gente se estima que hay ahora en el gimnasio.
     *
     * Precondiciones:
     * - Existe la tabla attendances.
     *
     * Postcondiciones:
     * - Devuelve la cantidad de ingresos registrados en los últimos WORKOUT_MINUTES minutos.
     * - No modifica nada.
     *
     * @return int Ingresos de los últimos 90 minutos.
     */
    public function currentOccupancy(): int
    {
        // Ingresos desde hace 90 minutos hasta ahora.
        return Attendance::query()
            ->where('fecha_hora', '>=', now()->subMinutes(self::WORKOUT_MINUTES))
            ->count();
    }

    /**
     * Traduce una cantidad de presentes al estado que se le muestra al socio.
     *
     * Precondiciones:
     * - $presentes es mayor o igual a cero.
     *
     * Postcondiciones:
     * - Devuelve 'tranqui' de 0 a QUIET_MAX, 'movido' hasta BUSY_MAX y 'a_pleno' con más.
     *
     * @param  int  $presentes  Cantidad estimada de personas en el gimnasio.
     * @return 'tranqui'|'movido'|'a_pleno' Estado de ocupación.
     */
    public static function levelFor(int $presentes): string
    {
        // Compara contra los dos topes, del más bajo al más alto.
        return match (true) {
            $presentes <= self::QUIET_MAX => 'tranqui',
            $presentes <= self::BUSY_MAX => 'movido',
            default => 'a_pleno',
        };
    }

    /**
     * Arma la franja con el estado de ocupación actual.
     *
     * Precondiciones:
     * - Ninguna: no depende de quién tenga la sesión iniciada.
     *
     * Postcondiciones:
     * - La vista recibe $estado ('tranqui', 'movido' o 'a_pleno').
     * - La vista nunca recibe la cantidad de personas: el socio no la ve.
     *
     * @return View La vista livewire.gym-occupancy-meter.
     */
    public function render(): View
    {
        // Devuelve la vista solo con el estado.
        return view('livewire.gym-occupancy-meter', [
            // Estado según los ingresos de los últimos 90 minutos.
            'estado' => self::levelFor($this->currentOccupancy()),
        ]);
    }
}
