<?php

namespace App\Livewire;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Historial del socio, en dos pestañas: "Mis pagos" y "Mis asistencias".
 *
 * Las asistencias son los ingresos que el socio registró escaneando el QR de recepción.
 */
#[Layout('layouts::member')]
#[Title('Historial')]
class MemberHistory extends Component
{
    // Máximo de pagos que se listan: los más recientes.
    private const int MAX_PAYMENTS = 50;

    // Máximo de asistencias que se listan: las más recientes.
    private const int MAX_ATTENDANCES = 60;

    /**
     * Arma la pantalla con los pagos y las asistencias del socio que tiene la sesión iniciada.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - La vista recibe $payments: hasta 50 pagos del socio, del más nuevo al más viejo, cada uno
     *   con su membresía y el plan cargados.
     * - La vista recibe $attendances: hasta 60 asistencias del socio, de la más nueva a la más vieja.
     * - Nunca incluye pagos ni asistencias de otro socio. No modifica nada.
     *
     * @return View La vista livewire.member-history.
     */
    public function render(): View
    {
        /** @var Client $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // Devuelve la vista con los pagos.
        return view('livewire.member-history', [
            // Pagos del socio con el plan que abonó cada uno, del más reciente al más antiguo.
            'payments' => $client->payments()
                ->with('membership.plan')
                ->latest('fecha_pago')
                ->latest('id')
                ->limit(self::MAX_PAYMENTS)
                ->get(),
            // Ingresos del socio al gimnasio, del más reciente al más antiguo.
            'attendances' => $client->attendances()
                ->latest('fecha_hora')
                ->latest('id')
                ->limit(self::MAX_ATTENDANCES)
                ->get(),
        ]);
    }
}
