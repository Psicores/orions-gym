<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('memberships:renew-credits')]
#[Description('Inicia el mes nuevo (créditos del plan, Entrenando) en las membresías que se pagaron después de terminar su ciclo')]
class RenewMonthlyCredits extends Command
{
    /**
     * Recarga mensual: asegura que toda membresía pagada después de terminar su ciclo tenga
     * iniciado el mes nuevo, con los créditos del plan.
     *
     * Normalmente el mes nuevo arranca en el momento del cobro (ver Client::registerPayment()).
     * Este comando es la red de seguridad: encuentra las que quedaron pagas pero sin renovar
     * (por ejemplo, pagos cargados antes de que existiera la renovación automática) y las pone al día.
     *
     * Corre todas las madrugadas desde el scheduler (routes/console.php) y también se puede
     * correr a mano con "php artisan memberships:renew-credits".
     *
     * Precondiciones:
     * - Existen las tablas memberships y payments.
     * - La app está en hora argentina (config/app.php).
     *
     * Postcondiciones:
     * - Toda membresía con el ciclo terminado (ver Membership::completedCycle()) que tiene un
     *   pago hecho después de su fecha de vencimiento inicia un ciclo nuevo desde el día de ese
     *   pago: Entrenando, un mes de vigencia y creditos_disponibles = los del plan (ver
     *   Membership::renewCycle()). Vale para todos los planes, limitados o libres.
     * - No toca las membresías sin pagos posteriores a su vencimiento, ni las vencidas.
     * - Correrlo otra vez no cambia nada más: al renovar, el vencimiento queda posterior al pago.
     * - Muestra cuántas membresías renovó y devuelve el código de éxito.
     *
     * @return int Command::SUCCESS.
     */
    public function handle(): int
    {
        // Cantidad de membresías renovadas.
        $renewed = 0;

        // Membresías con el ciclo terminado y un pago posterior a su vencimiento, de a tandas.
        Membership::query()
            // Solo las que terminaron su ciclo y no están cerradas.
            ->completedCycle()
            // Con algún pago hecho después del día de vencimiento (un pago de ese mismo día no cuenta).
            ->whereHas('payments', fn (Builder $query): Builder => $query
                ->whereRaw('date(payments.fecha_pago) > date(memberships.fecha_vencimiento)'))
            // Recorre por id para no saltear filas mientras se van modificando.
            ->chunkById(100, function (Collection $memberships) use (&$renewed): void {
                // Renueva cada una.
                foreach ($memberships as $membership) {
                    /** @var Payment $payment Último pago de la membresía: el que abona el mes nuevo. */
                    $payment = $membership->payments()->latest('fecha_pago')->first();

                    // El mes nuevo arranca el día en que pagó.
                    $membership->renewCycle($payment->fecha_pago);
                    // Suma una renovada.
                    $renewed++;
                }
            });

        // Informa el resultado (queda en el log del scheduler o en la terminal).
        $this->info("Membresías renovadas: {$renewed}.");

        // Termina con éxito.
        return self::SUCCESS;
    }
}
