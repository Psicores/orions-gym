<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Recibe los avisos del navegador cuando algo de la página no cumple la política de contenido
 * (Content-Security-Policy-Report-Only, ver SecurityHeaders) y los anota en el log.
 *
 * Sirve para ver qué bloquearía la política completa antes de aplicarla: si después de un tiempo
 * de uso normal no aparecen avisos (o solo los de extensiones del navegador), se puede activar.
 */
class CspReportController extends Controller
{
    /** Tamaño máximo del aviso que se acepta, en bytes: uno real ocupa menos de 2 KB. */
    private const int MAX_BYTES = 8192;

    /** Datos del aviso que se guardan; el resto se descarta. */
    private const array FIELDS = [
        'document-uri',
        'effective-directive',
        'violated-directive',
        'blocked-uri',
        'source-file',
        'line-number',
        'column-number',
    ];

    /**
     * Anota en el log un aviso de la política de contenido.
     *
     * Precondiciones:
     * - El pedido es un POST del navegador con el aviso en JSON, con la forma
     *   {"csp-report": {...}} (formato de la directiva report-uri).
     * - La ruta tiene límite de pedidos por IP y no exige token CSRF (el navegador no lo manda).
     *
     * Postcondiciones:
     * - Si el aviso es válido y pesa hasta MAX_BYTES, escribe en el log una advertencia con los
     *   datos de FIELDS, cada uno recortado a 300 caracteres.
     * - Si no es válido o es demasiado grande, no escribe nada.
     * - Siempre responde 204 (sin contenido): quien lo manda no se entera de qué se guardó.
     *
     * @param  Request  $request  Pedido con el aviso.
     * @return Response Respuesta vacía.
     */
    public function __invoke(Request $request): Response
    {
        // Cuerpo crudo: el navegador lo manda como application/csp-report, que Laravel no interpreta solo.
        $body = $request->getContent();

        // Solo avisos de tamaño razonable.
        if ($body !== '' && strlen($body) <= self::MAX_BYTES) {
            // Interpreta el JSON (null si no lo es).
            $payload = json_decode($body, true);
            // El aviso viene dentro de "csp-report".
            $report = is_array($payload) ? ($payload['csp-report'] ?? null) : null;

            // Con un aviso con forma de aviso, lo anota.
            if (is_array($report)) {
                // Solo los datos útiles, recortados, para que nadie llene el log con texto largo.
                $data = collect($report)
                    ->only(self::FIELDS)
                    ->map(fn (mixed $value): string => Str::limit(is_scalar($value) ? (string) $value : '', 300))
                    ->all();

                // Advertencia en el log de Laravel (storage/logs/laravel.log).
                Log::warning('Aviso de la política de contenido (CSP)', $data);
            }
        }

        // Respuesta vacía en cualquier caso.
        return response()->noContent();
    }
}
