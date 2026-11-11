<?php

namespace App\Models;

use App\Enums\MembershipStatusEnum;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Membresía: el contrato que une a un cliente con un plan durante un período.
 *
 * Controla el acceso físico al gimnasio (estado) y las clases que le quedan
 * (creditos_disponibles), independientemente de si el cliente pagó.
 */
#[Fillable([
    'client_id',
    'plan_id',
    'fecha_inicio',
    'fecha_vencimiento',
    'estado',
    'creditos_disponibles',
])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /**
     * Registra los eventos del modelo: al crear una membresía sin créditos, copia los del plan.
     *
     * Precondiciones:
     * - Al crear la membresía, plan_id apunta a un plan existente (aunque esté dado de baja).
     * - Los eventos del modelo están activos. DatabaseSeeder usa WithoutModelEvents, así que
     *   un seeder de membresías debe cargar creditos_disponibles a mano.
     *
     * Postcondiciones:
     * - Toda membresía creada sin creditos_disponibles queda con los creditos_otorgados de su plan.
     * - Si se indicó creditos_disponibles al crearla, se respeta ese valor.
     * - Editar una membresía existente no vuelve a copiar los créditos.
     * - Si plan_id no existe, la creación falla con ModelNotFoundException y no se guarda nada.
     */
    protected static function booted(): void
    {
        // Se ejecuta justo antes de insertar una membresía nueva en la base.
        static::creating(function (Membership $membership): void {
            // Si ya trae créditos cargados, no los pisa.
            if ($membership->creditos_disponibles !== null) {
                // Sale sin cambiar nada.
                return;
            }

            // Busca el plan (incluso si fue dado de baja) y falla si no existe.
            $plan = Plan::withTrashed()->findOrFail($membership->plan_id);
            // Copia los créditos que otorga el plan como créditos iniciales de la membresía.
            $membership->creditos_disponibles = $plan->creditos_otorgados;
        });
    }

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - fecha_inicio y fecha_vencimiento guardan fechas (Y-m-d).
     * - estado guarda uno de los valores de MembershipStatusEnum
     *   (entrenando, en_pausa, activo_flex, vencida).
     *
     * Postcondiciones:
     * - Las fechas se leen como objetos Carbon.
     * - $membership->estado devuelve un caso de MembershipStatusEnum, y al asignarle un caso
     *   se guarda su valor de texto.
     * - creditos_disponibles se lee como int.
     * - Si la base tiene un estado que no existe en el enum, leerlo lanza un ValueError.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // Fecha de inicio como fecha (Carbon).
            'fecha_inicio' => 'date',
            // Fecha de vencimiento como fecha (Carbon).
            'fecha_vencimiento' => 'date',
            // Estado como el enum MembershipStatusEnum en lugar de texto suelto.
            'estado' => MembershipStatusEnum::class,
            // Créditos restantes como número entero.
            'creditos_disponibles' => 'integer',
        ];
    }

    /**
     * Filtra las membresías a las que se les puede imputar un pago (activas o pausadas).
     *
     * Precondiciones:
     * - Se usa como Membership::query()->acceptingPayments() o $client->memberships()->acceptingPayments().
     *
     * Postcondiciones:
     * - La consulta solo trae membresías cuyo estado cumple MembershipStatusEnum::acceptsPayments()
     *   (hoy: todas menos las vencidas).
     * - No ejecuta la consulta ni modifica datos.
     *
     * @param  Builder<Membership>  $query  Consulta a la que se agrega el filtro.
     */
    #[Scope]
    protected function acceptingPayments(Builder $query): void
    {
        // Se queda con los estados que admiten pagos; la regla vive en el enum para no repetirla.
        $query->whereIn('estado', MembershipStatusEnum::payableCases());
    }

    /**
     * Cliente dueño de la membresía.
     *
     * Precondiciones:
     * - client_id apunta a un cliente existente (la clave foránea lo garantiza).
     *
     * Postcondiciones:
     * - Devuelve la relación "la membresía pertenece a un cliente", sin ejecutar la consulta.
     * - Incluye clientes dados de baja: $membership->client nunca es null por una baja lógica.
     * - No modifica datos.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        // Une memberships.client_id con clients.id.
        return $this->belongsTo(Client::class)
            // Incluye clientes dados de baja para no perder el historial.
            ->withTrashed();
    }

    /**
     * Plan contratado en la membresía.
     *
     * Precondiciones:
     * - plan_id apunta a un plan existente (la clave foránea lo garantiza).
     *
     * Postcondiciones:
     * - Devuelve la relación "la membresía pertenece a un plan", sin ejecutar la consulta.
     * - Incluye planes dados de baja: el historial sigue mostrando qué plan tenía.
     * - No modifica datos.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        // Une memberships.plan_id con plans.id.
        return $this->belongsTo(Plan::class)
            // Incluye planes dados de baja para no perder el historial.
            ->withTrashed();
    }

    /**
     * Pagos imputados a esta membresía.
     *
     * Precondiciones:
     * - Existe la tabla payments con la columna membership_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "una membresía tiene muchos pagos", sin ejecutar la consulta.
     * - $membership->payments devuelve una colección (vacía si todavía no se pagó).
     * - No modifica datos.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        // Busca en payments las filas cuyo membership_id es el id de esta membresía.
        return $this->hasMany(Payment::class);
    }

    /**
     * Da días de gracia: habilita la membresía por excepción (Activo flex) y corre el vencimiento.
     *
     * Precondiciones:
     * - La membresía existe en la base y no está vencida (MembershipStatusEnum::Vencida).
     * - $dias es un entero entre 1 y 60.
     *
     * Postcondiciones:
     * - estado = ActivoFlex.
     * - fecha_vencimiento = la mayor entre la fecha de vencimiento actual y hoy, más $dias días.
     *   Si ya estaba vencida por fecha, los días se cuentan desde hoy, para que la gracia no
     *   termine en el pasado.
     * - Los cambios quedan guardados en la base y en esta instancia.
     * - Si alguna precondición no se cumple, lanza la excepción y no guarda nada.
     *
     * @param  int  $dias  Cantidad de días de gracia.
     *
     * @throws InvalidArgumentException Si la membresía está vencida o $dias está fuera de rango.
     */
    public function grantGraceDays(int $dias): void
    {
        // Una membresía cerrada no se reabre con gracia: se le crea una nueva al cliente.
        if ($this->estado === MembershipStatusEnum::Vencida) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('La membresía está vencida; creale una nueva.');
        }

        // Limita la gracia a un rango razonable.
        if ($dias < 1 || $dias > 60) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('Los días de gracia deben estar entre 1 y 60.');
        }

        // Toma como base el vencimiento actual, o hoy si ese vencimiento ya pasó.
        $base = $this->fecha_vencimiento->max(today());

        // Guarda el nuevo estado y el nuevo vencimiento en una sola operación.
        $this->update([
            // Habilitada por excepción del dueño.
            'estado' => MembershipStatusEnum::ActivoFlex,
            // Corre el vencimiento los días indicados.
            'fecha_vencimiento' => $base->copy()->addDays($dias),
        ]);
    }
}
