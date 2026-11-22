<?php

namespace App\Models;

use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asistencia: un ingreso de un socio al gimnasio.
 *
 * La registra el propio socio al escanear el QR de recepción desde su portal (ver
 * Client::registerAttendance()). Cada asistencia descuenta un crédito de la membresía con la que entró.
 */
#[Fillable([
    'client_id',
    'membership_id',
    'fecha_hora',
])]
class Attendance extends Model
{
    /** Código del QR de desarrollo (valor por defecto de config/gym.php): es público, no sirve en producción. */
    public const string DEVELOPMENT_TOKEN = 'orions-ingreso-sede-1';

    /** Largo mínimo del código del QR en producción, para que no se pueda adivinar. */
    public const int MIN_PRODUCTION_TOKEN_LENGTH = 16;

    /**
     * Devuelve el código que tiene que llevar el QR de recepción, o null si no hay uno usable.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Fuera de producción devuelve config('gym.checkin_token') sin espacios en las puntas, o
     *   DEVELOPMENT_TOKEN si está vacío (así el QR de prueba funciona sin configurar nada).
     * - En producción devuelve null si el código está vacío, es DEVELOPMENT_TOKEN (está en el código, lo
     *   conoce cualquiera) o tiene menos de MIN_PRODUCTION_TOKEN_LENGTH caracteres. Con null el
     *   ingreso por QR queda deshabilitado y el panel pide configurarlo.
     * - No modifica nada.
     *
     * @return string|null El código del QR, o null si falta configurarlo.
     */
    public static function receptionToken(): ?string
    {
        // Código configurado (GYM_CHECKIN_TOKEN), sin espacios en las puntas.
        $token = trim((string) config('gym.checkin_token'));

        // Fuera de producción, sin código configurado se usa el de prueba.
        if (! app()->isProduction()) {
            // El configurado o, si está vacío, el de desarrollo.
            return $token !== '' ? $token : self::DEVELOPMENT_TOKEN;
        }

        // En producción no se acepta uno vacío, el de desarrollo ni uno corto.
        if ($token === self::DEVELOPMENT_TOKEN || mb_strlen($token) < self::MIN_PRODUCTION_TOKEN_LENGTH) {
            // Inseguro: se trata como si faltara.
            return null;
        }

        // Código usable.
        return $token;
    }

    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - La columna fecha_hora guarda una fecha con hora.
     *
     * Postcondiciones:
     * - $attendance->fecha_hora devuelve un objeto Carbon.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // El momento del ingreso se maneja como fecha y hora (Carbon).
            'fecha_hora' => 'datetime',
        ];
    }

    /**
     * Socio que ingresó.
     *
     * Precondiciones:
     * - client_id apunta a un cliente existente (la clave foránea lo garantiza).
     *
     * Postcondiciones:
     * - Devuelve la relación "la asistencia pertenece a un cliente", sin ejecutar la consulta.
     * - Incluye clientes dados de baja: el historial no se pierde.
     * - No modifica datos.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        // Une attendances.client_id con clients.id.
        return $this->belongsTo(Client::class)
            // Incluye clientes dados de baja para no perder el historial.
            ->withTrashed();
    }

    /**
     * Membresía con la que ingresó (la que perdió un crédito).
     *
     * Precondiciones:
     * - membership_id apunta a una membresía existente (la clave foránea lo garantiza).
     *
     * Postcondiciones:
     * - Devuelve la relación "la asistencia pertenece a una membresía", sin ejecutar la consulta.
     * - No modifica datos.
     *
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        // Une attendances.membership_id con memberships.id.
        return $this->belongsTo(Membership::class);
    }
}
