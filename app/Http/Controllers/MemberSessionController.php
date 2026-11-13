<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ingreso y salida del sitio: una sola pantalla de login para socios y personal.
 *
 * Los socios son el modelo Client (guard "client") y van a su portal en /mi-perfil; el personal
 * es el modelo User (guard "web") y va al panel /admin. El panel conserva además su login propio
 * en /admin/login.
 */
class MemberSessionController extends Controller
{
    // Intentos fallidos permitidos por usuario e IP antes de frenar el ingreso por un minuto.
    private const int MAX_ATTEMPTS = 5;

    // Ingresos que puede intentar una misma IP por minuto, acierte o no.
    private const int MAX_ATTEMPTS_PER_IP_PER_MINUTE = 10;

    // Ingresos que puede intentar una misma IP por hora, acierte o no.
    private const int MAX_ATTEMPTS_PER_IP_PER_HOUR = 60;

    /**
     * Muestra la pantalla de ingreso.
     *
     * Precondiciones:
     * - Quien la pide no tiene sesión iniciada (lo garantiza el middleware "guest" de la ruta).
     *
     * Postcondiciones:
     * - Devuelve la vista del login. No modifica nada.
     *
     * @return View La vista auth.login.
     */
    public function create(): View
    {
        // Pantalla oscura, con el mismo estilo de la página pública.
        return view('auth.login');
    }

