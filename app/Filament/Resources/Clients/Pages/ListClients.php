<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Enums\ClientStatusEnum;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Pestañas arriba de la tabla para filtrar los clientes por estado con un clic.
     *
     * Precondiciones:
     * - Existe la tabla clients con la columna estado.
     *
     * Postcondiciones:
     * - Devuelve la pestaña "Todos" seguida de una por cada estado de ClientStatusEnum, en el
     *   orden del enum (Entrenando, En pausa, Activo temporal, Inactivo).
     * - Cada pestaña muestra cuántos clientes activos (no dados de baja) hay en ese estado, con
     *   el color del estado; "Todos" muestra el total.
     * - Elegir una pestaña filtra la tabla por ese estado; la búsqueda y los filtros siguen
     *   funcionando dentro de la pestaña.
     * - Los contadores salen de una sola consulta agrupada.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        // Cuenta los clientes por estado en una sola consulta: ["entrenando" => 12, "en_pausa" => 3, ...].
        $counts = Client::query()
            // Sin orden, para que la agrupación sea válida.
            ->reorder()
            // Agrupa por estado.
            ->groupBy('estado')
            // Trae "estado => cantidad" directo de la base, sin pasar por el cast del modelo.
            ->toBase()
            // Columnas: el estado y su cantidad.
            ->selectRaw('estado, count(*) as total')
            // Lo convierte en un mapa estado => cantidad.
            ->pluck('total', 'estado');

        // La primera pestaña muestra todos los clientes.
        $tabs = [
            // Sin filtro; el contador es la suma de todos los estados.
            'todos' => Tab::make('Todos')->badge((int) $counts->sum()),
        ];

        // Agrega una pestaña por cada estado posible.
        foreach (ClientStatusEnum::cases() as $status) {
            // La clave de la pestaña (va en la URL) es el valor del estado.
            $tabs[$status->value] = Tab::make($status->getLabel())
                // Cantidad de clientes en ese estado (0 si no hay ninguno).
                ->badge((int) ($counts[$status->value] ?? 0))
                // Mismo color que el badge del estado en la tabla.
                ->badgeColor($status->getColor())
                // Al elegirla, filtra la tabla por ese estado.
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('estado', $status));
        }

        // Devuelve las pestañas en orden.
        return $tabs;
    }
}
