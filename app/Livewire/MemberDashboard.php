<?php

namespace App\Livewire;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Inicio del portal del socio: cómo está su membresía, cuándo vence y cuántos créditos le quedan.
 */
#[Layout('layouts::member')]
#[Title('Inicio')]
class MemberDashboard extends Component
{
    /**
     * Arma la pantalla con los datos del socio que tiene la sesión iniciada.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - La vista recibe $client (el socio) y $membership: su membresía vigente con el plan
     *   cargado, o null si no tiene ninguna abierta.
     * - Nunca muestra datos de otro socio. No modifica nada.
     *
     * @return View La vista livewire.member-dashboard.
     */
    public function render(): View
    {
        /** @var Client $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // Devuelve la vista con el socio y su membresía vigente.
        return view('livewire.member-dashboard', [
            // Socio.
            'client' => $client,
            // Membresía no vencida más reciente, con su plan (ver Client::currentMembership()).
            'membership' => $client->currentMembership()->with('plan')->first(),
        ]);
    }
}
