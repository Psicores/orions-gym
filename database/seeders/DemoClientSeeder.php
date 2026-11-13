<?php

namespace Database\Seeders;

use App\Enums\ClientStatusEnum;
use App\Enums\MembershipStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Clientes de ejemplo para desarrollo: cubren todos los estados del cliente y de la membresía,
 * vencimientos pasados, de hoy y próximos, pagos con ambos medios y un cliente dado de baja.
 *
 * Lo carga DatabaseSeeder junto con todo lo demás (salvo con SEED_DEMO_DATA=false). A mano:
 * php artisan db:seed --class=DemoClientSeeder
 */
class DemoClientSeeder extends Seeder
{
    /**
     * DNI de los clientes de ejemplo. Los usa "php artisan demo:remove" para borrarlos.
     *
     * @var list<string>
     */
    public const array DNIS = [
        '30100001', '30100002', '30100003', '30100004', '30100005', '30100006',
        '30100007', '30100008', '30100009', '30100010', '30100011',
    ];

    /**
     * Email de ejemplo que le corresponde a un DNI, en un dominio reservado para pruebas.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve demo<DNI>@example.com. No consulta la base.
     *
     * @param  string  $dni  DNI del cliente de ejemplo.
     * @return string El email de ejemplo.
     */
    public static function emailFor(string $dni): string
    {
        // Mismo formato para todos los clientes de ejemplo.
        return "demo{$dni}@example.com";
    }

