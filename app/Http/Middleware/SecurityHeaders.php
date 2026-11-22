<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agrega las cabeceras de seguridad a todas las respuestas del sitio (landing, portal y panel).
 */
class SecurityHeaders
{
    /**
     * Política de contenido que se aplica desde ya: no restringe scripts, estilos ni imágenes
     * (así no rompe nada), pero bloquea plugins, el cambio de la dirección base de la página,
     * los formularios que envían a otro sitio y que otro sitio muestre este dentro de un marco.
     */
    public const string ENFORCED_POLICY = "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    /**
     * Política completa, por ahora solo en modo aviso: todo tiene que venir del propio sitio.
     * Scripts y estilos en línea siguen permitidos porque los usan Livewire, Alpine y Filament;
     * lo que se gana es que la página no pueda cargar ni mandar datos a otros dominios.
     * Las únicas excepciones son las imágenes en data:/blob: (vistas previas de subidas) y
     * Google Maps en un marco (el mapa de la landing, cuando se cargue).
     *
     * @var array<string, string>
     */
    public const array REPORT_ONLY_POLICY = [
        'default-src' => "'self'",
        'script-src' => "'self' 'unsafe-inline' 'unsafe-eval'",
        'style-src' => "'self' 'unsafe-inline'",
        'img-src' => "'self' data: blob:",
        'font-src' => "'self' data:",
        'media-src' => "'self' blob:",
        'connect-src' => "'self'",
        'frame-src' => "'self' https://www.google.com",
        'worker-src' => "'self' blob:",
        'manifest-src' => "'self'",
        'object-src' => "'none'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
        'frame-ancestors' => "'self'",
    ];

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
     * - Lleva la política de contenido ENFORCED_POLICY (Content-Security-Policy) y, en modo
     *   aviso, REPORT_ONLY_POLICY (Content-Security-Policy-Report-Only): el navegador no
     *   bloquea nada de esta última, solo manda a /csp-reportes lo que habría bloqueado.
     * - Con el servidor de desarrollo de Vite encendido, su dirección se suma a la política en
     *   modo aviso, para que no llene el log de avisos falsos.
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

        // Reglas que se aplican desde ya.
        $response->headers->set('Content-Security-Policy', self::ENFORCED_POLICY);
        // Política completa en modo aviso.
        $response->headers->set('Content-Security-Policy-Report-Only', $this->reportOnlyPolicy());

        // HSTS solo tiene sentido (y solo se respeta) sobre HTTPS.
        if ($request->secure()) {
            // Un año, sin subdominios: no se sabe qué más hay en el dominio.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Devuelve la respuesta ya protegida.
        return $response;
    }

    /**
     * Arma el texto de la política en modo aviso.
     *
     * Precondiciones:
     * - Existe la ruta csp.report.
     *
     * Postcondiciones:
     * - Devuelve REPORT_ONLY_POLICY como texto de cabecera, con report-uri apuntando a la ruta
     *   csp.report (sin dominio).
     * - Si corre el servidor de desarrollo de Vite, su dirección (y la del websocket de recarga)
     *   queda permitida para scripts, estilos, fuentes, imágenes y conexiones.
     * - No modifica nada.
     *
     * @return string La política lista para la cabecera.
     */
    private function reportOnlyPolicy(): string
    {
        // Parte de la política fija.
        $policy = self::REPORT_ONLY_POLICY;

        // En desarrollo con "npm run dev" los archivos vienen de otro puerto.
        if (Vite::isRunningHot()) {
            // Dirección del servidor de Vite (está en public/hot).
            $vite = rtrim((string) file_get_contents(public_path('hot')));
            // La misma dirección como websocket, para la recarga automática.
            $socket = preg_replace('/^http/', 'ws', $vite);

            // La suma a lo que puede cargar desde ahí.
            foreach (['script-src', 'style-src', 'font-src', 'img-src'] as $directive) {
                // Agrega el origen de Vite.
                $policy[$directive] .= ' '.$vite;
            }
            // Y las conexiones (incluido el websocket).
            $policy['connect-src'] .= ' '.$vite.' '.$socket;
        }

        // Adónde manda los avisos el navegador.
        $policy['report-uri'] = route('csp.report', absolute: false);

        // Une las directivas en el formato de la cabecera.
        return collect($policy)
            ->map(fn (string $value, string $directive): string => $directive.' '.$value)
            ->implode('; ');
    }
}
