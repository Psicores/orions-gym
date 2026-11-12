<?php

namespace App\Filament\Widgets;

use App\Enums\ClientStatusEnum;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

/**
 * Contadores del inicio del panel: cómo está el gimnasio hoy, de un vistazo.
 *
 * Solo lee datos que ya existen (clientes); no guarda ni calcula nada nuevo.
 */
class ReceptionStats extends StatsOverviewWidget
{
    // Primero en el inicio.
    protected static ?int $sort = 1;

    // Se actualiza solo una vez por minuto (el valor por defecto de Filament es cada 5 segundos).
    protected ?string $pollingInterval = '60s';

    /**
     * Arma los tres contadores del inicio.
     *
     * Precondiciones:
     * - Existe la tabla clients.
     * - La app está en hora argentina, así "hoy" es el del gimnasio.
     *
     * Postcondiciones:
     * - "Entrenando": clientes activos (no dados de baja) con la cuota al día. Lleva a la tabla
     *   de clientes filtrada por ese estado.
     * - "Deben la cuota": clientes En pausa. Lleva a la tabla filtrada. En amarillo, nunca rojo.
     * - "Vencen en 7 días": clientes Entrenando o Activo temporal cuyo vencimiento cae entre hoy
     *   y dentro de 7 días.
     * - No modifica datos.
     *
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        // Cantidad de clientes por estado en una sola consulta: ["entrenando" => 5, ...].
        $byStatus = Client::query()
            // Agrupa por estado.
            ->groupBy('estado')
            // Trae el resultado crudo, sin pasar por el cast del modelo.
            ->toBase()
            // Columnas: el estado y su cantidad.
            ->selectRaw('estado, count(*) as total')
            // Lo convierte en un mapa estado => cantidad.
            ->pluck('total', 'estado');

        // Clientes que siguen entrando y cuya cuota vence entre hoy y dentro de 7 días.
        $expiringSoon = Client::query()
            // Solo los que hoy pueden entrar.
            ->whereIn('estado', [ClientStatusEnum::Entrenando, ClientStatusEnum::ActivoTemporal])
            // Vencimiento de hoy en adelante...
            ->whereDate('fecha_vencimiento', '>=', today())
            // ...y hasta dentro de 7 días inclusive (comparando solo la fecha, sin la hora).
            ->whereDate('fecha_vencimiento', '<=', today()->addDays(7))
            // Solo la cantidad.
            ->count();

        // Devuelve los contadores en el orden en que se muestran.
        return [
            // Clientes con la cuota al día.
            Stat::make('Entrenando', (int) ($byStatus[ClientStatusEnum::Entrenando->value] ?? 0))
                // Aclaración debajo del número.
                ->description('Con la cuota al día')
                // Ícono de la aclaración.
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                // Verde.
                ->color('success')
                // Al hacer clic, abre la tabla de clientes en esa pestaña.
                ->url(ClientResource::getUrl('index', ['tab' => ClientStatusEnum::Entrenando->value])),
            // Clientes que deben.
            Stat::make('Deben la cuota', (int) ($byStatus[ClientStatusEnum::EnPausa->value] ?? 0))
                // Aclaración debajo del número.
                ->description('En pausa hasta que abonen')
                // Ícono de la aclaración.
                ->descriptionIcon(Heroicon::OutlinedPauseCircle)
                // Amarillo suave: avisa sin alarmar.
                ->color('warning')
                // Al hacer clic, abre la tabla de clientes en esa pestaña.
                ->url(ClientResource::getUrl('index', ['tab' => ClientStatusEnum::EnPausa->value])),
            // Cuotas que vencen pronto.
            Stat::make('Vencen en 7 días', $expiringSoon)
                // Aclaración debajo del número.
                ->description('Están en la lista de abajo')
                // Ícono de la aclaración.
                ->descriptionIcon(Heroicon::OutlinedClock)
                // Celeste.
                ->color('info'),
        ];
    }

    /**
     * Vuelve a calcular los contadores cuando se registra un pago o cambia un cliente en la
     * misma pantalla (por ejemplo, al cobrar desde la lista "Para cobrar").
     *
     * Precondiciones:
     * - Alguna acción emitió "pago-registrado" o "cliente-actualizado".
     *
     * Postcondiciones:
     * - Livewire vuelve a dibujar el widget con los valores actuales.
     * - No modifica datos.
     */
    #[On('pago-registrado')]
    #[On('cliente-actualizado')]
    public function refreshStats(): void
    {
        // No hace falta código: atender el evento ya provoca que Livewire redibuje los contadores.
    }
}
