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

#[Signature('memberships:update-completed-cycles')]
#[Description('Pasa a "En pausa" las membresías Entrenando que ya terminaron su ciclo, y a sus clientes si se quedan sin acceso')]
class UpdateCompletedCycles extends Command
{
    /**
     * Cierra los ciclos terminados: pasa a En pausa las membresías que siguen Entrenando aunque
     * su fecha de vencimiento ya pasó.
     *
     * "En pausa" no marca al socio como deudor: solo indica que terminó su mes y le toca renovar.
     * El dueño los encuentra en la pestaña "En pausa" del listado de clientes.
     *
     * Corre todas las madrugadas desde el scheduler (routes/console.php) y también se puede
     * correr a mano con "php artisan memberships:update-completed-cycles".
     *
     * Precondiciones:
     * - Existe la tabla memberships.
     * - La app está en hora argentina (config/app.php), así "hoy" es el día del gimnasio.
     *
     * Postcondiciones:
     * - Toda membresía con fecha_vencimiento anterior a hoy y estado Entrenando queda En pausa.
     * - Las membresías que vencen hoy siguen Entrenando hasta mañana.
     * - Las membresías en Activo flex no se tocan: la cortesía del dueño (ver
     *   Membership::grantCourtesy()) vale hasta que el socio renueve, aunque la fecha haya pasado.
     * - El cliente de cada membresía pausada queda En pausa, salvo que todavía tenga otra
     *   membresía que lo deje entrar (por ejemplo, ya renovó con una nueva).
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
            // Consulta de las membresías que terminaron su ciclo y siguen Entrenando.
            $completed = Membership::query()
                // Vencimiento anterior a hoy (la que vence hoy todavía vale).
                ->whereDate('fecha_vencimiento', '<', today())
                // Solo las que siguen en el estado normal; las que tienen cortesía quedan como están.
                ->where('estado', MembershipStatusEnum::Entrenando);

            // Anota de qué clientes son, antes de cambiarles el estado.
            $clientIds = (clone $completed)->distinct()->pluck('client_id');

            // Pasa esas membresías a En pausa en una sola consulta; devuelve cuántas cambió.
            $pausedMemberships = $completed->update(['estado' => MembershipStatusEnum::EnPausa]);

            // Pasa a En pausa a esos clientes, salvo los que todavía tienen acceso por otra membresía.
            $pausedClients = Client::query()
                // Solo los clientes de las membresías recién pausadas.
                ->whereIn('id', $clientIds)
                // Sin ninguna otra membresía que los deje entrar.
                ->whereDoesntHave('memberships', fn (Builder $query): Builder => $query->whereIn('estado', $accessStatuses))
                // Que no estén ya en pausa (para contar solo los que cambian).
                ->where('estado', '!=', ClientStatusEnum::EnPausa)
                // Los pasa a En pausa (le toca renovar); devuelve cuántos cambió.
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
