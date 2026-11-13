<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureMemberSessionIsCurrent;
use App\Models\Client;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
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

    // Clave de sesión donde se anota quién acertó la contraseña y todavía debe el código de dos pasos.
    private const string PENDING_TWO_FACTOR_KEY = 'staff_two_factor_pending';

    // Minutos que tiene para escribir el código antes de tener que volver a poner la contraseña.
    private const int TWO_FACTOR_MINUTES = 10;

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
     *   dispositivo solo si marcó "Mantener la sesión iniciada") y redirige a la pantalla del portal que quería ver o, si no, a /mi-perfil.
     * - Si no, y corresponden a un usuario del personal con acceso al panel, inicia su sesión y
     *   redirige a /admin. Si esa cuenta tiene verificación en dos pasos, todavía no inicia la
     *   sesión: lo manda a /ingresar/codigo, que pide el código (ver storeCode).
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
            // Inicia la sesión del socio; queda recordada solo si lo pidió (en un celular propio conviene).
            Auth::guard('client')->login($client, remember: $request->boolean('remember'));
            // Anota con qué contraseña se abrió esta sesión, para cerrarla si después cambia.
            EnsureMemberSessionIsCurrent::stampSession($client);

            // Termina el ingreso y lo lleva a donde iba o a su inicio.
            return $this->finishLogin($request, $throttleKey, redirect()->intended(route('member.dashboard')));
        }

        // Después prueba como personal, que entra con su email.
        if (Auth::guard('web')->attempt(['email' => trim($data['usuario']), 'password' => $data['password']])) {
            // Usuario del personal que acaba de acertar la contraseña.
            $user = Auth::guard('web')->user();

            // Solo sirve si además puede entrar al panel (por ejemplo, tiene el email verificado).
            if ($user->canAccessPanel(Filament::getPanel('admin'))) {
                // Con verificación en dos pasos, la contraseña sola no alcanza: falta el código.
                if ($this->twoFactorProvider($user) !== null) {
                    // Cierra la sesión recién abierta: todavía no demostró el segundo factor.
                    Auth::guard('web')->logout();
                    // El intento fue correcto: no cuenta como fallido.
                    RateLimiter::clear($throttleKey);
                    // Anota quién está a mitad del ingreso y hasta cuándo puede escribir el código.
                    $request->session()->put(self::PENDING_TWO_FACTOR_KEY, [
                        // Cuenta que ya acertó la contraseña.
                        'id' => $user->getKey(),
                        // Pasado este momento tiene que volver a escribir la contraseña.
                        'expires_at' => now()->addMinutes(self::TWO_FACTOR_MINUTES)->getTimestamp(),
                    ]);

                    // Lo lleva a la pantalla que pide el código.
                    return redirect()->route('login.code');
                }

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
     * Muestra la pantalla que pide el código de la verificación en dos pasos.
     *
     * Precondiciones:
     * - Quien la pide no tiene sesión iniciada (middleware "guest" de la ruta).
     *
     * Postcondiciones:
     * - Si hay un ingreso del personal a medio hacer y no venció, devuelve la vista del código.
     * - Si no, redirige a /ingresar. No modifica datos.
     *
     * @param  Request  $request  Pedido actual.
     * @return View|RedirectResponse La vista auth.two-factor o la redirección al ingreso.
     */
    public function createCode(Request $request): View|RedirectResponse
    {
        // Sin un ingreso a medio hacer no hay nada que verificar: vuelve al formulario.
        if ($this->pendingTwoFactorUser($request) === null) {
            // Redirige al ingreso.
            return redirect()->route('login');
        }

        // Muestra el formulario del código.
        return view('auth.two-factor');
    }

    /**
     * Verifica el código de la verificación en dos pasos y termina el ingreso del personal.
     *
     * Precondiciones:
     * - El pedido trae "codigo": el código de 6 dígitos de la app o un código de recuperación.
     * - Esa persona ya acertó su contraseña en /ingresar hace menos de 10 minutos.
     *
     * Postcondiciones:
     * - Si el código es correcto, inicia la sesión del personal y redirige a /admin. Un código
     *   de la app no se puede usar dos veces y un código de recuperación se gasta al usarlo.
     * - Si es incorrecto, vuelve al formulario con un error y no inicia ninguna sesión.
     * - Tras 5 códigos incorrectos seguidos de la misma cuenta desde la misma IP, rechaza los
     *   siguientes durante un minuto; además valen los topes por IP del ingreso.
     * - Si el ingreso a medio hacer venció o no existe, redirige a /ingresar.
     *
     * @param  Request  $request  Pedido con el código.
     * @return RedirectResponse Redirección al panel o al ingreso.
     *
     * @throws ValidationException Si falta el código, es incorrecto o hay demasiados intentos.
     */
    public function storeCode(Request $request): RedirectResponse
    {
        // Cuenta que está a mitad del ingreso; null si no hay o venció.
        $user = $this->pendingTwoFactorUser($request);

        // Sin ingreso a medio hacer, tiene que empezar de nuevo.
        if ($user === null) {
            // Redirige al ingreso.
            return redirect()->route('login');
        }

        // Valida que haya escrito algo razonable.
        $data = $request->validate(['codigo' => ['required', 'string', 'max:255']], attributes: ['codigo' => 'código']);

        // Frena a quien manda una ráfaga de intentos desde la misma IP.
        $this->ensureIpIsNotFlooding($request);

        // Clave del contador de códigos fallidos: la cuenta y la IP.
        $throttleKey = 'two-factor|'.$user->getKey().'|'.$request->ip();

        // Si ya falló demasiadas veces, no deja seguir probando.
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            // Vuelve al formulario diciendo cuánto falta para reintentar.
            throw ValidationException::withMessages([
                'codigo' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        // Método de segundo factor activo en la cuenta (la app de autenticación).
        $provider = $this->twoFactorProvider($user);
        // Lo que escribió, sin espacios (las apps muestran "123 456").
        $code = str_replace(' ', '', $data['codigo']);

        // Seis dígitos: es un código de la app; cualquier otra cosa se prueba como código de recuperación.
        $isValid = $provider !== null && (preg_match('/^\d{6}$/', $code) === 1
            // Código de la app; no acepta uno ya usado.
            ? $provider->verifyCode($code, $provider->getSecret($user), shouldPreventCodeReuse: true)
            // Código de recuperación; si es válido se gasta.
            : $provider->isRecoverable() && $provider->verifyRecoveryCode($code, $user));

        // Código incorrecto.
        if (! $isValid) {
            // Suma un intento fallido.
            RateLimiter::hit($throttleKey);

            // Vuelve al formulario con el error.
            throw ValidationException::withMessages(['codigo' => 'El código no es correcto.']);
        }

        // Ya no hay ingreso a medio hacer.
        $request->session()->forget(self::PENDING_TWO_FACTOR_KEY);
        // Inicia la sesión del personal.
        Auth::guard('web')->login($user);

        // Termina el ingreso y lo lleva al panel.
        return $this->finishLogin($request, $throttleKey, redirect()->to(Filament::getPanel('admin')->getUrl()));
    }

    /**
     * Devuelve la cuenta del personal que acertó la contraseña y todavía debe el código.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve el usuario anotado en la sesión si el plazo no venció, la cuenta existe y
     *   sigue teniendo la verificación en dos pasos activa.
     * - En cualquier otro caso borra la anotación de la sesión y devuelve null.
     *
     * @param  Request  $request  Pedido actual.
     * @return User|null La cuenta a mitad del ingreso, o null.
     */
    private function pendingTwoFactorUser(Request $request): ?User
    {
        // Anotación guardada al acertar la contraseña.
        $pending = $request->session()->get(self::PENDING_TWO_FACTOR_KEY);

        // Busca la cuenta solo si la anotación existe y no venció.
        $user = is_array($pending) && ($pending['expires_at'] ?? 0) >= now()->getTimestamp()
            // Cuenta anotada (null si fue borrada).
            ? User::query()->find($pending['id'] ?? null)
            // Sin anotación vigente.
            : null;

        // Si no hay cuenta o ya no usa el segundo factor, la anotación no sirve.
        if ($user === null || $this->twoFactorProvider($user) === null) {
            // La borra.
            $request->session()->forget(self::PENDING_TWO_FACTOR_KEY);

            // No hay ingreso a medio hacer.
            return null;
        }

        // Cuenta que debe el código.
        return $user;
    }

    /**
     * Devuelve el método de verificación en dos pasos que un usuario del personal tiene activo.
     *
     * Precondiciones:
     * - $user es una cuenta del personal.
     *
     * Postcondiciones:
     * - Devuelve la app de autenticación del panel si la cuenta la tiene activada; si no, null.
     * - No modifica nada.
     *
     * @param  User  $user  Cuenta del personal.
     * @return AppAuthentication|null El método activo, o null si no usa segundo factor.
     */
    private function twoFactorProvider(User $user): ?AppAuthentication
    {
        // Recorre los métodos de segundo factor configurados en el panel.
        return collect(Filament::getPanel('admin')->getMultiFactorAuthenticationProviders())
            // Se queda con la app de autenticación, si esta cuenta la tiene activada.
            ->first(fn (object $provider): bool => $provider instanceof AppAuthentication && $provider->isEnabled($user));
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