    /**
     * Casos de ejemplo. Los días son relativos a hoy (negativo = pasado), así los estados y los
     * avisos de vencimiento tienen sentido sin importar cuándo se cargue.
     *
     * Cada membresía: plan, inicio y vencimiento (días desde hoy), estado, créditos ya usados y
     * pagos como [hace cuántos días, medio].
     *
     * @return list<array{
     *     dni: string, nombre: string, apellido: string, nacimiento: string, estado: ClientStatusEnum,
     *     observaciones?: string, baja?: bool,
     *     membresias: list<array{plan: string, inicio: int, vence: int, estado: MembershipStatusEnum, usados?: int, pagos?: list<array{0: int, 1: PaymentMethodEnum}>}>
     * }>
     */
    private function cases(): array
    {
        // Atajos para que la lista se lea de corrido.
        $efectivo = PaymentMethodEnum::Efectivo;
        $transferencia = PaymentMethodEnum::Transferencia;

        return [
            // Al día, a mitad de mes.
            ['dni' => '30100001', 'nombre' => 'Lucía', 'apellido' => 'Fernández', 'nacimiento' => '1995-03-14', 'estado' => ClientStatusEnum::Entrenando, 'membresias' => [
                ['plan' => '3 días', 'inicio' => -10, 'vence' => 20, 'estado' => MembershipStatusEnum::Entrenando, 'usados' => 4, 'pagos' => [[10, $efectivo]]],
            ]],
            // Al día con pase libre, vence en 3 días.
            ['dni' => '30100002', 'nombre' => 'Martín', 'apellido' => 'Gómez', 'nacimiento' => '1988-07-02', 'estado' => ClientStatusEnum::Entrenando, 'membresias' => [
                ['plan' => 'Pase libre', 'inicio' => -27, 'vence' => 3, 'estado' => MembershipStatusEnum::Entrenando, 'pagos' => [[27, $transferencia]]],
            ]],
            // Al día, vence hoy y le quedan pocos créditos.
            ['dni' => '30100003', 'nombre' => 'Sofía', 'apellido' => 'Rodríguez', 'nacimiento' => '2001-11-23', 'estado' => ClientStatusEnum::Entrenando, 'membresias' => [
                ['plan' => '2 días', 'inicio' => -30, 'vence' => 0, 'estado' => MembershipStatusEnum::Entrenando, 'usados' => 14, 'pagos' => [[30, $efectivo]]],
            ]],
            // Al día, vence mañana.
            ['dni' => '30100004', 'nombre' => 'Julieta', 'apellido' => 'Torres', 'nacimiento' => '1998-01-30', 'estado' => ClientStatusEnum::Entrenando, 'membresias' => [
                ['plan' => '3 días', 'inicio' => -29, 'vence' => 1, 'estado' => MembershipStatusEnum::Entrenando, 'usados' => 10, 'pagos' => [[29, $efectivo]]],
            ]],
            // Debe: venció hace 5 días, pagó el mes anterior. Con una observación de salud.
            ['dni' => '30100005', 'nombre' => 'Diego', 'apellido' => 'López', 'nacimiento' => '1990-05-09', 'estado' => ClientStatusEnum::EnPausa, 'observaciones' => 'Dolor lumbar: evitar peso muerto.', 'membresias' => [
                ['plan' => '3 días', 'inicio' => -35, 'vence' => -5, 'estado' => MembershipStatusEnum::EnPausa, 'usados' => 12, 'pagos' => [[35, $efectivo]]],
            ]],
            // Debe: venció hace 20 días y nunca pagó.
            ['dni' => '30100006', 'nombre' => 'Valentina', 'apellido' => 'Díaz', 'nacimiento' => '1993-09-17', 'estado' => ClientStatusEnum::EnPausa, 'membresias' => [
                ['plan' => '2 días', 'inicio' => -50, 'vence' => -20, 'estado' => MembershipStatusEnum::EnPausa, 'usados' => 9],
            ]],
            // Con cortesía: una membresía vieja paga y la actual en Activo flex sin pagar.
            ['dni' => '30100007', 'nombre' => 'Nicolás', 'apellido' => 'Martínez', 'nacimiento' => '1985-12-05', 'estado' => ClientStatusEnum::ActivoTemporal, 'membresias' => [
                ['plan' => '3 días', 'inicio' => -62, 'vence' => -32, 'estado' => MembershipStatusEnum::Vencida, 'usados' => 12, 'pagos' => [[62, $transferencia]]],
                ['plan' => '3 días', 'inicio' => -32, 'vence' => 4, 'estado' => MembershipStatusEnum::ActivoFlex, 'usados' => 11],
            ]],
            // Dejó de venir: su única membresía está vencida hace 3 meses.
            ['dni' => '30100008', 'nombre' => 'Camila', 'apellido' => 'Pérez', 'nacimiento' => '1999-04-21', 'estado' => ClientStatusEnum::Inactivo, 'membresias' => [
                ['plan' => 'Pase libre', 'inicio' => -120, 'vence' => -90, 'estado' => MembershipStatusEnum::Vencida, 'pagos' => [[120, $efectivo]]],
            ]],
            // Recién inscripto: todavía sin membresía.
            ['dni' => '30100009', 'nombre' => 'Joaquín', 'apellido' => 'Sánchez', 'nacimiento' => '2004-08-12', 'estado' => ClientStatusEnum::Inactivo, 'membresias' => []],
            // Con historial: cambió de plan, la anterior vencida y la actual al día, dos pagos.
            ['dni' => '30100010', 'nombre' => 'Agustina', 'apellido' => 'Romero', 'nacimiento' => '1992-02-28', 'estado' => ClientStatusEnum::Entrenando, 'membresias' => [
                ['plan' => '3 días', 'inicio' => -65, 'vence' => -35, 'estado' => MembershipStatusEnum::Vencida, 'usados' => 12, 'pagos' => [[65, $efectivo]]],
                ['plan' => '2 días', 'inicio' => -5, 'vence' => 25, 'estado' => MembershipStatusEnum::Entrenando, 'usados' => 2, 'pagos' => [[5, $transferencia]]],
            ]],
            // Dado de baja (se ve con el filtro de eliminados), con historial viejo.
            ['dni' => '30100011', 'nombre' => 'Tomás', 'apellido' => 'Álvarez', 'nacimiento' => '1980-10-03', 'estado' => ClientStatusEnum::Inactivo, 'baja' => true, 'membresias' => [
                ['plan' => '3 días', 'inicio' => -200, 'vence' => -170, 'estado' => MembershipStatusEnum::Vencida, 'usados' => 12, 'pagos' => [[200, $efectivo]]],
            ]],
        ];
    }

