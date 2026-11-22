<?php

namespace App\Livewire;

use App\Enums\ConsentOriginEnum;
use App\Http\Middleware\EnsureMemberSessionIsCurrent;
use App\Models\Client;
use App\Rules\ArgentineMobile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Mi cuenta" del portal del socio: sus datos, su celular, los permisos de aviso, el cambio de
 * contraseña y el botón para salir.
 */
#[Layout('layouts::member')]
#[Title('Mi cuenta')]
class MemberAccount extends Component
{
    // Contraseña con la que entró (su DNI si nunca la cambió).
    public string $current_password = '';

    // Contraseña nueva.
    public string $password = '';

    // Repetición de la contraseña nueva.
    public string $password_confirmation = '';

    // Se pone en true después de guardar, para mostrar el aviso de éxito.
    public bool $saved = false;

    // Acepta avisos de su cuenta por WhatsApp (casilla del formulario "Avisos").
    public bool $acepta_avisos_whatsapp = false;

    // Acepta promociones, eventos y novedades (casilla del formulario "Avisos").
    public bool $acepta_novedades = false;

    // Se pone en true después de guardar los avisos, para mostrar el aviso de éxito.
    public bool $consentsSaved = false;

    // Celular del socio (formulario "Tu celular").
    public string $cel = '';

    // Se pone en true después de guardar el celular, para mostrar el aviso de éxito.
    public bool $celSaved = false;

    /**
     * Carga en las casillas los permisos de aviso que el socio tiene hoy.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - Las dos casillas y el celular muestran lo guardado (el celular, legible). No modifica nada.
     */
    public function mount(): void
    {
        /** @var Client $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // Lo que tiene guardado.
        $this->acepta_avisos_whatsapp = (bool) $client->acepta_avisos_whatsapp;
        $this->acepta_novedades = (bool) $client->acepta_novedades;
        // Celular legible (+54 9 280 412-3456), o vacío.
        $this->cel = (string) Client::formatCel($client->cel);
    }

    /**
     * Guarda el celular que escribió el socio.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - Si es un celular argentino válido, queda guardado en formato +549... (ver
     *   Client::normalizeCel()), el campo lo muestra legible y $celSaved queda en true.
     * - Si está vacío o no es válido, muestra el error en el campo y no guarda nada.
     *
     * @throws ValidationException Si el celular no pasa la validación.
     */
    public function updateCel(): void
    {
        // Oculta el aviso de un guardado anterior.
        $this->celSaved = false;

        // Obligatorio acá (es lo que vino a completar) y tiene que ser un celular argentino.
        $this->validate(
            ['cel' => ['required', 'string', 'max:255', new ArgentineMobile]],
            attributes: ['cel' => 'celular'],
        );

        /** @var Client $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // El modelo lo normaliza al asignarlo.
        $client->update(['cel' => $this->cel]);

        // Lo muestra como quedó guardado.
        $this->cel = (string) Client::formatCel($client->cel);
        // Muestra la confirmación.
        $this->celSaved = true;
    }

    /**
     * Guarda los permisos de aviso que eligió el socio.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - Los dos permisos quedan como las casillas. El que cambió queda con la fecha de ahora (o
     *   sin fecha, si lo retiró) y el origen pasa a ser el portal (ver Client::updateConsents()).
     * - $consentsSaved queda en true para mostrar la confirmación.
     */
    public function updateConsents(): void
    {
        /** @var Client $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // Guarda los dos permisos, anotando que los cambió el socio.
        $client->updateConsents($this->acepta_avisos_whatsapp, $this->acepta_novedades, ConsentOriginEnum::Portal);

        // Muestra la confirmación.
        $this->consentsSaved = true;
    }

    /**
     * Cambia la contraseña del socio que tiene la sesión iniciada.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - Si la contraseña actual es correcta y la nueva tiene entre 6 y 255 caracteres, está bien
     *   repetida y no es su DNI: la guarda hasheada, vacía los campos y deja $saved en true.
     *   Desde ese momento el DNI deja de servir como contraseña.
     * - Si algo no se cumple, muestra el error en el campo que corresponde y no guarda nada.
     * - La sesión del socio sigue abierta en este dispositivo (y recordada, si lo estaba).
     * - En los demás dispositivos donde había entrado, tiene que volver a ingresar: sus sesiones
     *   se cierran y sus "recordarme" dejan de valer.
     *
     * @throws ValidationException Si algún dato no pasa la validación.
     */
    public function updatePassword(): void
    {
        // Oculta el aviso de un guardado anterior.
        $this->saved = false;

        // Valida el formato de los tres campos.
        $this->validate([
            // Contraseña actual.
            'current_password' => ['required', 'string'],
            // Nueva: mínimo 8 caracteres, con letras y números, y repetida igual.
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()],
        ], attributes: [
            // Nombres para los mensajes de error.
            'current_password' => 'contraseña actual',
            'password' => 'contraseña nueva',
        ]);

        /** @var Client $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // La contraseña actual tiene que ser la correcta.
        if (! $client->passwordMatches($this->current_password)) {
            // Corta sin guardar.
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
        }

        // La nueva no puede ser el DNI: es justamente lo que se quiere dejar de usar.
        if (Client::normalizeDni($this->password) === Client::normalizeDni($client->dni)) {
            // Corta sin guardar.
            throw ValidationException::withMessages(['password' => 'Elegí una contraseña distinta de tu DNI.']);
        }

        // ¿Este dispositivo había quedado recordado? (tiene la cookie de "recordarme").
        $wasRemembered = request()->hasCookie(Auth::guard('client')->getRecallerName());

        // Guarda la contraseña nueva (el modelo la hashea).
        $client->password = $this->password;
        // Token nuevo: los "recordarme" de los otros dispositivos dejan de valer.
        $client->setRememberToken(Str::random(60));
        $client->save();

        // Esta sesión pasa a ser la de la contraseña nueva: las demás se cierran solas.
        EnsureMemberSessionIsCurrent::stampSession($client);

        // Si este dispositivo estaba recordado, le emite la cookie con el token nuevo.
        if ($wasRemembered) {
            // Vuelve a iniciar la sesión acá mismo, recordada.
            Auth::guard('client')->login($client, remember: true);
        }

        // Vacía el formulario.
        $this->reset('current_password', 'password', 'password_confirmation');
        // Muestra el aviso de éxito.
        $this->saved = true;
    }

    /**
     * Arma la pantalla con los datos del socio.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - La vista recibe $client. No modifica nada.
     *
     * @return View La vista livewire.member-account.
     */
    public function render(): View
    {
        // Devuelve la vista con el socio de la sesión.
        return view('livewire.member-account', [
            // Socio.
            'client' => Auth::guard('client')->user(),
        ]);
    }
}
