<?php

namespace App\Models;

use Database\Factories\AccessPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Punto de acceso: un lugar del gimnasio con su propio QR impreso (recepción, otro salón...).
 *
 * El QR lleva la dirección /ingreso/{token}. El socio la abre con la cámara del celular, confirma
 * y queda registrado su ingreso (ver App\Http\Controllers\CheckInController). Apagar el punto, o
 * cambiarle el código, invalida el QR impreso.
 */
#[Fillable(['nombre', 'activo', 'activity_id'])]
// El código nunca sale al convertir el punto a array o JSON.
#[Hidden(['token'])]
class AccessPoint extends Model
{
    /** @use HasFactory<AccessPointFactory> */
    use HasFactory;

    /** Largo del código al azar de cada punto. */
    public const int TOKEN_LENGTH = 40;

    /**
     * Le da un código al azar a cada punto nuevo.
     *
     * Precondiciones:
     * - Ninguna: Eloquent lo llama una vez al cargar el modelo.
     *
     * Postcondiciones:
     * - Al crear un punto sin token, recibe uno de TOKEN_LENGTH caracteres al azar.
     */
    protected static function booted(): void
    {
        // Antes de guardar un punto nuevo.
        static::creating(function (AccessPoint $accessPoint): void {
            // Código al azar si no trae uno.
            $accessPoint->token ??= Str::random(self::TOKEN_LENGTH);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Prendido o apagado.
            'activo' => 'boolean',
        ];
    }

    /**
     * Busca el punto de acceso activo que corresponde a lo que leyó un QR.
     *
     * Precondiciones:
     * - Ninguna: acepta cualquier texto.
     *
     * Postcondiciones:
     * - Acepta la dirección completa del QR (".../ingreso/{token}", con o sin barra o parámetros
     *   al final) o el código solo (los QR impresos antes de los puntos de acceso).
     * - Devuelve el punto activo con ese código, o null si no existe o está apagado.
     * - No modifica nada.
     *
     * @param  string  $scanned  Texto leído del QR.
     * @return self|null El punto de acceso, o null.
     */
    public static function findActiveFromQr(string $scanned): ?self
    {
        // Sin espacios en las puntas.
        $scanned = trim($scanned);

        // Si es una dirección de ingreso, se queda con el código.
        if (preg_match('#/ingreso/([^/?\#]+)#', $scanned, $matches) === 1) {
            $scanned = rawurldecode($matches[1]);
        }

        // Un código vacío o demasiado largo no puede ser de un punto.
        if ($scanned === '' || mb_strlen($scanned) > 64) {
            return null;
        }

        // Punto activo con ese código.
        return static::query()->where('token', $scanned)->where('activo', true)->first();
    }

    /**
     * Dirección que lleva el QR de este punto.
     *
     * Precondiciones:
     * - APP_URL tiene el dominio con el que se va a usar el sitio.
     *
     * Postcondiciones:
     * - Devuelve la dirección absoluta /ingreso/{token}. Si cambia el dominio, cambia la dirección
     *   y hay que reimprimir el QR. No modifica nada.
     *
     * @return string Dirección del QR.
     */
    public function checkInUrl(): string
    {
        // Dirección absoluta según APP_URL.
        return route('check-in.show', $this->token);
    }

    /**
     * Le da un código nuevo al punto: el QR impreso deja de servir.
     *
     * Precondiciones:
     * - El punto existe en la base.
     *
     * Postcondiciones:
     * - El token cambia por uno nuevo al azar y queda guardado. Hay que imprimir el QR nuevo.
     */
    public function regenerateToken(): void
    {
        // Código nuevo y guardado.
        $this->forceFill(['token' => Str::random(self::TOKEN_LENGTH)])->save();
    }

    /**
     * Ingresos registrados por este punto.
     *
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        // Busca en attendances las filas con este access_point_id.
        return $this->hasMany(Attendance::class);
    }
}
