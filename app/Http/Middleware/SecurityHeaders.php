<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agrega las cabeceras de seguridad a todas las respuestas del sitio (landing, portal y panel).
 */
class SecurityHeaders
{
    /**
     * Deja pasar el pedido y le suma las cabeceras de seguridad a la respuesta.
     *
     * Precondiciones:
     * - Está registrado como middleware global (ver bootstrap/app.php).
     *
     * Postcondiciones:
     * - El navegador no adivina el tipo de los archivos (X-Content-Type-Options).
     * - El sitio no se puede incrustar en un marco de otro dominio (X-Frame-Options).
     * - A otros sitios solo se les informa el dominio de origen, no la dirección completa
     *   (Referrer-Policy).
     * - La cámara solo la puede usar el propio sitio (el escáner de QR); micrófono, ubicación y
     *   pagos quedan apagados (Permissions-Policy).
     * - Si el pedido llegó por HTTPS, el navegador recuerda por un año entrar siempre por HTTPS
     *   (Strict-Transport-Security).
     * - La respuesta no dice qué versión de PHP corre el servidor (se quita X-Powered-By).
     * - No cambia el contenido de la respuesta.
     *
     * @param  Request  $request  Pedido actual.
     * @param  Closure(Request): (Response)  $next  Resto de la cadena de middlewares.
     * @return Response La respuesta con las cabeceras agregadas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Resuelve el pedido normalmente.
        $response = $next($request);

        // Evita que el navegador interprete un archivo como otro tipo (por ejemplo, una imagen como script).
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Evita que otro sitio muestre este adentro de un marco para engañar con clics.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Al salir a otro sitio solo se informa el dominio.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // La cámara queda permitida solo para el propio sitio: la usa el escáner de QR.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');

        // PHP agrega X-Powered-By por su cuenta (fuera de la respuesta de Laravel): se quita ahí.
        if (! headers_sent()) {
            // No hace falta anunciar la versión de PHP.
            header_remove('X-Powered-By');
        }
        // Por si viniera también dentro de la respuesta.
        $response->headers->remove('X-Powered-By');

        // HSTS solo tiene sentido (y solo se respeta) sobre HTTPS.
        if ($request->secure()) {
            // Un año, sin subdominios: no se sabe qué más hay en el dominio.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Devuelve la respuesta ya protegida.
        return $response;
    }
}
