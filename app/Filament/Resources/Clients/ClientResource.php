<?php

namespace App\Filament\Resources\Clients;

use App\Enums\ClientStatusEnum;
use App\Filament\Resources\Clients\Actions\RegisterPaymentAction;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\PaymentsRelationManager;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $recordTitleAttribute = 'apellido';

    /**
     * Arma el formulario de alta y edición de clientes del panel /admin.
     *
     * Precondiciones:
     * - Existe la tabla clients con las columnas de la migración create_clients_table.
     * - El disco "public" está configurado y, para ver los archivos en el navegador,
     *   se corrió php artisan storage:link.
     *
     * Postcondiciones:
     * - El formulario tiene tres secciones: Datos personales, Cuota y Salud y documentación.
     * - No se puede guardar si falta un campo obligatorio, si el DNI o el email ya existen
     *   en otro cliente (también entre los dados de baja) o si un archivo supera los 2 MB.
     * - Al guardar, la foto queda en storage/app/public/clients/fotos y el certificado en
     *   storage/app/public/clients/certificados, con nombre aleatorio; en la base se guarda
     *   solo la ruta relativa.
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El esquema con todos los campos del cliente.
     */
    public static function form(Schema $schema): Schema
    {
        // Devuelve el esquema recibido con los componentes del formulario cargados.
        return $schema
            // Lista de secciones del formulario, en el orden en que se muestran.
            ->components([
                // Sección 1: datos de identidad y contacto del cliente.
                Section::make('Datos personales')
                    // Muestra los campos en dos columnas.
                    ->columns(2)
                    // Campos de la sección.
                    ->schema([
                        // Campo de texto para el nombre (columna nombre).
                        TextInput::make('nombre')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Nombre')
                            // No se puede guardar vacío.
                            ->required()
                            // Máximo de caracteres, igual al de la columna string.
                            ->maxLength(255),
                        // Campo de texto para el apellido (columna apellido).
                        TextInput::make('apellido')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Apellido')
                            // No se puede guardar vacío.
                            ->required()
                            // Máximo de caracteres, igual al de la columna string.
                            ->maxLength(255),
                        // Campo de texto para el DNI (columna dni).
                        TextInput::make('dni')
                            // Texto que ve el usuario sobre el campo.
                            ->label('DNI')
                            // No se puede guardar vacío.
                            ->required()
                            // Máximo de caracteres, igual al de la columna string.
                            ->maxLength(255)
                            // Rechaza un DNI que ya tenga otro cliente; al editar ignora al propio cliente.
                            ->unique(),
                        // Selector de fecha para la fecha de nacimiento.
                        DatePicker::make('fecha_nacimiento')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Fecha de nacimiento')
                            // No se puede guardar vacío.
                            ->required()
                            // No permite elegir una fecha futura.
                            ->maxDate(now()),
                        // Campo de texto para el celular (columna cel).
                        TextInput::make('cel')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Celular')
                            // Lo marca como teléfono (en el celular abre el teclado numérico).
                            ->tel()
                            // No se puede guardar vacío.
                            ->required()
                            // Máximo de caracteres, igual al de la columna string.
                            ->maxLength(255),
                        // Campo de texto para el email (columna email).
                        TextInput::make('email')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Email')
                            // Valida que tenga formato de email.
                            ->email()
                            // No se puede guardar vacío.
                            ->required()
                            // Máximo de caracteres, igual al de la columna string.
                            ->maxLength(255)
                            // Rechaza un email que ya tenga otro cliente; al editar ignora al propio cliente.
                            ->unique(),
                    ]),
                // Sección 2: situación de pago del cliente.
                Section::make('Cuota')
                    // Muestra los campos en dos columnas.
                    ->columns(2)
                    // Campos de la sección.
                    ->schema([
                        // Lista desplegable para el estado (columna estado).
                        Select::make('estado')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Estado')
                            // Las opciones salen del enum, con su nombre legible (getLabel).
                            ->options(ClientStatusEnum::class)
                            // Al dar de alta, viene preseleccionado Inactivo.
                            ->default(ClientStatusEnum::Inactivo)
                            // No se puede guardar sin estado.
                            ->required(),
                        // Selector de fecha para el vencimiento de la cuota (opcional).
                        DatePicker::make('fecha_vencimiento')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Fecha de vencimiento'),
                    ]),
                // Sección 3: foto, certificado y datos de salud.
                Section::make('Salud y documentación')
                    // Muestra los campos en dos columnas.
                    ->columns(2)
                    // Campos de la sección.
                    ->schema([
                        // Subida de la foto del cliente (columna foto_path, opcional).
                        FileUpload::make('foto_path')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Foto')
                            // Solo acepta imágenes.
                            ->image()
                            // Muestra la vista previa redonda, tipo foto de perfil.
                            ->avatar()
                            // Guarda el archivo en el disco public (storage/app/public).
                            ->disk('public')
                            // Carpeta dentro del disco donde se guardan las fotos.
                            ->directory('clients/fotos')
                            // Tamaño máximo en KB (2 MB, el límite actual de PHP).
                            ->maxSize(2048),
                        // Subida del certificado de salud (columna certificado_salud_path, opcional).
                        FileUpload::make('certificado_salud_path')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Certificado de salud')
                            // Guarda el archivo en el disco public (storage/app/public).
                            ->disk('public')
                            // Carpeta dentro del disco donde se guardan los certificados.
                            ->directory('clients/certificados')
                            // Solo acepta PDF o cualquier tipo de imagen.
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            // Tamaño máximo en KB (2 MB, el límite actual de PHP).
                            ->maxSize(2048)
                            // Agrega un botón para abrir el archivo en otra pestaña.
                            ->openable()
                            // Agrega un botón para descargar el archivo.
                            ->downloadable(),
                        // Área de texto libre para dolencias u observaciones (opcional).
                        Textarea::make('observaciones_dolencias')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Observaciones / dolencias')
                            // Alto inicial de 4 renglones.
                            ->rows(4)
                            // Ocupa el ancho completo de la sección.
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Arma la tabla del listado de clientes del panel /admin.
     *
     * Precondiciones:
     * - Existe la tabla clients con las columnas de la migración create_clients_table.
     * - Para ver las fotos, el archivo debe existir en el disco "public" y estar hecho
     *   el php artisan storage:link.
     *
     * Postcondiciones:
     * - Lista los clientes activos (no dados de baja), ordenados por apellido.
     * - La búsqueda encuentra por DNI o apellido (no por nombre).
     * - Se puede editar un cliente, darlo de baja (baja lógica) o restaurarlo. No hay
     *   borrado definitivo, para conservar el historial.
     * - Si un cliente no tiene foto o el archivo no existe, la celda Foto queda vacía.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada con columnas, filtros y acciones.
     */
    public static function table(Table $table): Table
    {
        // Devuelve la tabla recibida con columnas, filtros y acciones cargadas.
        return $table
            // Usa el apellido como nombre de cada fila en mensajes y confirmaciones.
            ->recordTitleAttribute('apellido')
            // Columnas visibles, en el orden en que se muestran.
            ->columns([
                // Foto del cliente leída de la columna foto_path.
                ImageColumn::make('foto_path')
                    // Encabezado de la columna.
                    ->label('Foto')
                    // Busca el archivo en el disco public para armar la URL.
                    ->disk('public')
                    // Muestra la imagen recortada en círculo.
                    ->circular(),
                // Nombre del cliente.
                TextColumn::make('nombre')
                    // Encabezado de la columna.
                    ->label('Nombre')
                    // Permite ordenar haciendo clic en el encabezado.
                    ->sortable(),
                // Apellido del cliente.
                TextColumn::make('apellido')
                    // Encabezado de la columna.
                    ->label('Apellido')
                    // Incluye esta columna en el buscador de la tabla.
                    ->searchable()
                    // Permite ordenar haciendo clic en el encabezado.
                    ->sortable(),
                // DNI del cliente.
                TextColumn::make('dni')
                    // Encabezado de la columna.
                    ->label('DNI')
                    // Incluye esta columna en el buscador de la tabla.
                    ->searchable(),
                // Estado del cliente (caso de ClientStatusEnum gracias al cast del modelo).
                TextColumn::make('estado')
                    // Encabezado de la columna.
                    ->label('Estado')
                    // Lo muestra como etiqueta de color, con el nombre y color definidos en el enum.
                    ->badge(),
                // Fecha de vencimiento de la cuota.
                TextColumn::make('fecha_vencimiento')
                    // Encabezado de la columna.
                    ->label('Vencimiento')
                    // Formato día/mes/año.
                    ->date('d/m/Y')
                    // Permite ordenar haciendo clic en el encabezado.
                    ->sortable(),
            ])
            // Al entrar, ordena por apellido de la A a la Z.
            ->defaultSort('apellido')
            // Filtros disponibles en la tabla.
            ->filters([
                // Filtro para ver solo activos, solo dados de baja o todos.
                TrashedFilter::make(),
            ])
            // Acciones de cada fila.
            ->recordActions([
                // Cobro rápido: abre el modal para registrar un pago del cliente.
                RegisterPaymentAction::make(),
                // Botón para abrir el formulario de edición del cliente.
                EditAction::make(),
            ])
            // Acciones sobre varias filas seleccionadas.
            ->toolbarActions([
                // Agrupa las acciones masivas en un solo menú.
                BulkActionGroup::make([
                    // Da de baja (baja lógica) a los clientes seleccionados.
                    DeleteBulkAction::make(),
                    // Restaura a los clientes seleccionados que estaban dados de baja.
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Tablas relacionadas que se muestran al editar un cliente.
     *
     * Precondiciones:
     * - Client tiene las relaciones memberships() y payments().
     *
     * Postcondiciones:
     * - La edición del cliente muestra las pestañas "Membresías" y "Pagos", en ese orden.
     *
     * @return array<class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        // Lista de relation managers del recurso.
        return [
            // Membresías del cliente (alta, edición y baja).
            MembershipsRelationManager::class,
            // Historial de pagos del cliente (solo lectura).
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }

    /**
     * Consulta que usa Filament para encontrar el cliente de la URL (por ejemplo /admin/clients/5/edit).
     *
     * Precondiciones:
     * - El modelo Client usa SoftDeletes (columna deleted_at).
     *
     * Postcondiciones:
     * - Devuelve una consulta que incluye también a los clientes dados de baja, para poder
     *   abrirlos y restaurarlos desde el panel.
     * - No modifica datos.
     *
     * @return Builder<Client> Consulta base de clientes sin el filtro de baja lógica.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        // Parte de la consulta por defecto de Filament para el modelo Client.
        return parent::getRecordRouteBindingEloquentQuery()
            // Le quita el filtro global que oculta a los registros dados de baja.
            ->withoutGlobalScopes([
                // El filtro que agrega SoftDeletes ("deleted_at is null").
                SoftDeletingScope::class,
            ]);
    }
}
