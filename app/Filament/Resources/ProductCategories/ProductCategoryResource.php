<?php

namespace App\Filament\Resources\ProductCategories;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\ProductCategories\Pages\ManageProductCategories;
use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Categorías de productos del kiosco. Solo las manejan los administradores.
 */
class ProductCategoryResource extends Resource
{
    // Solo para administradores.
    use AdminOnly;

    protected static ?string $model = ProductCategory::class;

    // URL en castellano: /admin/kiosco/categorias.
    protected static ?string $slug = 'kiosco/categorias';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    // Grupo "Kiosco" del menú.
    protected static string|UnitEnum|null $navigationGroup = ProductResource::NAVIGATION_GROUP;

    // Después de Productos.
    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Categorías';

    protected static ?string $modelLabel = 'categoría';

    protected static ?string $pluralModelLabel = 'categorías';

    protected static ?string $recordTitleAttribute = 'nombre';

    /**
     * Formulario de alta y edición de una categoría (en una ventana modal).
     *
     * Precondiciones:
     * - Existe la tabla product_categories.
     *
     * Postcondiciones:
     * - El nombre es obligatorio, de hasta 60 caracteres y no se repite.
     * - El orden es un entero entre 0 y 999 (vacío = 0).
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El esquema con los campos.
     */
    public static function form(Schema $schema): Schema
    {
        // Devuelve el esquema con los campos.
        return $schema->components(self::formFields());
    }

    /**
     * Campos de una categoría; los reusa el alta rápida desde el formulario de productos.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve el nombre (obligatorio, único) y el orden. No modifica nada.
     *
     * @return array<int, TextInput>
     */
    public static function formFields(): array
    {
        return [
            // Nombre del botón.
            TextInput::make('nombre')
                // Texto sobre el campo.
                ->label('Nombre')
                // Ejemplo dentro del campo vacío.
                ->placeholder('Ej.: Bebidas')
                // Obligatorio.
                ->required()
                // Igual que la columna.
                ->maxLength(60)
                // No se repite (ignora la propia al editar).
                ->unique(ProductCategory::class, 'nombre', ignoreRecord: true),
            // Posición en la pantalla de venta.
            TextInput::make('orden')
                // Texto sobre el campo.
                ->label('Orden')
                // Explica para qué sirve.
                ->helperText('Las de número menor aparecen primero al vender.')
                // Solo enteros.
                ->integer()
                // Sin negativos.
                ->minValue(0)
                // Tope razonable.
                ->maxValue(999)
                // Vacío vale 0.
                ->default(0),
        ];
    }

    /**
     * Listado de categorías.
     *
     * Precondiciones:
     * - Existe la tabla product_categories.
     *
     * Postcondiciones:
     * - Muestra nombre, orden y cantidad de productos, ordenadas por orden y nombre.
     * - Se puede editar o borrar; los productos de una categoría borrada quedan sin categoría.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            // Columnas.
            ->columns([
                // Nombre.
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),
                // Posición.
                TextColumn::make('orden')
                    ->label('Orden')
                    ->sortable(),
                // Cuántos productos vigentes tiene (sin contar los dados de baja).
                TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products'),
            ])
            // Primero por orden, después alfabético.
            ->defaultSort(fn ($query) => $query->orderBy('orden')->orderBy('nombre'))
            // Acciones de cada fila.
            ->recordActions([
                // Editar en una ventana modal.
                EditAction::make()
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->extraModalWindowAttributes(['class' => 'orions-modal']),
                // Borrar, con aviso de qué pasa con los productos.
                DeleteAction::make()
                    ->modalDescription('Los productos de esta categoría quedan sin categoría; no se borran.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            // Única página: el listado, con alta y edición en ventanas modales.
            'index' => ManageProductCategories::route('/'),
        ];
    }
}
