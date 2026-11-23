<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use App\Support\ImageOptimizer;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Productos del kiosco.
 *
 * Todo el personal ve el listado (precios y stock); solo los administradores dan de alta, editan
 * o dan de baja (ver App\Policies\ProductPolicy). El stock no se edita acá: se mueve con cargas de
 * mercadería, ajustes y ventas.
 */
class ProductResource extends Resource
{
    /** Nombre del grupo del menú lateral con todo lo del kiosco. */
    public const string NAVIGATION_GROUP = 'Kiosco';

    protected static ?string $model = Product::class;

    // URL en castellano: /admin/kiosco/productos.
    protected static ?string $slug = 'kiosco/productos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    // Grupo "Kiosco" del menú.
    protected static string|UnitEnum|null $navigationGroup = self::NAVIGATION_GROUP;

    // Primero del grupo.
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'productos';

    protected static ?string $recordTitleAttribute = 'nombre';

    /**
     * Formulario de alta y edición de un producto (en una ventana modal).
     *
     * Precondiciones:
     * - Existen las tablas products y product_categories.
     *
     * Postcondiciones:
     * - Obligatorios: nombre y precio. Opcionales: categoría, precio en efectivo, costo, código
     *   de barras, SKU, stock mínimo y foto.
     * - El código de barras y el SKU no se repiten entre productos (tampoco con los dados de baja).
     * - Los importes quedan entre 0 y 99.999.999,99.
     * - La foto se guarda achicada (600×600 como máximo) y liviana (ver ImageOptimizer).
     * - El stock no se carga acá.
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El esquema con los campos.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            // Dos columnas.
            ->columns(2)
            ->components([
                // Nombre que se ve al vender.
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->placeholder('Ej.: Agua 500 ml')
                    ->required()
                    ->maxLength(120)
                    // Ancho completo.
                    ->columnSpanFull(),
                // Categoría, con alta rápida sin salir del formulario.
                Select::make('product_category_id')
                    ->label('Categoría')
                    // Lista las categorías por nombre.
                    ->relationship('category', 'nombre')
                    // Permite buscar escribiendo.
                    ->searchable()
                    // Carga todas al abrir (son pocas).
                    ->preload()
                    // Botón "+" para crear una categoría nueva.
                    ->createOptionForm(ProductCategoryResource::formFields())
                    // Ancho completo.
                    ->columnSpanFull(),
                // Precio de lista.
                self::moneyInput('precio', 'Precio')
                    ->required(),
                // Precio pagando en efectivo.
                self::moneyInput('precio_efectivo', 'Precio en efectivo')
                    ->helperText('Opcional. Vacío: se cobra el precio normal.'),
                // Código de barras del envase.
                TextInput::make('barcode')
                    ->label('Código de barras')
                    ->helperText('Opcional. Escanealo con el lector o escribí los números.')
                    ->maxLength(64)
                    // No se repite (ignora el propio al editar).
                    ->unique(Product::class, 'barcode', ignoreRecord: true)
                    // Mensaje claro si ya existe.
                    ->validationMessages(['unique' => 'Ese código ya lo tiene otro producto (puede estar dado de baja).']),
                // Código interno.
                TextInput::make('sku')
                    ->label('Código interno (SKU)')
                    ->helperText('Opcional.')
                    ->maxLength(64)
                    // No se repite (ignora el propio al editar).
                    ->unique(Product::class, 'sku', ignoreRecord: true)
                    // Mensaje claro si ya existe.
                    ->validationMessages(['unique' => 'Ese código ya lo tiene otro producto (puede estar dado de baja).']),
                // Costo para el gimnasio.
                self::moneyInput('costo', 'Costo')
                    ->helperText('Opcional. Lo que le cuesta al gimnasio; no lo ve el socio.'),
                // Si se lleva la cuenta del stock.
                Toggle::make('controla_stock')
                    ->label('Controlar stock')
                    ->helperText('Apagalo para lo que no se cuenta (por ejemplo, un servicio).')
                    ->default(true)
                    // Muestra u oculta el stock mínimo al momento.
                    ->live(),
                // Umbral de aviso.
                TextInput::make('stock_minimo')
                    ->label('Stock mínimo')
                    ->helperText('Opcional. Avisa cuando quedan estas unidades o menos.')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(99999)
                    // Solo si se controla el stock.
                    ->visible(fn (Get $get): bool => (bool) $get('controla_stock')),
                // Aparece primero al vender.
                Toggle::make('destacado')
                    ->label('Destacado')
                    ->helperText('Aparece primero en la pantalla de venta.'),
                // Se ofrece para vender.
                Toggle::make('activo')
                    ->label('Activo')
                    ->helperText('Apagado, no se ofrece para vender.')
                    ->default(true),
                // Foto del producto.
                FileUpload::make('imagen_path')
                    ->label('Foto')
                    // Solo imágenes.
                    ->image()
                    // Disco público (se muestra en la pantalla de venta).
                    ->disk('public')
                    // Carpeta dentro del disco.
                    ->directory('products')
                    // Original de hasta 10 MB (foto del celular); se guarda mucho más liviana.
                    ->maxSize(10240)
                    // 600×600 como máximo y liviana en el servidor (ver ImageOptimizer).
                    ->when(true, fn (FileUpload $field) => ImageOptimizer::configure($field, 600, 600))
                    ->helperText('Opcional. Una foto del producto sobre fondo claro se reconoce rápido.')
                    // Ancho completo.
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Campo de importe en pesos (precio, precio en efectivo o costo).
     *
     * Precondiciones:
     * - $name es una columna decimal(10,2).
     *
     * Postcondiciones:
     * - Devuelve un campo numérico con $ adelante, centavos, entre 0 y 99.999.999,99.
     *
     * @param  string  $name  Columna.
     * @param  string  $label  Texto sobre el campo.
     * @return TextInput El campo.
     */
    private static function moneyInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            // Solo números.
            ->numeric()
            // Signo pesos adelante.
            ->prefix('$')
            // Centavos.
            ->step(0.01)
            // Sin negativos.
            ->minValue(0)
            // Lo que entra en decimal(10,2).
            ->maxValue(99999999.99);
    }

    /**
     * Listado de productos.
     *
     * Precondiciones:
     * - Existen las tablas products y product_categories.
     *
     * Postcondiciones:
     * - Muestra foto, nombre, categoría, precios y stock (en ámbar si está en el mínimo o por
     *   debajo; una raya si no se controla), destacado y activo.
     * - Se busca por nombre, código de barras o SKU; se filtra por categoría, activo y bajas.
     * - Los botones de editar, dar de baja y restaurar solo aparecen para administradores.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            // Título de cada fila en mensajes y confirmaciones.
            ->recordTitleAttribute('nombre')
            // Trae la categoría en la misma consulta.
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->columns([
                // Foto chica.
                ImageColumn::make('imagen_path')
                    ->label('')
                    ->disk('public')
                    ->square()
                    ->imageSize(40),
                // Nombre, buscable.
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                // Código de barras: oculto por defecto, pero se busca por él.
                TextColumn::make('barcode')
                    ->label('Código de barras')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                // SKU: oculto por defecto, pero se busca por él.
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                // Categoría.
                TextColumn::make('category.nombre')
                    ->label('Categoría')
                    ->placeholder('—'),
                // Precio de lista.
                TextColumn::make('precio')
                    ->label('Precio')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                // Precio en efectivo.
                TextColumn::make('precio_efectivo')
                    ->label('En efectivo')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—'),
                // Stock actual.
                TextColumn::make('stock')
                    ->label('Stock')
                    // Una raya si no se controla.
                    ->formatStateUsing(fn (Product $record): string => $record->controla_stock ? (string) $record->stock : '—')
                    // Ámbar si hay que reponer (nunca rojo).
                    ->color(fn (Product $record): ?string => $record->hasLowStock() || ($record->controla_stock && $record->stock <= 0) ? 'warning' : null)
                    ->sortable(),
                // Destacado.
                IconColumn::make('destacado')
                    ->label('Destacado')
                    ->boolean()
                    ->toggleable(),
                // Activo.
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            // Alfabético.
            ->defaultSort('nombre')
            ->filters([
                // Por categoría.
                SelectFilter::make('product_category_id')
                    ->label('Categoría')
                    ->relationship('category', 'nombre'),
                // Activos, apagados o todos.
                TernaryFilter::make('activo')
                    ->label('Activo'),
                // Vigentes, dados de baja o todos.
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Editar en una ventana modal (solo administradores, ver ProductPolicy).
                EditAction::make()
                    ->modalIcon(Heroicon::OutlinedPencilSquare)
                    ->modalDescription('Cambiar el precio no modifica las ventas ya hechas.')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->extraModalWindowAttributes(['class' => 'orions-modal']),
                // Dar de baja (baja lógica).
                DeleteAction::make(),
                // Restaurar uno dado de baja.
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Dar de baja los seleccionados.
                    DeleteBulkAction::make(),
                    // Restaurar los seleccionados.
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            // Única página: el listado, con alta y edición en ventanas modales.
            'index' => ManageProducts::route('/'),
        ];
    }
}
