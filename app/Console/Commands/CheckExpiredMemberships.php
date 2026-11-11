<?php

namespace App\Console\Commands;

use App\Enums\ClientStatusEnum;
use App\Enums\MembershipStatusEnum;
use App\Models\Client;
use App\Models\Membership;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('memberships:check-expired')]
#[Description('Pasa a "En pausa" las membresías vencidas que habilitan el acceso, y a sus clientes si se quedan sin acceso')]
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
     * - La app está en hora argentina (config/app.php), así "hoy" es el día del gimnasio.
     *
     * Postcondiciones:
     * - Toda membresía con fecha_vencimiento anterior a hoy y estado Entrenando o Activo flex
     *   (los que habilitan la entrada) queda en En pausa. Activo flex se incluye porque los
     *   días de gracia también terminan.
     * - Las membresías que vencen hoy siguen habilitadas hasta mañana.
     * - El cliente de cada membresía pausada queda En pausa (debe abonar), salvo que todavía
     *   tenga otra membresía que lo deje entrar (por ejemplo, ya renovó con una nueva).
     * - No toca membresías en pausa o vencidas, ni clientes sin membresías pausadas hoy.
     * - Membresías y clientes se actualizan en una transacción: o cambia todo o nada.
     * - Correrlo varias veces el mismo día no cambia nada más.
     * - Muestra cuántas membresías y cuántos clientes pausó y devuelve el código de éxito.
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

        // Hace todos los cambios en una transacción y devuelve [membresías pausadas, clientes pausados].
        [$pausedMemberships, $pausedClients] = DB::transaction(function () use ($accessStatuses): array {
            // Consulta de las membresías vencidas que todavía dejan entrar.
            $expired = Membership::query()
                // Vencimiento anterior a hoy (la que vence hoy todavía vale).
                ->whereDate('fecha_vencimiento', '<', today())
                // Solo las que siguen habilitando la entrada.
                ->whereIn('estado', $accessStatuses);

            // Anota de qué clientes son, antes de cambiarles el estado.
            $clientIds = (clone $expired)->distinct()->pluck('client_id');

            // Pasa esas membresías a En pausa en una sola consulta; devuelve cuántas cambió.
            $pausedMemberships = $expired->update(['estado' => MembershipStatusEnum::EnPausa]);

            // Pasa a En pausa a esos clientes, salvo los que todavía tienen acceso por otra membresía.
            $pausedClients = Client::query()
                // Solo los clientes de las membresías recién pausadas.
                ->whereIn('id', $clientIds)
                // Sin ninguna otra membresía que los deje entrar.
                ->whereDoesntHave('memberships', fn (Builder $query): Builder => $query->whereIn('estado', $accessStatuses))
                // Que no estén ya en pausa (para contar solo los que cambian).
                ->where('estado', '!=', ClientStatusEnum::EnPausa)
                // Los pasa a En pausa (debe abonar); devuelve cuántos cambió.
                ->update(['estado' => ClientStatusEnum::EnPausa]);

            // Devuelve ambos conteos.
            return [$pausedMemberships, $pausedClients];
        });

        // Informa el resultado (queda en el log del scheduler o en la terminal).
        $this->info("Membresías pasadas a En pausa: {$pausedMemberships}. Clientes pasados a En pausa: {$pausedClients}.");

        // Termina con éxito.
        return self::SUCCESS;
    }
}