    /**
     * Carga los clientes de ejemplo con sus membresías y pagos.
     *
     * Precondiciones:
     * - Las migraciones están corridas.
     * - Existe al menos un usuario (los pagos quedan registrados a nombre del primero).
     *
     * Postcondiciones:
     * - Existen los planes base (llama a PlanSeeder).
     * - Existe un cliente por cada caso de cases(), identificado por su DNI, con sus membresías,
     *   sus pagos (por el precio del plan) y su fecha de vencimiento calculada.
     * - El cliente marcado como baja queda dado de baja (baja lógica).
     * - Un cliente cuyo DNI ya existía no se toca, así que correrlo varias veces no duplica nada
     *   ni pisa cambios hechos a mano.
     * - Funciona con los eventos de modelos apagados (DatabaseSeeder usa WithoutModelEvents):
     *   los créditos y el vencimiento se cargan de forma explícita.
     *
     * @throws RuntimeException Si no hay ningún usuario para registrar los pagos.
     */
    public function run(): void
    {
        // Asegura que existan los planes que usan los casos.
        $this->call(PlanSeeder::class);

        // Usuario a cuyo nombre quedan los pagos de ejemplo: el primero creado (el administrador).
        $user = User::query()->oldest('id')->first();

        // Sin usuarios no se pueden registrar pagos.
        if ($user === null) {
            // Avisa cómo resolverlo.
            throw new RuntimeException('No hay usuarios: corré "php artisan db:seed", que crea las cuentas antes que los clientes.');
        }

        // Planes base indexados por nombre (incluye dados de baja, por si el dueño archivó alguno).
        $plans = Plan::withTrashed()->get()->keyBy('nombre');

        // Recorre los casos de ejemplo.
        foreach ($this->cases() as $case) {
            // Busca el cliente por DNI (incluso dado de baja) o lo crea con los datos del caso.
            $client = Client::withTrashed()->firstOrCreate(
                // Criterio de búsqueda: el DNI.
                ['dni' => $case['dni']],
                [
                    // Nombre de pila.
                    'nombre' => $case['nombre'],
                    // Apellido.
                    'apellido' => $case['apellido'],
                    // Fecha de nacimiento.
                    'fecha_nacimiento' => $case['nacimiento'],
                    // Celular de ejemplo, distinto por cliente (termina como el DNI).
                    'cel' => '11'.$case['dni'],
                    // Email de ejemplo en un dominio reservado para pruebas.
                    'email' => self::emailFor($case['dni']),
                    // Estado del cliente según el caso.
                    'estado' => $case['estado'],
                    // Observación de salud, si el caso la trae.
                    'observaciones_dolencias' => $case['observaciones'] ?? null,
                ],
            );

            // Si ya existía, no lo toca ni le agrega membresías de nuevo.
            if (! $client->wasRecentlyCreated) {
                // Pasa al siguiente caso.
                continue;
            }

            // Crea las membresías del caso.
            foreach ($case['membresias'] as $item) {
                // Plan de la membresía.
                $plan = $plans[$item['plan']];

                // Crea la membresía con fechas relativas a hoy.
                $membership = Membership::create([
                    // Cliente dueño.
                    'client_id' => $client->id,
                    // Plan contratado.
                    'plan_id' => $plan->id,
                    // Inicio: hoy más (o menos) los días del caso.
                    'fecha_inicio' => today()->addDays($item['inicio']),
                    // Vencimiento: hoy más (o menos) los días del caso.
                    'fecha_vencimiento' => today()->addDays($item['vence']),
                    // Estado de acceso.
                    'estado' => $item['estado'],
                    // Créditos que le quedan: los del plan menos los usados (nunca negativo).
                    'creditos_disponibles' => max(0, $plan->creditos_otorgados - ($item['usados'] ?? 0)),
                ]);

                // Crea los pagos de esa membresía.
                foreach ($item['pagos'] ?? [] as [$daysAgo, $method]) {
                    // Pago por el precio del plan, hecho hace $daysAgo días a media mañana.
                    Payment::create([
                        // Cliente que pagó.
                        'client_id' => $client->id,
                        // Membresía a la que se imputa.
                        'membership_id' => $membership->id,
                        // Monto: el precio del plan.
                        'monto' => $plan->precio,
                        // Medio de pago del caso.
                        'metodo_pago' => $method,
                        // Fecha y hora del cobro.
                        'fecha_pago' => today()->subDays($daysAgo)->setTime(10, 30),
                        // Quién lo cobró.
                        'registrado_por_user_id' => $user->id,
                    ]);
                }
            }

            // Calcula el vencimiento del cliente a partir de sus membresías (los eventos pueden estar apagados).
            $client->syncFechaVencimiento();

            // Si el caso es una baja, da de baja al cliente (baja lógica).
            if ($case['baja'] ?? false) {
                // Marca deleted_at; el historial queda intacto.
                $client->delete();
            }
        }
    }
}
