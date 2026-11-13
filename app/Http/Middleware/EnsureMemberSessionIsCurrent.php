<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra las sesiones del portal que quedaron abiertas con una contraseña que ya no vale:
 * cuando el socio la cambia (o el personal se la restablece), los otros dispositivos donde
 * había entrado tienen que volver a ingresar.
 *
 * Cada sesión guarda una huella de la contraseña con la que se abrió (Client::passwordStamp());
 * si la huella actual del socio es otra, esa sesión se cierra.
 */
class EnsureMemberSessionIsCurrent
{
    // Clave de la sesión donde se guarda la huella de la contraseña.
    public const string SESSION_KEY = 'member_password_stamp';

    /**
     * Deja pasar a la sesión abierta con la contraseña vigente y cierra la que quedó vieja.
     *
     * Precondiciones:
     * - La ruta ya pasó por "auth:client": hay un socio con sesión iniciada.
     *
     * Postcondiciones:
     * - Si la sesión todavía no tiene huella de este socio (primer pedido después de ingresar,
     *   o el navegador lo usó antes otro socio), guarda la actual y sigue.
     * - Si la huella guardada coincide con la actual, sigue sin tocar nada.
     * - Si no coincide (la contraseña cambió desde otro dispositivo o fue restablecida), cierra
     *   la sesión del socio en este dispositivo y redirige al ingreso. No toca una sesión del
     *   personal que hubiera en el mismo navegador ni las sesiones de otros dispositivos.
     *
     * @param  Request  $request  Pedido actual.
     * @param  Closure(Request): (Response)  $next  Resto de la cadena de middlewares.
     * @return Response La pantalla pedida o la redirección al ingreso.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Client|null $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // Sin socio no hay nada que controlar (lo resuelve "auth:client").
        if ($client === null) {
            // Sigue.
            return $next($request);
        }

        // Huella con la que se abrió esta sesión.
        $stored = $request->session()->get(self::SESSION_KEY);

        // Sesión recién abierta, o que antes usó otro socio en este navegador: anota la huella vigente.
        if (! is_string($stored) || ! str_starts_with($stored, $client->getKey().'|')) {
            // La guarda para comparar en los próximos pedidos.
            self::stampSession($client);

            // Sigue.
            return $next($request);
        }

        // La contraseña cambió después de abrir esta sesión.
        if (! hash_equals($stored, self::stampFor($client))) {
            // Cierra la sesión del socio solo en este dispositivo (sin renovar el token de "recordarme").
            Auth::guard('client')->logoutCurrentDevice();
            // Borra la huella vieja.
            $request->session()->forget(self::SESSION_KEY);
            // Renueva el token de formularios.
            $request->session()->regenerateToken();

            // Lo manda a ingresar de nuevo.
            return redirect()->route('login');
        }

        // Huella vigente: sigue.
        return $next($request);
    }

    /**
     * Anota en la sesión actual la huella de la contraseña vigente del socio.
     *
     * Precondiciones:
     * - Hay una sesión activa y $client es el socio que la usa.
     *
     * Postcondiciones:
     * - La sesión actual queda marcada como abierta con la contraseña vigente, así no se cierra
     *   sola (se usa al ingresar y cuando el socio cambia su contraseña desde este dispositivo).
     *
     * @param  Client  $client  Socio dueño de la sesión.
     */
    public static function stampSession(Client $client): void
    {
        // Guarda la huella actual.
        session()->put(self::SESSION_KEY, self::stampFor($client));
    }

    /**
     * Valor que se guarda en la sesión: a qué socio pertenece y la huella de su contraseña.
     *
     * Precondiciones:
     * - $client existe en la base (tiene id).
     *
     * Postcondiciones:
     * - Devuelve "id|huella". El id adelante evita comparar la huella de un socio con la de
     *   otro que haya usado antes el mismo navegador.
     * - No modifica nada.
     *
     * @param  Client  $client  Socio dueño de la sesión.
     * @return string Valor a guardar y comparar.
     */
    public static function stampFor(Client $client): string
    {
        // Id del socio, separador y huella de su contraseña.
        return $client->getKey().'|'.$client->passwordStamp();
    }
}
