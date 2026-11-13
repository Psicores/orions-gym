<?php

namespace App\Models;

use App\Enums\ClientStatusEnum;
use App\Enums\MembershipStatusEnum;
use Carbon\CarbonInterface;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
     * Registra los eventos del modelo: al crear una membresía sin créditos copia los del plan, y
     * cada vez que una membresía se guarda o se borra recalcula el vencimiento de su cliente.
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
     * - Después de crear, editar o borrar una membresía (también al dar cortesía o renovar), el
     *   cliente queda con fecha_vencimiento recalculada (ver Client::syncFechaVencimiento()).
     * - Los cambios masivos por consulta (como el comando de vencimientos, que solo cambia el
     *   estado a En pausa) no disparan estos eventos; no hace falta porque no cambian fechas
     *   ni cierran membresías.
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

        // Después de guardar (crear o editar) una membresía, actualiza el vencimiento del cliente.
        static::saved(function (Membership $membership): void {
            // Recalcula a partir de todas sus membresías.
            $membership->client->syncFechaVencimiento();
        });

        // Después de borrar una membresía, actualiza el vencimiento del cliente sin ella.
        static::deleted(function (Membership $membership): void {
            // Recalcula a partir de las membresías que le quedan.
            $membership->client->syncFechaVencimiento();
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
     * Asistencias registradas con esta membresía (cada una le descontó un crédito).
     *
     * Precondiciones:
     * - Existe la tabla attendances con la columna membership_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "una membresía tiene muchas asistencias", sin ejecutar la consulta.
     * - No modifica datos.
     *
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        // Busca en attendances las filas cuyo membership_id es el id de esta membresía.
        return $this->hasMany(Attendance::class);
    }

    /**
     * Filtra las membresías que terminaron su ciclo y esperan la renovación.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - La consulta queda limitada a las membresías no vencidas que están En pausa o Activo flex,
     *   o cuya fecha_vencimiento es anterior a hoy. Es la misma regla de hasCompletedCycle().
     * - No ejecuta la consulta ni modifica datos.
     *
     * @param  Builder<Membership>  $query  Consulta de membresías.
     */
    #[Scope]
    protected function completedCycle(Builder $query): void
    {
        // Nunca las cerradas: a esas se les crea una membresía nueva.
        $query->where('estado', '!=', MembershipStatusEnum::Vencida)
            // Y además, alguna de las señales de ciclo terminado.
            ->where(fn (Builder $query): Builder => $query
                // Pausada por el cierre de ciclo o habilitada por cortesía.
                ->whereIn('estado', [MembershipStatusEnum::EnPausa, MembershipStatusEnum::ActivoFlex])
                // O con la fecha ya pasada (todavía no corrió el cierre de la madrugada).
                ->orWhereDate('fecha_vencimiento', '<', today()));
    }

    /**
     * Indica si la membresía terminó su ciclo y espera la renovación.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve false si está Vencida (cerrada).
     * - Devuelve true si está En pausa o Activo flex, o si su fecha_vencimiento es anterior a hoy.
     * - Devuelve false si está Entrenando y todavía no venció (el día del vencimiento inclusive).
     * - No modifica nada.
     *
     * @return bool true si le toca renovar.
     */
    public function hasCompletedCycle(): bool
    {
        // Una membresía cerrada no se renueva.
        if ($this->estado === MembershipStatusEnum::Vencida) {
            // No aplica.
            return false;
        }

        // Pausada, con cortesía o con la fecha pasada: terminó su ciclo.
        return in_array($this->estado, [MembershipStatusEnum::EnPausa, MembershipStatusEnum::ActivoFlex], true)
            || $this->fecha_vencimiento->isBefore(today());
    }

    /**
     * Inicia un ciclo (mes) nuevo en la misma membresía: es la renovación.
     *
     * La llama Client::registerPayment() cuando se cobra una membresía con el ciclo terminado,
     * y el comando memberships:renew-credits como red de seguridad.
     *
     * Precondiciones:
     * - La membresía existe en la base y no está vencida (MembershipStatusEnum::Vencida).
     * - $inicio es el primer día del ciclo nuevo (normalmente, el día del pago).
     *
     * Postcondiciones:
     * - fecha_inicio = $inicio y fecha_vencimiento = un mes después (si ese día no existe en el
     *   mes siguiente, el último día de ese mes: del 31/01 al 28/02).
     * - estado = Entrenando.
     * - creditos_disponibles = creditos_otorgados del plan. No arrastra el saldo anterior: ni los
     *   créditos que sobraron ni los que quedó debiendo.
     * - El cliente de la membresía queda en estado Entrenando y con fecha_vencimiento recalculada.
     * - Los cambios de la membresía y del cliente se guardan juntos (si uno falla, no queda
     *   ninguno) y quedan también en esta instancia.
     * - Si la membresía está vencida, lanza la excepción y no guarda nada.
     *
     * @param  CarbonInterface  $inicio  Primer día del ciclo nuevo.
     *
     * @throws InvalidArgumentException Si la membresía está vencida.
     */
    public function renewCycle(CarbonInterface $inicio): void
    {
        // Una membresía cerrada no se reabre: se le crea una nueva al cliente.
        if ($this->estado === MembershipStatusEnum::Vencida) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('La membresía está vencida; creale una nueva.');
        }

        // Guarda membresía y cliente en una transacción: o cambian los dos o ninguno.
        DB::transaction(function () use ($inicio): void {
            // Actualiza la membresía (al guardarse, recalcula el vencimiento del cliente).
            $this->update([
                // Primer día del mes nuevo.
                'fecha_inicio' => $inicio->toDateString(),
                // Un mes después, sin pasarse al mes siguiente si el día no existe.
                'fecha_vencimiento' => $inicio->copy()->addMonthNoOverflow()->toDateString(),
                // Vuelve al estado normal.
                'estado' => MembershipStatusEnum::Entrenando,
                // Recarga los créditos del plan (incluye planes dados de baja).
                'creditos_disponibles' => $this->plan->creditos_otorgados,
            ]);

            // El cliente queda al día.
            $this->client->update(['estado' => ClientStatusEnum::Entrenando]);
        });
    }

    /**
     * Da cortesía: habilita la membresía por confianza del dueño (Activo flex), sin fecha de fin.
     *
     * Precondiciones:
     * - La membresía existe en la base y no está vencida (MembershipStatusEnum::Vencida).
     *
     * Postcondiciones:
     * - estado = ActivoFlex: el socio puede escanear y entrar aunque su fecha haya terminado.
     * - No cambia fecha_vencimiento ni los créditos.
     * - El cliente de la membresía queda en estado Activo temporal.
     * - La cortesía no termina sola (el cierre de ciclo de la madrugada no toca Activo flex):
     *   termina cuando el socio renueva (ver renewCycle()) o cuando el dueño le cambia el estado.
     * - Los cambios de la membresía y del cliente se guardan juntos (si uno falla, no queda ninguno)
     *   y quedan también en esta instancia.
     * - Si la membresía está vencida, lanza la excepción y no guarda nada.
     *
     * @throws InvalidArgumentException Si la membresía está vencida.
     */
    public function grantCourtesy(): void
    {
        // Una membresía cerrada no se reabre con cortesía: se le crea una nueva al cliente.
        if ($this->estado === MembershipStatusEnum::Vencida) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('La membresía está vencida; creale una nueva.');
        }

        // Guarda membresía y cliente en una transacción: o cambian los dos o ninguno.
        DB::transaction(function (): void {
            // Habilitada por cortesía del dueño.
            $this->update(['estado' => MembershipStatusEnum::ActivoFlex]);

            // El cliente pasa a Activo temporal: puede entrar aunque todavía no renovó.
            $this->client->update(['estado' => ClientStatusEnum::ActivoTemporal]);
        });
    }
}
