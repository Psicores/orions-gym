<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga al socio a elegir una contraseña propia antes de usar el portal: mientras su contraseña
 * siga siendo el DNI, solo puede entrar a "Mi cuenta" (donde la cambia) y cerrar sesión.
 */
class EnsureMemberHasOwnPassword
{
    /**
     * Deja pasar al socio con contraseña propia y manda a "Mi cuenta" al que todavía usa el DNI.
     *
     * Precondiciones:
     * - La ruta ya pasó por "auth:client": hay un socio con sesión iniciada.
     *
     * Postcondiciones:
     * - Si el socio ya eligió una contraseña (ver Client::hasCustomPassword()), sigue normalmente.
     * - Si no, redirige a /mi-perfil/cuenta y no muestra la pantalla pedida.
     * - No modifica nada.
     *
     * @param  Request  $request  Pedido actual.
     * @param  Closure(Request): (Response)  $next  Resto de la cadena de middlewares.
     * @return Response La pantalla pedida o la redirección a "Mi cuenta".
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Client|null $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // Con el DNI como contraseña no puede seguir: primero tiene que cambiarla.
        if ($client !== null && ! $client->hasCustomPassword()) {
            // Lo lleva a la pantalla donde se cambia.
            return redirect()->route('member.account');
        }

        // Con contraseña propia, sigue.
        return $next($request);
    }
}
