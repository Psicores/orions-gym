<?php

namespace App\Models;

use App\Enums\ClientStatusEnum;
use App\Enums\MembershipStatusEnum;
use App\Enums\PaymentMethodEnum;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

#[Fillable([
    'dni',
    'nombre',
    'apellido',
    'fecha_nacimiento',
    'cel',
    'email',
    'foto_path',
    'certificado_salud_path',
    'observaciones_dolencias',
    'estado',
    'fecha_vencimiento',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - Las columnas fecha_nacimiento y fecha_vencimiento guardan fechas (Y-m-d) o null.
     * - La columna estado guarda uno de los valores de ClientStatusEnum
     *   (entrenando, en_pausa, activo_temporal, inactivo).
     *
     * Postcondiciones:
     * - $client->fecha_nacimiento y $client->fecha_vencimiento devuelven un objeto Carbon
     *   (o null si la columna está vacía).
     * - $client->estado devuelve un caso de ClientStatusEnum, y al asignarle un caso del enum
     *   se guarda su valor de texto.
     * - Si la base tiene un estado que no existe en el enum, leerlo lanza un ValueError.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // La fecha de nacimiento se maneja como fecha (Carbon) en lugar de texto.
            'fecha_nacimiento' => 'date',
            // El estado se maneja como el enum ClientStatusEnum en lugar de texto suelto.
            'estado' => ClientStatusEnum::class,
            // La fecha de vencimiento de la cuota se maneja como fecha (Carbon).
            'fecha_vencimiento' => 'date',
        ];
    }

    /**
     * Membresías (contratos con un plan) que tuvo y tiene el cliente.
     *
     * Precondiciones:
     * - Existe la tabla memberships con la columna client_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "un cliente tiene muchas membresías", sin ejecutar la consulta.
     * - $client->memberships devuelve una colección (vacía si no tiene ninguna).
     * - No modifica datos.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        // Busca en memberships las filas cuyo client_id es el id de este cliente.
        return $this->hasMany(Membership::class);
    }

    /**
     * Pagos que hizo el cliente, de cualquiera de sus membresías.
     *
     * Precondiciones:
     * - Existe la tabla payments con la columna client_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "un cliente tiene muchos pagos", sin ejecutar la consulta.
     * - $client->payments devuelve una colección (vacía si nunca pagó).
     * - No modifica datos.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        // Busca en payments las filas cuyo client_id es el id de este cliente.
        return $this->hasMany(Payment::class);
    }

    /**
     * Registra un pago del cliente imputado a una de sus membresías.
     *
     * Es el único punto de entrada para guardar pagos: hoy lo usa la acción "Registrar pago"
     * del panel (cobro manual), y el día que se sume una pasarela (por ejemplo Mercado Pago)
     * su webhook debería llamar a este mismo método con un nuevo caso de PaymentMethodEnum,
     * así las validaciones quedan en un solo lugar.
     *
     * Precondiciones:
     * - El cliente existe en la base.
     * - $membership pertenece a este cliente y su estado admite pagos (no está vencida).
     * - $monto es un número mayor a 0 con hasta 2 decimales (por ejemplo "25000.50").
     * - $registradoPor es el usuario del staff que cobró (en el panel, el usuario logueado).
     *
     * Postcondiciones:
     * - Se creó y devolvió un Payment con este cliente, la membresía, el monto, el medio,
     *   fecha_pago = ahora y registrado_por_user_id = $registradoPor->id.
     * - El cliente queda en estado Entrenando (cuota al día), tanto en la base como en esta
     *   instancia del modelo.
     * - No cambia la fecha de vencimiento del cliente ni el estado de la membresía.
     * - El pago y el cambio de estado se guardan juntos: si uno falla, no queda ninguno.
     * - Si alguna precondición sobre la membresía o el monto no se cumple, lanza la excepción
     *   y no guarda nada.
     *
     * @param  Membership  $membership  Membresía a la que se imputa el pago.
     * @param  string  $monto  Monto cobrado, como texto decimal para no perder centavos.
     * @param  PaymentMethodEnum  $metodoPago  Medio con el que pagó.
     * @param  User  $registradoPor  Usuario que cobró.
     * @return Payment El pago recién guardado.
     *
     * @throws InvalidArgumentException Si la membresía es de otro cliente, está vencida o el monto no es positivo.
     */
    public function registerPayment(Membership $membership, string $monto, PaymentMethodEnum $metodoPago, User $registradoPor): Payment
    {
        // Evita imputar el pago a la membresía de otra persona.
        if ($membership->client_id !== $this->id) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('La membresía no pertenece a este cliente.');
        }

        // Evita cobrar sobre una membresía cerrada (vencida).
        if (! $membership->estado->acceptsPayments()) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('La membresía está vencida y no admite pagos.');
        }

        // Evita montos vacíos, cero, negativos o que no sean números.
        if (! is_numeric($monto) || (float) $monto <= 0) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('El monto debe ser mayor a cero.');
        }

        // Guarda el pago y el nuevo estado en una transacción: o se guardan los dos o ninguno.
        return DB::transaction(function () use ($membership, $monto, $metodoPago, $registradoPor): Payment {
            // Crea el pago ya vinculado a este cliente (client_id lo completa la relación).
            $payment = $this->payments()->create([
                // Membresía a la que se imputa.
                'membership_id' => $membership->id,
                // Monto cobrado; el cast decimal:2 lo guarda con 2 decimales.
                'monto' => $monto,
                // Medio de pago; el cast del enum guarda su valor de texto.
                'metodo_pago' => $metodoPago,
                // Momento del cobro: ahora.
                'fecha_pago' => now(),
                // Quién cobró, para la rendición de caja.
                'registrado_por_user_id' => $registradoPor->id,
            ]);

            // Si pagó, la cuota queda al día: pasa el cliente a Entrenando y lo guarda.
            $this->update(['estado' => ClientStatusEnum::Entrenando]);

            // Devuelve el pago creado.
            return $payment;
        });
    }

    /**
     * Recalcula la fecha de vencimiento del cliente a partir de sus membresías.
     *
     * Precondiciones:
     * - El cliente existe en la base.
     *
     * Postcondiciones:
     * - fecha_vencimiento = la fecha de vencimiento más lejana entre sus membresías abiertas
     *   (todas menos las Vencidas).
     * - Si no tiene membresías abiertas, toma la más lejana entre todas (la última que tuvo).
     * - Si no tiene ninguna membresía, queda vacía (null).
     * - El valor queda guardado en la base y en esta instancia. Si no cambió, no escribe nada.
     * - No cambia el estado del cliente.
     */
    public function syncFechaVencimiento(): void
    {
        // Vencimiento más lejano entre las membresías abiertas (no vencidas).
        $fechaVencimiento = $this->memberships()
            // Excluye las membresías cerradas.
            ->where('estado', '!=', MembershipStatusEnum::Vencida)
            // Toma la fecha máxima (null si no hay ninguna).
            ->max('fecha_vencimiento')
            // Si no hay abiertas, usa la fecha máxima entre todas (null si no tiene membresías).
            ?? $this->memberships()->max('fecha_vencimiento');

        // Guarda la fecha (update no escribe si el valor es el mismo).
        $this->update(['fecha_vencimiento' => $fechaVencimiento]);
    }
}
