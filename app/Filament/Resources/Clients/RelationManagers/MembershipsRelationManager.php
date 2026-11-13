<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\MembershipStatusEnum;
use App\Filament\Resources\Clients\Actions\GrantCourtesyAction;
use App\Filament\Resources\Clients\Actions\RegisterPaymentAction;
use App\Models\Membership;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pestaña "Membresías" dentro de la ficha de un cliente: lista sus membresías y
 * permite crearle una nueva (plan, fechas y estado inicial), editarlas o borrarlas.
 */
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Membresías';

    protected static ?string $modelLabel = 'membresía';

    protected static ?string $pluralModelLabel = 'membresías';

    /**
     * Indica si la pestaña es de solo lectura.
     *
     * Filament deja de solo lectura las tablas relacionadas en las páginas de vista. Acá se
     * desactiva porque la ficha del cliente es la pantalla de trabajo de recepción: desde ahí
     * se crean, editan y cobran las membresías.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve false: crear, editar y borrar membresías está disponible también en la ficha.
     *
     * @return bool Siempre false.
     */
    public function isReadOnly(): bool
    {
        // Nunca es de solo lectura.
        return false;
    }

    /**
     * Arma el formulario (en un modal) para crear o editar una membresía del cliente.
     *
     * Precondiciones:
     * - Se abre desde la ficha de un cliente existente ($this->getOwnerRecord()).
     * - Existe al menos un plan activo para poder elegirlo.
     *
     * Postcondiciones:
     * - Al crear: la membresía queda asociada al cliente actual, con el plan, las fechas y el
     *   estado elegidos; los créditos los copia el modelo desde el plan (no se piden).
     * - Al editar: además se pueden corregir los créditos disponibles.
     * - No se puede guardar si falta plan, fechas o estado, si el vencimiento es anterior
     *   al inicio, o si los créditos son negativos.
     * - Solo se ofrecen planes activos; al editar una membresía cuyo plan fue dado de baja,
     *   ese plan sigue apareciendo y se puede guardar sin cambiarlo.
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El esquema con los campos de la membresía.
     */
    public function form(Schema $schema): Schema
    {
        // Devuelve el esquema recibido con los campos del formulario cargados.
        return $schema
            // Lista de campos, en el orden en que se muestran.
            ->components([
                // Lista desplegable para elegir el plan (columna plan_id).
                Select::make('plan_id')
                    // Texto que ve el usuario sobre el campo.
                    ->label('Plan')
                    // Toma las opciones de la relación plan() mostrando el nombre de cada plan.
                    ->relationship(
                        // Relación Membership::plan().
                        name: 'plan',
                        // Columna que se muestra como texto de cada opción.
                        titleAttribute: 'nombre',
                        // Ofrece solo planes activos y, al editar, también el plan actual de la
                        // membresía aunque esté dado de baja (si no, no se podría volver a guardar).
                        modifyQueryUsing: fn (Builder $query, ?Membership $record): Builder => $query->where(
                            // Agrupa las condiciones entre paréntesis para no romper otros filtros.
                            fn (Builder $query): Builder => $query
                                // Planes que no están dados de baja.
                                ->whereNull('plans.deleted_at')
                                // O, si se está editando, el plan que ya tiene la membresía.
                                ->when($record, fn (Builder $query): Builder => $query->orWhere('plans.id', $record->plan_id)),
                        ),
                    )
                    // Permite escribir para filtrar los planes.
                    ->searchable()
                    // Carga todas las opciones al abrir (son pocos planes).
                    ->preload()
                    // No se puede guardar sin plan.
                    ->required(),
                // Selector de fecha de inicio de la membresía.
                DatePicker::make('fecha_inicio')
                    // Texto que ve el usuario sobre el campo.
                    ->label('Fecha de inicio')
                    // Al crear, viene cargada la fecha de hoy.
                    ->default(now())
                    // No se puede guardar vacía.
                    ->required(),
                // Selector de fecha de vencimiento de la membresía.
                DatePicker::make('fecha_vencimiento')
                    // Texto que ve el usuario sobre el campo.
                    ->label('Fecha de vencimiento')
                    // Al crear, viene cargada la fecha de dentro de un mes.
                    ->default(now()->addMonth())
                    // No puede ser anterior a la fecha de inicio.
                    ->afterOrEqual('fecha_inicio')
                    // No se puede guardar vacía.
                    ->required(),
                // Lista desplegable para el estado de acceso (columna estado).
                Select::make('estado')
                    // Texto que ve el usuario sobre el campo.
                    ->label('Estado')
                    // Las opciones salen del enum, con su nombre legible (getLabel).
                    ->options(MembershipStatusEnum::class)
                    // Al crear, viene preseleccionado Entrenando.
                    ->default(MembershipStatusEnum::Entrenando)
                    // No se puede guardar sin estado.
                    ->required(),
                // Campo numérico para corregir los créditos que le quedan.
                TextInput::make('creditos_disponibles')
                    // Texto que ve el usuario sobre el campo.
                    ->label('Créditos disponibles')
                    // Solo acepta números enteros.
                    ->integer()
                    // Admite negativos: quien ingresa sin créditos queda debiendo clases.
                    ->minValue(-999)
                    // Obligatorio cuando se muestra.
                    ->required()
                    // Solo aparece al editar: al crear, el modelo copia los créditos del plan.
                    ->visibleOn('edit'),
            ]);
    }

    /**
     * Arma la tabla de membresías del cliente.
     *
     * Precondiciones:
     * - Se muestra dentro de la ficha de un cliente existente.
     *
     * Postcondiciones:
     * - Lista solo las membresías de ese cliente, de la más reciente a la más vieja.
     * - Muestra el plan (aunque esté dado de baja), las fechas, el estado con su color e
     *   ícono y los créditos disponibles.
     * - Cada fila muestra a la vista el botón "Cobrar" (salvo en membresías vencidas) y agrupa
     *   el resto de las acciones en un menú.
     * - Permite dar cortesía (Activo flex, cliente en Activo temporal) a las membresías
     *   Entrenando o En pausa.
     * - Permite crear, editar y borrar membresías. No ofrece asociar ni desasociar
     *   membresías de otros clientes.
     * - No ofrece borrar una membresía que ya tiene pagos (la base lo impide para no perder
     *   el historial de caja); en el borrado masivo esas filas se informan como no borradas.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada con columnas y acciones.
     */
    public function table(Table $table): Table
    {
        // Devuelve la tabla recibida con columnas y acciones cargadas.
        return $table
            // Agrega a cada fila si tiene pagos (payments_exists) en la misma consulta, para decidir si se puede borrar.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists('payments'))
            // Usa el nombre del plan como título de cada fila en mensajes y confirmaciones.
            ->recordTitle(fn (Membership $record): string => $record->plan->nombre)
            // Columnas visibles, en el orden en que se muestran.
            ->columns([
                // Nombre del plan contratado, a través de la relación plan().
                TextColumn::make('plan.nombre')
                    // Encabezado de la columna.
                    ->label('Plan')
                    // Incluye esta columna en el buscador de la tabla.
                    ->searchable(),
                // Fecha de inicio.
                TextColumn::make('fecha_inicio')
                    // Encabezado de la columna.
                    ->label('Inicio')
                    // Formato día/mes/año.
                    ->date('d/m/Y')
                    // Permite ordenar haciendo clic en el encabezado.
                    ->sortable(),
                // Fecha de vencimiento.
                TextColumn::make('fecha_vencimiento')
                    // Encabezado de la columna.
                    ->label('Vencimiento')
                    // Formato día/mes/año.
                    ->date('d/m/Y')
                    // Permite ordenar haciendo clic en el encabezado.
                    ->sortable(),
                // Estado de acceso (caso de MembershipStatusEnum gracias al cast del modelo).
                TextColumn::make('estado')
                    // Encabezado de la columna.
                    ->label('Estado')
                    // Lo muestra como etiqueta con el nombre, color e ícono definidos en el enum.
                    ->badge(),
                // Créditos que le quedan.
                TextColumn::make('creditos_disponibles')
                    // Encabezado de la columna.
                    ->label('Créditos')
                    // Lo formatea como número.
                    ->numeric(),
            ])
            // Al entrar, muestra primero la membresía que empezó más recientemente.
            ->defaultSort('fecha_inicio', 'desc')
            // Acciones arriba de la tabla.
            ->headerActions([
                // Botón para crear una membresía nueva para este cliente.
                CreateAction::make()
                    // Después de crearla, avisa a la página para refrescar el vencimiento del cliente.
                    ->after(fn () => $this->dispatch('cliente-actualizado')),
            ])
            // Acciones de cada fila.
            ->recordActions([
                // Cobro rápido de esta membresía: es la acción más usada, por eso queda a la vista.
                RegisterPaymentAction::make()
                    // Texto corto para la fila.
                    ->label('Cobrar')
                    // No se ofrece en membresías vencidas (no admiten pagos).
                    ->hidden(fn (Membership $record): bool => ! $record->estado->acceptsPayments()),
                // El resto de las acciones va en un menú "⋮" para no llenar la fila de botones.
                ActionGroup::make([
                    // Botón "Dar cortesía": pasa la membresía a Activo flex para que el socio entre hasta renovar.
                    GrantCourtesyAction::make(),
                    // Botón para editar la membresía.
                    EditAction::make()
                        // Después de guardarla, avisa a la página para refrescar el vencimiento del cliente.
                        ->after(fn () => $this->dispatch('cliente-actualizado')),
                    // Botón para borrar la membresía (pide confirmación).
                    DeleteAction::make()
                        // Se oculta si la membresía ya tiene pagos registrados.
                        ->hidden(fn (Membership $record): bool => (bool) $record->payments_exists)
                        // Después de borrarla, avisa a la página para refrescar el vencimiento del cliente.
                        ->after(fn () => $this->dispatch('cliente-actualizado')),
                ]),
            ])
            // Acciones sobre varias filas seleccionadas.
            ->toolbarActions([
                // Agrupa las acciones masivas en un solo menú.
                BulkActionGroup::make([
                    // Borra las membresías seleccionadas.
                    DeleteBulkAction::make()
                        // Después de borrarlas, avisa a la página para refrescar el vencimiento del cliente.
                        ->after(fn () => $this->dispatch('cliente-actualizado')),
                ]),
            ]);
    }
}
