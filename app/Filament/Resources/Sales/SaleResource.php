<?php

namespace App\Filament\Resources\Sales;

use App\Enums\PaymentMethodEnum;
use App\Enums\SaleStatusEnum;
use App\Exceptions\SaleException;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\Sale;
use App\Models\User;
use App\Services\Kiosk\SaleService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Historial de ventas del kiosco.
 *
 * Lo ve todo el personal: fecha, socio, productos, total, medios de pago, quién vendió y estado,
 * con filtros y el total cobrado al pie. Cada venta se abre en una ventana con el detalle. Solo los
 * administradores anulan (ver App\Policies\SalePolicy), con motivo: devuelve el stock y marca los
 * cobros como reintegrados.
 */
class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    // URL en castellano: /admin/kiosco/ventas.
    protected static ?string $slug = 'kiosco/ventas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    // Grupo "Kiosco" del menú.
    protected static string|UnitEnum|null $navigationGroup = ProductResource::NAVIGATION_GROUP;

    // Después de Vender.
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?string $modelLabel = 'venta';

    protected static ?string $pluralModelLabel = 'ventas';

    /**
     * Listado de ventas.
     *
     * Precondiciones:
     * - Existen las tablas sales, sale_items y sale_payments.
     *
     * Postcondiciones:
     * - Lista de la más nueva a la más vieja. Se filtra por fecha (desde/hasta), estado, medio de
     *   pago y quién vendió; se busca por número, nombre o DNI del socio.
     * - Al pie, el total cobrado de lo filtrado, sin contar las anuladas.
     * - Botón "Detalle" para todos y "Anular" solo para administradores en ventas vigentes.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            // Trae todo lo que muestra en la misma consulta.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['client', 'vendidoPor', 'items', 'payments']))
            ->columns([
                // Número de venta.
                TextColumn::make('id')
                    ->label('N.º')
                    ->formatStateUsing(fn (int $state): string => "#{$state}")
                    ->searchable(),
                // Cuándo.
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                // Socio o mostrador.
                TextColumn::make('client.apellido')
                    ->label('Socio')
                    ->formatStateUsing(fn (Sale $record): string => $record->client ? "{$record->client->apellido}, {$record->client->nombre}" : 'Mostrador')
                    ->placeholder('Mostrador')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('client', fn (Builder $query): Builder => $query
                        ->where('apellido', 'like', "%{$search}%")
                        ->orWhere('nombre', 'like', "%{$search}%")
                        ->orWhere('dni', 'like', "{$search}%"))),
                // Qué se vendió, resumido.
                TextColumn::make('items_resumen')
                    ->label('Productos')
                    ->state(fn (Sale $record): string => $record->items->map(fn ($item): string => "{$item->cantidad}× {$item->nombre}")->implode(', '))
                    ->limit(60)
                    ->wrap(),
                // Total cobrado.
                TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS', locale: 'es_AR')
                    // Anuladas tachadas en gris.
                    ->color(fn (Sale $record): ?string => $record->estado === SaleStatusEnum::Anulada ? 'gray' : null)
                    ->extraAttributes(fn (Sale $record): array => $record->estado === SaleStatusEnum::Anulada ? ['class' => 'orions-tachado'] : [])
                    ->sortable()
                    // Total de lo filtrado, sin anuladas.
                    ->summarize(Sum::make()
                        ->label('Cobrado')
                        ->money('ARS', locale: 'es_AR')
                        ->query(fn ($query) => $query->where('estado', '!=', SaleStatusEnum::Anulada->value))),
                // Medios de pago.
                TextColumn::make('medios')
                    ->label('Pago')
                    ->state(fn (Sale $record): array => $record->payments->pluck('metodo')->unique()->values()->all())
                    ->badge(),
                // Quién vendió.
                TextColumn::make('vendidoPor.name')
                    ->label('Vendió'),
                // Estado.
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
            ])
            // Lo más nuevo primero.
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Rango de fechas.
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['hasta'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                    // Etiquetas del filtro activo.
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators[] = 'Desde '.date('d/m/Y', strtotime($data['desde']));
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators[] = 'Hasta '.date('d/m/Y', strtotime($data['hasta']));
                        }

                        return $indicators;
                    }),
                // Estado.
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(SaleStatusEnum::class),
                // Medio de pago (ventas con al menos un cobro de ese medio).
                SelectFilter::make('metodo')
                    ->label('Medio de pago')
                    ->options(PaymentMethodEnum::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $metodo): Builder => $query->whereHas('payments', fn (Builder $query): Builder => $query->where('metodo', $metodo)),
                    )),
                // Quién vendió.
                SelectFilter::make('vendido_por_user_id')
                    ->label('Vendió')
                    ->relationship('vendidoPor', 'name', fn (Builder $query): Builder => $query->withTrashed()),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                self::detailAction(),
                self::voidAction(),
            ])
            ->emptyStateHeading('Todavía no hay ventas')
            ->emptyStateDescription('Las ventas se hacen desde el botón Kiosco de la barra de arriba.');
    }

    /**
     * "Detalle": ventana con los renglones, los cobros y, si corresponde, la anulación.
     *
     * Postcondiciones:
     * - Solo muestra; no modifica nada.
     */
    private static function detailAction(): Action
    {
        return Action::make('detail')
            ->label('Detalle')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (Sale $record): string => "Venta #{$record->getKey()}")
            ->modalDescription(fn (Sale $record): string => $record->created_at->format('d/m/Y H:i')." · vendió {$record->vendidoPor?->name}")
            ->modalContent(fn (Sale $record) => view('filament.kiosk.sale-detail', ['sale' => $record]))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'orions-modal'])
            // Solo para mirar.
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    /**
     * "Anular": solo administradores, en ventas vigentes, con motivo.
     *
     * Postcondiciones:
     * - Al confirmar, llama a SaleService::void(): la venta queda anulada, vuelve el stock y los
     *   cobros quedan reintegrados. Avisa que hay que devolver la plata.
     */
    private static function voidAction(): Action
    {
        return Action::make('void')
            ->label('Anular')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            // Ámbar, nunca rojo.
            ->color('warning')
            // Solo administradores y en ventas vigentes (ver SalePolicy).
            ->visible(fn (Sale $record): bool => Gate::forUser(Auth::guard('web')->user())->allows('void', $record))
            ->modalHeading(fn (Sale $record): string => "Anular la venta #{$record->getKey()}")
            ->modalDescription('Los productos vuelven al stock y los cobros quedan como reintegrados: acordate de devolver la plata. No se puede deshacer.')
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalWidth(Width::Medium)
            ->modalFooterActionsAlignment(Alignment::End)
            ->extraModalWindowAttributes(['class' => 'orions-modal'])
            ->modalSubmitActionLabel('Anular venta')
            ->schema([
                // Por qué.
                TextInput::make('motivo')
                    ->label('Motivo')
                    ->placeholder('Ej.: se cobró dos veces, devolvió el producto')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Sale $record, array $data): void {
                // Quién anula.
                $user = Auth::guard('web')->user();
                abort_unless($user instanceof User && Gate::forUser($user)->allows('void', $record), 403);

                try {
                    app(SaleService::class)->void($record, $user, $data['motivo']);
                } catch (SaleException $exception) {
                    // Por ejemplo, otra persona la anuló recién.
                    Notification::make()->warning()->title('No se pudo anular')->body($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title("Venta #{$record->getKey()} anulada")->send();
            });
    }

    public static function getPages(): array
    {
        return [
            // Solo el listado; el detalle se abre en una ventana.
            'index' => ListSales::route('/'),
        ];
    }
}
