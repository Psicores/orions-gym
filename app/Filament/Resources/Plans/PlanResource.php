<?php

namespace App\Filament\Resources\Plans;

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    // URL en castellano: /admin/planes.
    protected static ?string $slug = 'planes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $modelLabel = 'plan';

    protected static ?string $pluralModelLabel = 'planes';

    protected static ?string $recordTitleAttribute = 'nombre';

    /**
     * Arma el formulario de alta y edición de planes del panel /admin.
     *
     * Precondiciones:
     * - Existe la tabla plans con las columnas de la migración create_plans_table.
     *
     * Postcondiciones:
     * - No se puede guardar si falta algún campo.
     * - El precio queda entre 0 y 99.999.999,99 con hasta 2 decimales (lo que entra en decimal(10,2)).
     * - Los créditos quedan como entero entre 1 y 999 (999 = plan libre).
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El esquema con los campos del plan.
     */
    public static function form(Schema $schema): Schema
    {
        // Devuelve el esquema recibido con los campos del formulario cargados.
        return $schema
            // Lista de campos, en el orden en que se muestran.
            ->components([
                // Campo de texto para el nombre del plan (columna nombre).
                TextInput::make('nombre')
                    // Texto que ve el usuario sobre el campo.
                    ->label('Nombre')
                    // No se puede guardar vacío.
                    ->required()
                    // Máximo de caracteres, igual al de la columna string.
                    ->maxLength(255),
                // Campo numérico para el precio (columna precio).
                TextInput::make('precio')
                    // Texto que ve el usuario sobre el campo.
                    ->label('Precio')
                    // Solo acepta números (valida y muestra teclado numérico).
                    ->numeric()
                    // Muestra el signo $ antes del valor.
                    ->prefix('$')
                    // Permite centavos: avanza de a 0,01.
                    ->step(0.01)
                    // No permite precios negativos.
                    ->minValue(0)
                    // Tope que entra en la columna decimal(10,2).
                    ->maxValue(99999999.99)
                    // No se puede guardar vacío.
                    ->required(),
                // Campo numérico entero para los créditos (columna creditos_otorgados).
                TextInput::make('creditos_otorgados')
                    // Texto que ve el usuario sobre el campo.
                    ->label('Créditos otorgados')
                    // Ayuda debajo del campo con la convención del plan libre.
                    ->helperText('Clases que habilita el plan. Usá 999 para un plan libre.')
                    // Solo acepta números enteros.
                    ->integer()
                    // Al menos una clase.
                    ->minValue(1)
                    // 999 es el valor que representa "libre"; no tiene sentido pasarlo.
                    ->maxValue(999)
                    // No se puede guardar vacío.
                    ->required(),
            ]);
    }

    /**
     * Arma la tabla del listado de planes del panel /admin.
     *
     * Precondiciones:
     * - Existe la tabla plans con las columnas de la migración create_plans_table.
     * - PHP tiene la extensión intl (la usa el formato de moneda).
     *
     * Postcondiciones:
     * - Lista todos los planes ordenados por nombre, con el precio en pesos argentinos
     *   (por ejemplo "$ 25.000,50") y los créditos.
     * - Por defecto oculta los planes dados de baja; el filtro permite verlos.
     * - Se puede buscar por nombre, editar un plan, darlo de baja (baja lógica) o restaurarlo.
     *   No hay borrado definitivo, para no romper el historial de membresías.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada con columnas y acciones.
     */
    public static function table(Table $table): Table
    {
        // Devuelve la tabla recibida con columnas y acciones cargadas.
        return $table
            // Usa el nombre como título de cada fila en mensajes y confirmaciones.
            ->recordTitleAttribute('nombre')
            // Columnas visibles, en el orden en que se muestran.
            ->columns([
                // Nombre del plan.
                TextColumn::make('nombre')
                    // Encabezado de la columna.
                    ->label('Nombre')
                    // Incluye esta columna en el buscador de la tabla.
                    ->searchable()
                    // Permite ordenar haciendo clic en el encabezado.
                    ->sortable(),
                // Precio del plan.
                TextColumn::make('precio')
                    // Encabezado de la columna.
                    ->label('Precio')
                    // Lo formatea como pesos argentinos con separadores locales ($ 25.000,50).
                    ->money('ARS', locale: 'es_AR')
                    // Permite ordenar haciendo clic en el encabezado.
                    ->sortable(),
                // Créditos que otorga el plan.
                TextColumn::make('creditos_otorgados')
                    // Encabezado de la columna.
                    ->label('Créditos')
                    // Lo formatea como número (alineado y con separador de miles).
                    ->numeric()
                    // Permite ordenar haciendo clic en el encabezado.
                    ->sortable(),
            ])
            // Al entrar, ordena por nombre de la A a la Z.
            ->defaultSort('nombre')
            // Filtros disponibles en la tabla.
            ->filters([
                // Filtro para ver solo activos, solo dados de baja o todos.
                TrashedFilter::make(),
            ])
            // Acciones de cada fila.
            ->recordActions([
                // Botón para abrir el formulario de edición del plan.
                EditAction::make(),
            ])
            // Acciones sobre varias filas seleccionadas.
            ->toolbarActions([
                // Agrupa las acciones masivas en un solo menú.
                BulkActionGroup::make([
                    // Da de baja (baja lógica) a los planes seleccionados.
                    DeleteBulkAction::make(),
                    // Restaura los planes seleccionados que estaban dados de baja.
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/crear'),
            'edit' => EditPlan::route('/{record}/editar'),
        ];
    }

    /**
     * Consulta que usa Filament para encontrar el plan de la URL (por ejemplo /admin/planes/3/editar).
     *
     * Precondiciones:
     * - El modelo Plan usa SoftDeletes (columna deleted_at).
     *
     * Postcondiciones:
     * - Devuelve una consulta que incluye también a los planes dados de baja, para poder
     *   abrirlos y restaurarlos desde el panel.
     * - No modifica datos.
     *
     * @return Builder<Plan> Consulta base de planes sin el filtro de baja lógica.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        // Parte de la consulta por defecto de Filament para el modelo Plan.
        return parent::getRecordRouteBindingEloquentQuery()
            // Le quita el filtro global que oculta a los registros dados de baja.
            ->withoutGlobalScopes([
                // El filtro que agrega SoftDeletes ("deleted_at is null").
                SoftDeletingScope::class,
            ]);
    }
}
