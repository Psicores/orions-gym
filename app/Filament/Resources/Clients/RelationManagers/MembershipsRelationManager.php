<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\MembershipStatusEnum;
use App\Models\Membership;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pestaña "Membresías" dentro de la edición de un cliente: lista sus membresías y
 * permite crearle una nueva (plan, fechas y estado inicial), editarlas o borrarlas.
 */
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Membresías';

    protected static ?string $modelLabel = 'membresía';

    protected static ?string $pluralModelLabel = 'membresías';

    /**
     * Arma el formulario (en un modal) para crear o editar una membresía del cliente.
     *
     * Precondiciones:
     * - Se abre desde la edición de un cliente existente ($this->getOwnerRecord()).
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
                    // No permite créditos negativos.
                    ->minValue(0)
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
     * - Se muestra dentro de la edición de un cliente existente.
     *
     * Postcondiciones:
     * - Lista solo las membresías de ese cliente, de la más reciente a la más vieja.
     * - Muestra el plan (aunque esté dado de baja), las fechas, el estado con su color e
     *   ícono y los créditos disponibles.
     * - Permite dar días de gracia (Activo flex + vencimiento extendido, cliente en Activo temporal)
     *   a las membresías no vencidas.
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
                CreateAction::make(),
            ])
            // Acciones de cada fila.
            ->recordActions([
                // Botón para dar días de gracia: pasa la membresía a Activo flex y corre el vencimiento.
                Action::make('grantGraceDays')
                    // Texto del botón.
                    ->label('Dar días de gracia')
                    // Ícono de reloj.
                    ->icon(Heroicon::OutlinedClock)
                    // Celeste, el mismo color que el estado Activo flex.
                    ->color('info')
                    // No se ofrece para membresías vencidas (a esas se les crea una nueva).
                    ->hidden(fn (Membership $record): bool => $record->estado === MembershipStatusEnum::Vencida)
                    // Título del modal con el plan de la membresía.
                    ->modalHeading(fn (Membership $record): string => "Dar días de gracia ({$record->plan->nombre})")
                    // Explica qué va a pasar al confirmar.
                    ->modalDescription(fn (Membership $record): string => 'Pasa la membresía a Activo flex y corre el vencimiento (hoy: '.$record->fecha_vencimiento->format('d/m/Y').'). Si ya venció, los días se cuentan desde hoy.')
                    // Texto del botón que confirma.
                    ->modalSubmitActionLabel('Dar gracia')
                    // Campos del modal.
                    ->schema([
                        // Cantidad de días a extender.
                        TextInput::make('dias')
                            // Texto del campo.
                            ->label('Días de gracia')
                            // Solo enteros.
                            ->integer()
                            // Una semana por defecto.
                            ->default(7)
                            // Al menos un día.
                            ->minValue(1)
                            // Como mucho dos meses.
                            ->maxValue(60)
                            // Obligatorio.
                            ->required(),
                    ])
                    // Al confirmar, aplica la gracia desde el modelo (que valida estado y rango).
                    ->action(function (array $data, Membership $record): void {
                        // Cambia estado y vencimiento de la membresía, y pasa al cliente a Activo temporal.
                        $record->grantGraceDays((int) $data['dias']);

                        // Avisa a la página para que el formulario del cliente muestre su nuevo estado.
                        $this->dispatch('estado-cliente-actualizado');

                        // Confirma con la nueva fecha de vencimiento.
                        Notification::make()
                            // Título de la notificación.
                            ->title('Días de gracia otorgados')
                            // Detalle con el nuevo vencimiento.
                            ->body('Ahora vence el '.$record->fecha_vencimiento->format('d/m/Y').'.')
                            // Verde.
                            ->success()
                            // La muestra.
                            ->send();
                    }),
                // Botón para editar la membresía.
                EditAction::make(),
                // Botón para borrar la membresía (pide confirmación).
                DeleteAction::make()
                    // Se oculta si la membresía ya tiene pagos registrados.
                    ->hidden(fn (Membership $record): bool => (bool) $record->payments_exists),
            ])
            // Acciones sobre varias filas seleccionadas.
            ->toolbarActions([
                // Agrupa las acciones masivas en un solo menú.
                BulkActionGroup::make([
                    // Borra las membresías seleccionadas.
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