    /**
     * Inicia la sesión de un socio o de un miembro del personal y lo manda a su lugar.
     *
     * Precondiciones:
     * - El pedido trae "usuario" (DNI o email) y "password".
     *
     * Postcondiciones:
     * - Si corresponden a un socio no dado de baja, inicia su sesión (queda recordada en el
     *   dispositivo) y redirige a la pantalla del portal que quería ver o, si no, a /mi-perfil.
     * - Si no, y corresponden a un usuario del personal con acceso al panel, inicia su sesión y
     *   redirige a /admin.
     * - Si no corresponden a nadie, vuelve al formulario con un error y no inicia ninguna sesión.
     * - Tras 5 intentos fallidos seguidos con el mismo usuario desde la misma IP, rechaza los
     *   siguientes durante un minuto, aunque los datos sean correctos.
     * - Una misma IP puede intentar hasta 10 ingresos por minuto y 60 por hora, acierte o no y
     *   con el usuario que sea: pasado ese tope rechaza los siguientes. Evita que alguien
     *   pruebe un DNI tras otro.
     *
     * @param  Request  $request  Pedido con los datos del formulario.
     * @return RedirectResponse Redirección al portal o al panel.
     *
     * @throws ValidationException Si faltan datos, son incorrectos o hay demasiados intentos.
     */
    public function store(Request $request): RedirectResponse
    {
        // Exige los dos campos.
        $data = $request->validate([
            // DNI o email.
            'usuario' => ['required', 'string', 'max:255'],
            // Contraseña.
            'password' => ['required', 'string', 'max:255'],
        ], attributes: ['usuario' => 'DNI o email', 'password' => 'contraseña']);

        // Tope por IP: frena a quien prueba muchos usuarios distintos desde el mismo lugar.
        $this->ensureIpIsNotFlooding($request);

        // Clave del contador de intentos: usuario escrito + IP.
        $throttleKey = Str::transliterate(Str::lower(trim($data['usuario'])).'|'.$request->ip());

        // Con demasiados intentos fallidos, corta antes de mirar las credenciales.
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            // Avisa cuántos segundos faltan para volver a probar.
            throw ValidationException::withMessages([
                'usuario' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        // Primero prueba como socio.
        $client = Client::findForLogin($data['usuario']);

        // Socio encontrado y contraseña correcta.
        if ($client?->passwordMatches($data['password'])) {
            // Inicia la sesión del socio y la deja recordada: el portal se usa como una app del celular.
            Auth::guard('client')->login($client, remember: true);

            // Termina el ingreso y lo lleva a donde iba o a su inicio.
            return $this->finishLogin($request, $throttleKey, redirect()->intended(route('member.dashboard')));
        }

        // Después prueba como personal, que entra con su email.
        if (Auth::guard('web')->attempt(['email' => trim($data['usuario']), 'password' => $data['password']])) {
            // Solo sirve si además puede entrar al panel (por ejemplo, tiene el email verificado).
            if (Auth::guard('web')->user()->canAccessPanel(Filament::getPanel('admin'))) {
                // Termina el ingreso y lo lleva al panel.
                return $this->finishLogin($request, $throttleKey, redirect()->to(Filament::getPanel('admin')->getUrl()));
            }

            // Sin acceso al panel no tiene a dónde ir: cierra esa sesión.
            Auth::guard('web')->logout();
        }

        // Cuenta el intento fallido (se olvida al minuto).
        RateLimiter::hit($throttleKey);

        // Vuelve al formulario sin decir qué dato estuvo mal.
        throw ValidationException::withMessages([
            'usuario' => 'El DNI o la contraseña no son correctos.',
        ]);
    }

    /**
     * Cuenta el intento de ingreso de esta IP y lo rechaza si ya hizo demasiados.
     *
     * Precondiciones:
     * - $request es el envío del formulario de ingreso.
     *
     * Postcondiciones:
     * - Si la IP ya llegó al tope del minuto o al de la hora, lanza el error y no cuenta nada más.
     * - Si no, suma un intento a los dos contadores (se cuentan todos, también los correctos:
     *   entrar con el DNI como contraseña es un acierto para quien prueba DNI ajenos).
     *
     * @param  Request  $request  Pedido actual.
     *
     * @throws ValidationException Si la IP superó alguno de los dos topes.
     */
    private function ensureIpIsNotFlooding(Request $request): void
    {
        // Contadores de esta IP: clave => [tope, segundos que dura la cuenta].
        $limits = [
            'login-ip-minute|'.$request->ip() => [self::MAX_ATTEMPTS_PER_IP_PER_MINUTE, 60],
            'login-ip-hour|'.$request->ip() => [self::MAX_ATTEMPTS_PER_IP_PER_HOUR, 3600],
        ];

        // Primero revisa los dos topes.
        foreach ($limits as $key => [$maxAttempts]) {
            // Con el tope alcanzado, corta antes de mirar las credenciales.
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                // Avisa cuántos segundos faltan para volver a probar.
                throw ValidationException::withMessages([
                    'usuario' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        }

        // Después cuenta este intento en los dos.
        foreach ($limits as $key => [, $decaySeconds]) {
            // Se olvida solo al pasar el minuto o la hora.
            RateLimiter::hit($key, $decaySeconds);
        }
    }

    /**
     * Cierra la sesión del socio.
     *
     * Precondiciones:
     * - Hay un socio con sesión iniciada (lo garantiza el middleware "auth:client" de la ruta).
     *
     * Postcondiciones:
     * - El socio queda sin sesión y sin "recordarme" en este dispositivo.
     * - Si en el mismo navegador había una sesión del personal, no la toca.
     * - Redirige a la pantalla de ingreso.
     *
     * @param  Request  $request  Pedido actual.
     * @return RedirectResponse Redirección al login.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Cierra solo la sesión del portal.
        Auth::guard('client')->logout();

        // Renueva el token de formularios para que no se reutilice el anterior.
        $request->session()->regenerateToken();

        // Vuelve a la pantalla de ingreso.
        return redirect()->route('login');
    }

    /**
     * Pasos comunes a todo ingreso correcto.
     *
     * Precondiciones:
     * - Ya se inició la sesión en el guard que corresponde.
     *
     * Postcondiciones:
     * - El contador de intentos fallidos de ese usuario queda en cero.
     * - La sesión tiene un identificador nuevo (evita que se reutilice uno anterior al ingreso).
     * - Devuelve la misma redirección recibida.
     *
     * @param  Request  $request  Pedido actual.
     * @param  string  $throttleKey  Clave del contador de intentos.
     * @param  RedirectResponse  $redirect  A dónde va la persona.
     * @return RedirectResponse La redirección recibida.
     */
    private function finishLogin(Request $request, string $throttleKey, RedirectResponse $redirect): RedirectResponse
    {
        // Olvida los intentos fallidos anteriores.
        RateLimiter::clear($throttleKey);
        // Cambia el identificador de sesión.
        $request->session()->regenerate();

        // Sigue hacia su destino.
        return $redirect;
    }
}
