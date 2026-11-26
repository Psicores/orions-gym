<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\StockMovementTypeEnum;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Historial de movimientos de stock del kiosco. Solo lectura: los movimientos se crean al cargar
 * mercadería, ajustar o vender, y no se editan ni se borran. Lo ve todo el personal.
 */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    // URL en castellano: /admin/kiosco/movimientos.
    protected static ?string $slug = 'kiosco/movimientos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    // Grupo "Kiosco" del menú.
    protected static string|UnitEnum|null $navigationGroup = ProductResource::NAVIGATION_GROUP;

    // Después de Productos y Categorías.
    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Movimientos de stock';

    protected static ?string $modelLabel = 'movimiento de stock';

    protected static ?string $pluralModelLabel = 'movimientos de stock';

    /** Nadie crea movimientos a mano desde acá. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Los movimientos no se editan. */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** Los movimientos no se borran. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Los movimientos no se borran. */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Listado de movimientos.
     *
     * Precondiciones:
     * - Existe la tabla stock_movements.
     *
     * Postcondiciones:
     * - Lista del más nuevo al más viejo: fecha, producto, tipo, cantidad con signo, stock que
     *   quedó, quién y nota. Se filtra por producto y por tipo. No modifica nada.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            // En el celular cada fila es una tarjeta (ver .orions-stack-* en filament/admin/styles.blade.php).
            ->stackedOnMobile()
            // Trae producto y persona en la misma consulta.
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'registradoPor']))
            ->columns([
                // Cuándo.
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                // Qué producto.
                TextColumn::make('product.nombre')
                    ->label('Producto')
                    // En el celular es el título de la tarjeta.
                    ->extraCellAttributes(['class' => 'orions-stack-title'])
                    ->searchable(),
                // Tipo como etiqueta de color.
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                // Unidades con signo.
                TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->formatStateUsing(fn (int $state): string => sprintf('%+d', $state)),
                // Stock que quedó.
                TextColumn::make('stock_resultante')
                    ->label('Stock después'),
                // Quién.
                TextColumn::make('registradoPor.name')
                    ->label('Registró')
                    // Solo en pantallas grandes.
                    ->visibleFrom('sm'),
                // Detalle.
                TextColumn::make('nota')
                    ->label('Nota')
                    ->placeholder('—')
                    ->wrap(),
            ])
            // Lo más nuevo primero.
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Por producto (incluye los dados de baja).
                SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'nombre')
                    ->searchable()
                    ->preload(),
                // Por tipo.
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(StockMovementTypeEnum::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            // Solo el listado.
            'index' => ListStockMovements::route('/'),
        ];
    }
}
