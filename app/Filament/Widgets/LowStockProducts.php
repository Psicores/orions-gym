<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\Actions\StockActions;
use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista "Para reponer" del inicio: productos activos del kiosco sin stock o en el mínimo, con el
 * botón para cargar mercadería. Solo aparece si hay algo para reponer.
 */
class LowStockProducts extends TableWidget
{
    // Debajo de "Para cobrar".
    protected static ?int $sort = 3;

    // Ocupa todo el ancho del inicio.
    protected int|string|array $columnSpan = 'full';

    /**
     * Consulta de productos para reponer.
     *
     * Precondiciones:
     * - Existe la tabla products.
     *
     * Postcondiciones:
     * - Productos activos, no dados de baja, que controlan stock y tienen 0 o menos, o están en su
     *   stock mínimo o por debajo. No modifica nada.
     *
     * @return Builder<Product>
     */
    public static function query(): Builder
    {
        return Product::query()
            // A la venta y contando stock.
            ->where('activo', true)
            ->where('controla_stock', true)
            // Sin stock, o en el mínimo.
            ->where(fn (Builder $query): Builder => $query
                ->where('stock', '<=', 0)
                ->orWhere(fn (Builder $query): Builder => $query
                    ->whereNotNull('stock_minimo')
                    ->whereColumn('stock', '<=', 'stock_minimo')));
    }

    /**
     * Solo se muestra si hay algo para reponer.
     *
     * Postcondiciones:
     * - true si la consulta tiene al menos un producto. No modifica nada.
     */
    public static function canView(): bool
    {
        // Un inicio sin kiosco cargado no muestra la lista vacía.
        return static::query()->exists();
    }

    /**
     * Arma la tabla de productos para reponer.
     *
     * Postcondiciones:
     * - Muestra nombre, categoría, stock (en ámbar) y mínimo, de menor a mayor stock, con el botón
     *   "Cargar mercadería". No modifica nada salvo al cargar.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada.
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading('Para reponer')
            ->description('Productos del kiosco sin stock o en el mínimo.')
            ->query(static::query()->with('category'))
            ->columns([
                // Producto.
                TextColumn::make('nombre')
                    ->label('Producto'),
                // Categoría.
                TextColumn::make('category.nombre')
                    ->label('Categoría')
                    ->placeholder('—'),
                // Stock en ámbar (nunca rojo).
                TextColumn::make('stock')
                    ->label('Stock')
                    ->color('warning'),
                // Mínimo configurado.
                TextColumn::make('stock_minimo')
                    ->label('Mínimo')
                    ->placeholder('—'),
            ])
            // Los que menos tienen primero.
            ->defaultSort('stock')
            ->recordActions([
                // Cargar lo que llegó sin ir a Productos.
                StockActions::receive(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
