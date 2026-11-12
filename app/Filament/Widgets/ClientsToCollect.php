<?php

namespace App\Filament\Widgets;

use App\Enums\ClientStatusEnum;
use App\Filament\Resources\Clients\Actions\RegisterPaymentAction;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista "Para cobrar" del inicio: clientes con la cuota vencida o por vencer, con el botón
 * de cobro en cada fila.
 *
 * Solo lee datos que ya existen; el cobro usa la misma acción que el resto del panel.
 */
class ClientsToCollect extends TableWidget
{
    // Debajo de los contadores.
    protected static ?int $sort = 2;

    // Ocupa todo el ancho del inicio.
    protected int|string|array $columnSpan = 'full';

    /**
     * Arma la tabla de clientes a los que hay que cobrarles o avisarles.
     *
     * Precondiciones:
     * - Existe la tabla clients; el vencimiento de cada cliente se calcula solo a partir de sus
     *   membresías (Client::syncFechaVencimiento()).
     *
     * Postcondiciones:
     * - Lista los clientes activos (no dados de baja) en estado Entrenando, En pausa o Activo
     *   temporal cuyo vencimiento ya pasó o cae dentro de los próximos 7 días, del más urgente
     *   (vencimiento más viejo) al menos urgente. No incluye Inactivos ni clientes sin vencimiento.
     * - Cada fila muestra cliente y DNI, estado, vencimiento con la aclaración ("Venció hace
     *   5 días", en amarillo si ya pasó) y el plan vigente, con el botón "Cobrar".
     * - Hacer clic en la fila abre la ficha del cliente.
     * - No modifica datos (salvo al confirmar un cobro).
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada.
     */
    public function table(Table $table): Table
    {
        // Devuelve la tabla recibida con consulta, columnas y acciones cargadas.
        return $table
            // Título de la lista.
            ->heading('Para cobrar')
            // Qué incluye.
            ->description('Cuotas vencidas y las que vencen en los próximos 7 días.')
            // Clientes a mostrar.
            ->query(
                Client::query()
                    // Solo los que siguen viniendo o deben (no los inactivos).
                    ->whereIn('estado', [ClientStatusEnum::Entrenando, ClientStatusEnum::EnPausa, ClientStatusEnum::ActivoTemporal])
                    // Con vencimiento cargado.
                    ->whereNotNull('fecha_vencimiento')
                    // Vencido, o que vence de acá a 7 días.
                    ->whereDate('fecha_vencimiento', '<=', today()->addDays(7)),
            )
            // Al hacer clic en una fila se abre la ficha del cliente.
            ->recordUrl(fn (Client $record): string => ClientResource::getUrl('view', ['record' => $record]))
            // Columnas de la tabla.
            ->columns([
                // Cliente, con el DNI debajo.
                TextColumn::make('apellido')
                    // Encabezado de la columna.
                    ->label('Cliente')
                    // Muestra "Apellido, Nombre".
                    ->formatStateUsing(fn (Client $record): string => "{$record->apellido}, {$record->nombre}")
                    // Debajo, el DNI para distinguir homónimos.
                    ->description(fn (Client $record): string => "DNI {$record->dni}")
                    // Busca por apellido, nombre o DNI.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        // Agrupa las tres condiciones para no romper los filtros de la consulta base.
                        fn (Builder $query): Builder => $query
                            ->where('apellido', 'like', "%{$search}%")
                            ->orWhere('nombre', 'like', "%{$search}%")
                            ->orWhere('dni', 'like', "%{$search}%"),
                    )),
                // Estado del cliente.
                TextColumn::make('estado')
                    // Encabezado de la columna.
                    ->label('Estado')
                    // Como etiqueta de color.
                    ->badge(),
                // Vencimiento de la cuota.
                TextColumn::make('fecha_vencimiento')
                    // Encabezado de la columna.
                    ->label('Vencimiento')
                    // Formato día/mes/año.
                    ->date('d/m/Y')
                    // Debajo, cuánto falta o hace cuánto venció.
                    ->description(fn (Client $record): ?string => ClientResource::getExpirationHint($record))
                    // Amarillo suave si ya venció.
                    ->color(fn (Client $record): ?string => $record->fecha_vencimiento?->isBefore(today()) ? 'warning' : null)
                    // Permite ordenar por vencimiento.
                    ->sortable(),
                // Plan de la membresía vigente.
                TextColumn::make('currentMembership.plan.nombre')
                    // Encabezado de la columna.
                    ->label('Plan')
                    // Texto cuando no tiene membresía vigente.
                    ->placeholder('—'),
            ])
            // Primero los más urgentes (vencimiento más viejo).
            ->defaultSort('fecha_vencimiento')
            // Acciones de cada fila.
            ->recordActions([
                // Cobro rápido, igual que en la tabla de clientes.
                RegisterPaymentAction::make()
                    // Texto corto para la fila.
                    ->label('Cobrar'),
            ])
            // Opciones de filas por página.
            ->paginated([5, 10, 25])
            // Arranca mostrando 10.
            ->defaultPaginationPageOption(10)
            // Título cuando no hay nadie en la lista.
            ->emptyStateHeading('Nadie para cobrar')
            // Aclaración cuando no hay nadie en la lista.
            ->emptyStateDescription('No hay cuotas vencidas ni que venzan en los próximos 7 días.');
    }
}
