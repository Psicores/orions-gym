<?php

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago: plata que entregó un cliente para una de sus membresías.
 *
 * Guarda cuánto pagó, cómo, cuándo y qué usuario del staff lo cobró
 * (registrado_por_user_id), para poder rendir cuentas de la caja.
 */
#[Fillable([
    'client_id',
    'membership_id',
    'monto',
    'metodo_pago',
    'fecha_pago',
    'registrado_por_user_id',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - monto guarda un decimal con 2 decimales.
     * - metodo_pago guarda uno de los valores de PaymentMethodEnum (efectivo, transferencia).
     * - fecha_pago guarda fecha y hora.
     *
     * Postcondiciones:
     * - monto se lee como texto con 2 decimales (por ejemplo "25000.50"), sin errores de redondeo.
     * - $payment->metodo_pago devuelve un caso de PaymentMethodEnum, y al asignarle un caso
     *   se guarda su valor de texto.
     * - fecha_pago se lee como objeto Carbon.
     * - Si la base tiene un medio que no existe en el enum, leerlo lanza un ValueError.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // Monto como decimal exacto de 2 posiciones (plata: nunca float).
            'monto' => 'decimal:2',
            // Medio de pago como el enum PaymentMethodEnum en lugar de texto suelto.
            'metodo_pago' => PaymentMethodEnum::class,
            // Fecha y hora del cobro (Carbon).
            'fecha_pago' => 'datetime',
        ];
    }

    /**
     * Cliente que hizo el pago.
     *
     * Precondiciones:
     * - client_id apunta a un cliente existente (la clave foránea lo garantiza).
     *
     * Postcondiciones:
     * - Devuelve la relación "el pago pertenece a un cliente", sin ejecutar la consulta.
     * - Incluye clientes dados de baja: el historial de caja no pierde a quién se le cobró.
     * - No modifica datos.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        // Une payments.client_id con clients.id.
        return $this->belongsTo(Client::class)
            // Incluye clientes dados de baja para no perder el historial.
            ->withTrashed();
    }

    /**
     * Membresía a la que se imputó el pago.
     *
     * Precondiciones:
     * - membership_id apunta a una membresía existente (la clave foránea lo garantiza).
     *
     * Postcondiciones:
     * - Devuelve la relación "el pago pertenece a una membresía", sin ejecutar la consulta.
     * - No modifica datos.
     *
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        // Une payments.membership_id con memberships.id.
        return $this->belongsTo(Membership::class);
    }

    /**
     * Usuario del staff (dueño o profe) que cobró y registró el pago.
     *
     * Precondiciones:
     * - registrado_por_user_id apunta a un usuario existente (la clave foránea lo garantiza).
     *
     * Postcondiciones:
     * - Devuelve la relación "el pago fue registrado por un usuario", sin ejecutar la consulta.
     * - $payment->registradoPor devuelve el User que lo cobró.
     * - No modifica datos.
     *
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        // Une payments.registrado_por_user_id con users.id (la columna no sigue la convención, por eso se indica).
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
