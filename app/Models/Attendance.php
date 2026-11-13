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
