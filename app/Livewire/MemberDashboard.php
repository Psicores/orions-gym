<?php

namespace App\Livewire;

use App\Models\Announcement;
use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Inicio del portal del socio: los anuncios del gimnasio, cómo está su membresía, cuándo vence y
 * cuántos créditos le quedan.
 */
#[Layout('layouts::member')]
#[Title('Inicio')]
class MemberDashboard extends Component
{
    /**
     * Cierra un anuncio para el socio que tiene la sesión iniciada.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     * - $announcementId es el id del anuncio cuyo botón "cerrar" tocó.
     *
     * Postcondiciones:
     * - Si ese anuncio se le estaba mostrando (activo y sin cerrar), queda marcado como leído
     *   por este socio y deja de aparecerle (ver Client::dismissAnnouncement()).
     * - Si el id no corresponde a un anuncio que se le esté mostrando, no hace nada.
     * - Nunca afecta a otros socios.
     *
     * @param  int  $announcementId  Id del anuncio a cerrar.
     */
    public function dismissAnnouncement(int $announcementId): void
    {
        /** @var Client $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // Busca el anuncio solo entre los que el socio tiene a la vista.
        $announcement = Announcement::query()->active()->unreadBy($client)->find($announcementId);

        // Lo marca como cerrado, si existe.
        if ($announcement !== null) {
            // Guarda quién y cuándo.
            $client->dismissAnnouncement($announcement);
        }
    }

    /**
     * Arma la pantalla con los datos del socio que tiene la sesión iniciada.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - La vista recibe $client (el socio) y $membership: su membresía vigente con el plan
     *   cargado, o null si no tiene ninguna abierta.
     * - La vista recibe $announcements: los anuncios activos y dentro de sus fechas que este
     *   socio todavía no cerró.
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
            // Anuncios vigentes que el socio todavía no cerró, del más nuevo al más viejo.
            'announcements' => Announcement::query()->active()->unreadBy($client)->latest()->latest('id')->get(),
        ]);
    }
}
