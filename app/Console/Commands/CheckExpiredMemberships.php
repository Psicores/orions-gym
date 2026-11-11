<?php

namespace App\Console\Commands;

use App\Enums\MembershipStatusEnum;
use App\Models\Membership;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('memberships:check-expired')]
#[Description('Pasa a "En pausa" las membresías que habilitan el acceso y cuyo vencimiento ya pasó')]
class CheckExpiredMemberships extends Command
{
    /**
     * Revisa los vencimientos del día: pausa las membresías que siguen habilitando la entrada
     * al gimnasio aunque su fecha de vencimiento ya pasó.
     *
     * Corre todos los días desde el scheduler (routes/console.php) y también se puede correr
     * a mano con "php artisan memberships:check-expired".
     *
     * Precondiciones:
     * - Existe la tabla memberships.
     * - Corre después de la medianoche argentina, así "hoy" (today(), en la zona de la app)
     *   es el mismo día que en el gimnasio.
     *
     * Postcondiciones:
     * - Toda membresía con fecha_vencimiento anterior a hoy y estado Entrenando o Activo flex
     *   (los que habilitan la entrada) queda en En pausa. Activo flex se incluye porque los
     *   días de gracia también terminan.
     * - Las membresías que vencen hoy siguen habilitadas hasta mañana.
     * - No toca membresías en pausa o vencidas, ni el estado del cliente.
     * - Correrlo varias veces el mismo día no cambia nada más.
     * - Muestra cuántas membresías pausó y devuelve el código de éxito.
     *
     * @return int Command::SUCCESS.
     */
    public function handle(): int
    {
        // Estados que habilitan la entrada (Entrenando y Activo flex), según la regla del enum.
        $accessStatuses = array_filter(
            // Todos los estados posibles.
            MembershipStatusEnum::cases(),
            // Se queda con los que dejan entrar.
            fn (MembershipStatusEnum $status): bool => $status->allowsGymAccess(),
        );

        // Actualiza en una sola consulta todas las membresías vencidas que todavía dejan entrar.
        $paused = Membership::query()
            // Vencimiento anterior a hoy (la que vence hoy todavía vale).
            ->whereDate('fecha_vencimiento', '<', today())
            // Solo las que siguen habilitando la entrada.
            ->whereIn('estado', $accessStatuses)
            // Las pasa a En pausa; devuelve cuántas filas cambió.
            ->update(['estado' => MembershipStatusEnum::EnPausa]);

        // Informa el resultado (queda en el log del scheduler o en la terminal).
        $this->info("Membresías pasadas a En pausa: {$paused}");

        // Termina con éxito.
        return self::SUCCESS;
    }
}
