<?php

namespace App\Filament\Resources\Clients;

use App\Enums\ClientStatusEnum;
use App\Filament\Resources\Clients\Actions\RegisterPaymentAction;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
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
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    // URL en castellano: /admin/clientes.
    protected static ?string $slug = 'clientes';

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
                            // Nombre en los mensajes de error (si no, Filament lo pone como "dNI").
                            ->validationAttribute('DNI')
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
                        // Vencimiento de la cuota: se calcula solo a partir de las membresías.
                        DatePicker::make('fecha_vencimiento')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Fecha de vencimiento')
                            // Solo lectura: lo actualiza el sistema al cambiar las membresías.
                            ->disabled()
                            // No se envía al guardar, así el formulario nunca pisa el valor calculado.
                            ->dehydrated(false)
                            // Explica de dónde sale el valor.
                            ->helperText('Se calcula solo: es el vencimiento más lejano de sus membresías.'),
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
                            // Guarda el archivo en el disco privado (storage/app/private), sin acceso público.
                            ->disk('local')
                            // Privado: el panel lo muestra con un enlace firmado que vence a los pocos minutos.
                            ->visibility('private')
                            // Carpeta dentro del disco donde se guardan las fotos.
                            ->directory('clients/fotos')
                            // Tamaño máximo en KB (2 MB, el límite actual de PHP).
                            ->maxSize(2048),
                        // Subida del certificado de salud (columna certificado_salud_path, opcional).
                        FileUpload::make('certificado_salud_path')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Certificado de salud')
                            // Guarda el archivo en el disco privado (storage/app/private), sin acceso público.
                            ->disk('local')
                            // Privado: abrirlo o descargarlo usa un enlace firmado que vence a los pocos minutos.
                            ->visibility('private')
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
     * Arma la ficha del cliente: un resumen de solo lectura pensado para recepción.
     *
     * Precondiciones:
     * - Se muestra en la página de vista de un cliente existente (ViewClient).
     * - Client tiene las relaciones currentMembership y latestPayment.
     *
     * Postcondiciones:
     * - Muestra, sin entrar a editar: quién es (foto, nombre, DNI), su estado y vencimiento,
     *   la membresía vigente con sus créditos, el último pago, y los datos de contacto y salud.
     * - Los datos que faltan se muestran con un texto aclaratorio ("Sin membresía vigente",
     *   "Todavía no registró pagos", etc.) en lugar de quedar en blanco.
     * - El vencimiento pasado se pinta en amarillo, nunca en rojo.
     * - El certificado de salud se abre con un enlace firmado que vence a los 30 minutos.
     * - No modifica datos.
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema La ficha configurada.
     */
    public static function infolist(Schema $schema): Schema
    {
        // Devuelve el esquema con las secciones de la ficha.
        return $schema
            // Tres columnas en pantallas grandes; una sola en el celular.
            ->columns(['default' => 1, 'lg' => 3])
            // Secciones de la ficha.
            ->components([
                // Sección 1: quién es y cómo está su cuota, a todo el ancho.
                Section::make()
                    // Ocupa las tres columnas.
                    ->columnSpanFull()
                    // Cinco datos en fila en pantallas grandes; de a dos en el celular.
                    ->columns(['default' => 2, 'lg' => 5])
                    // Datos de la sección.
                    ->schema([
                        // Foto del cliente (solo si tiene).
                        ImageEntry::make('foto_path')
                            // Sin etiqueta: la foto se explica sola.
                            ->hiddenLabel()
                            // La busca en el disco privado.
                            ->disk('local')
                            // Usa un enlace firmado temporal.
                            ->visibility('private')
                            // Recortada en círculo.
                            ->circular()
                            // Tamaño en píxeles.
                            ->imageSize(72)
                            // Si no tiene foto, no ocupa lugar.
                            ->visible(fn (Client $record): bool => filled($record->foto_path)),
                        // Nombre completo, destacado.
                        TextEntry::make('nombre_completo')
                            // Etiqueta del dato.
                            ->label('Cliente')
                            // Nombre y apellido juntos.
                            ->state(fn (Client $record): string => "{$record->nombre} {$record->apellido}")
                            // Letra grande.
                            ->size(TextSize::Large)
                            // En negrita.
                            ->weight(FontWeight::Bold),
                        // Documento.
                        TextEntry::make('dni')
                            // Etiqueta del dato.
                            ->label('DNI'),
                        // Estado del cliente, con el color definido en el enum.
                        TextEntry::make('estado')
                            // Etiqueta del dato.
                            ->label('Estado')
                            // Como etiqueta de color.
                            ->badge()
                            // Debajo, qué significa el estado (por ejemplo "Debe abonar").
                            ->belowContent(fn (Client $record): string => $record->estado->getDescription()),
                        // Vencimiento de la cuota.
                        TextEntry::make('fecha_vencimiento')
                            // Etiqueta del dato.
                            ->label('Vencimiento')
                            // Formato día/mes/año.
                            ->date('d/m/Y')
                            // Texto cuando no tiene vencimiento.
                            ->placeholder('Sin vencimiento')
                            // Amarillo suave si ya venció.
                            ->color(fn (Client $record): ?string => $record->fecha_vencimiento?->isBefore(today()) ? 'warning' : null)
                            // Debajo, cuánto falta o hace cuánto venció (solo si está cerca o ya pasó).
                            ->belowContent(fn (Client $record): ?string => self::getExpirationHint($record)),
                    ]),
                // Sección 2: la membresía vigente.
                Section::make('Membresía vigente')
                    // Ícono de la sección.
                    ->icon(Heroicon::OutlinedIdentification)
                    // Dos datos por fila.
                    ->columns(2)
                    // Datos de la sección.
                    ->schema([
                        // Plan contratado.
                        TextEntry::make('currentMembership.plan.nombre')
                            // Etiqueta del dato.
                            ->label('Plan')
                            // Texto cuando no tiene ninguna membresía abierta.
                            ->placeholder('Sin membresía vigente'),
                        // Estado de acceso de la membresía.
                        TextEntry::make('currentMembership.estado')
                            // Etiqueta del dato.
                            ->label('Acceso')
                            // Como etiqueta de color.
                            ->badge()
                            // Texto cuando no hay membresía.
                            ->placeholder('—'),
                        // Vencimiento de la membresía.
                        TextEntry::make('currentMembership.fecha_vencimiento')
                            // Etiqueta del dato.
                            ->label('Vence')
                            // Formato día/mes/año.
                            ->date('d/m/Y')
                            // Texto cuando no hay membresía.
                            ->placeholder('—'),
                        // Créditos que le quedan.
                        TextEntry::make('creditos')
                            // Etiqueta del dato.
                            ->label('Créditos')
                            // "8 de 12", o "Libre" si el plan es libre.
                            ->state(fn (Client $record): ?string => self::getCreditsSummary($record))
                            // Texto cuando no hay membresía.
                            ->placeholder('—'),
                    ]),
                // Sección 3: el último pago.
                Section::make('Último pago')
                    // Ícono de la sección.
                    ->icon(Heroicon::OutlinedBanknotes)
                    // Dos datos por fila.
                    ->columns(2)
                    // Datos de la sección.
                    ->schema([
                        // Cuándo pagó.
                        TextEntry::make('latestPayment.fecha_pago')
                            // Etiqueta del dato.
                            ->label('Fecha')
                            // Formato día/mes/año con hora.
                            ->dateTime('d/m/Y H:i')
                            // Texto cuando nunca pagó.
                            ->placeholder('Todavía no registró pagos'),
                        // Cuánto pagó.
                        TextEntry::make('latestPayment.monto')
                            // Etiqueta del dato.
                            ->label('Monto')
                            // En pesos argentinos.
                            ->money('ARS', locale: 'es_AR')
                            // Texto cuando nunca pagó.
                            ->placeholder('—'),
                        // Cómo pagó.
                        TextEntry::make('latestPayment.metodo_pago')
                            // Etiqueta del dato.
                            ->label('Medio')
                            // Como etiqueta de color.
                            ->badge()
                            // Texto cuando nunca pagó.
                            ->placeholder('—'),
                        // Quién lo cobró.
                        TextEntry::make('latestPayment.registradoPor.name')
                            // Etiqueta del dato.
                            ->label('Cobró')
                            // Texto cuando nunca pagó.
                            ->placeholder('—'),
                    ]),
                // Sección 4: contacto y salud.
                Section::make('Contacto y salud')
                    // Ícono de la sección.
                    ->icon(Heroicon::OutlinedHeart)
                    // Dos datos por fila.
                    ->columns(2)
                    // Datos de la sección.
                    ->schema([
                        // Celular, con enlace para llamar desde el teléfono.
                        TextEntry::make('cel')
                            // Etiqueta del dato.
                            ->label('Celular')
                            // Ícono de teléfono.
                            ->icon(Heroicon::OutlinedPhone)
                            // Al tocarlo, abre la app de llamadas.
                            ->url(fn (Client $record): string => "tel:{$record->cel}"),
                        // Email, con enlace para escribirle.
                        TextEntry::make('email')
                            // Etiqueta del dato.
                            ->label('Email')
                            // Ícono de sobre.
                            ->icon(Heroicon::OutlinedEnvelope)
                            // Al tocarlo, abre el programa de correo.
                            ->url(fn (Client $record): string => "mailto:{$record->email}"),
                        // Fecha de nacimiento con la edad.
                        TextEntry::make('fecha_nacimiento')
                            // Etiqueta del dato.
                            ->label('Nacimiento')
                            // "14/03/1995 (31 años)".
                            ->formatStateUsing(fn (Client $record): string => $record->fecha_nacimiento->format('d/m/Y').' ('.$record->fecha_nacimiento->age.' años)'),
                        // Certificado de salud: enlace para abrirlo, si está cargado.
                        TextEntry::make('certificado_salud_path')
                            // Etiqueta del dato.
                            ->label('Certificado de salud')
                            // En vez de la ruta del archivo, muestra un texto para abrirlo.
                            ->formatStateUsing(fn (): string => 'Ver certificado')
                            // Ícono de documento.
                            ->icon(Heroicon::OutlinedDocumentText)
                            // Enlace firmado al archivo privado, válido por 30 minutos.
                            ->url(fn (Client $record): ?string => filled($record->certificado_salud_path)
                                // Solo si hay archivo.
                                ? Storage::disk('local')->temporaryUrl($record->certificado_salud_path, now()->addMinutes(30))
                                // Sin archivo, sin enlace.
                                : null)
                            // Lo abre en otra pestaña.
                            ->openUrlInNewTab()
                            // Texto cuando no está cargado.
                            ->placeholder('No cargado'),
                        // Dolencias u observaciones, a todo el ancho.
                        TextEntry::make('observaciones_dolencias')
                            // Etiqueta del dato.
                            ->label('Observaciones / dolencias')
                            // Ocupa las dos columnas.
                            ->columnSpanFull()
                            // Texto cuando no hay observaciones.
                            ->placeholder('Sin observaciones'),
                    ]),
            ]);
    }

    /**
     * Arma el texto de créditos de la membresía vigente del cliente.
     *
     * Precondiciones:
     * - $client viene de la base; puede no tener membresía vigente.
     *
     * Postcondiciones:
     * - Sin membresía vigente: devuelve null.
     * - Si el plan es libre (otorga 999 créditos o más): devuelve "Libre".
     * - En otro caso: "disponibles de otorgados", por ejemplo "8 de 12".
     * - No modifica datos.
     *
     * @return string|null Texto de créditos, o null si no aplica.
     */
    private static function getCreditsSummary(Client $client): ?string
    {
        // Membresía vigente del cliente (null si no tiene).
        $membership = $client->currentMembership;

        // Sin membresía no hay créditos que mostrar.
        if ($membership === null) {
            // No muestra texto.
            return null;
        }

        // Créditos que otorga el plan (999 es la convención para "libre").
        $granted = $membership->plan->creditos_otorgados;

        // Plan libre: no tiene sentido contar; si no, "disponibles de otorgados".
        return $granted >= 999 ? 'Libre' : "{$membership->creditos_disponibles} de {$granted}";
    }

    /**
     * Arma la tabla del listado de clientes del panel /admin.
     *
     * Precondiciones:
     * - Existe la tabla clients con las columnas de la migración create_clients_table.
     * - Para ver las fotos, el archivo debe existir en el disco privado "local".
     *
     * Postcondiciones:
     * - Lista los clientes activos (no dados de baja), ordenados por apellido.
     * - La búsqueda encuentra por nombre, apellido o DNI (por ejemplo "juan perez").
     * - El vencimiento muestra debajo cuánto falta o hace cuánto venció cuando está cerca
     *   (7 días o menos) o ya pasó; si ya pasó, se pinta en amarillo (nunca en rojo).
     * - Hacer clic en una fila abre la ficha del cliente; "Editar" lleva al formulario.
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
            // Al hacer clic en una fila se abre la ficha del cliente (no el formulario de edición).
            ->recordUrl(fn (Client $record): string => self::getUrl('view', ['record' => $record]))
            // Columnas visibles, en el orden en que se muestran.
            ->columns([
                // Foto del cliente leída de la columna foto_path.
                ImageColumn::make('foto_path')
                    // Encabezado de la columna.
                    ->label('Foto')
                    // Busca la foto en el disco privado.
                    ->disk('local')
                    // Arma un enlace firmado temporal en lugar de una URL pública.
                    ->visibility('private')
                    // Muestra la imagen recortada en círculo.
                    ->circular(),
                // Nombre del cliente.
                TextColumn::make('nombre')
                    // Encabezado de la columna.
                    ->label('Nombre')
                    // Incluye esta columna en el buscador de la tabla.
                    ->searchable()
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
                    // Debajo de la fecha, aclara cuánto falta o hace cuánto venció (solo si está cerca o ya pasó).
                    ->description(fn (Client $record): ?string => self::getExpirationHint($record))
                    // Si ya venció, la pinta en amarillo suave; si no, queda con el color normal.
                    ->color(fn (Client $record): ?string => $record->fecha_vencimiento?->isBefore(today()) ? 'warning' : null)
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
                RegisterPaymentAction::make()
                    // Texto corto para la fila.
                    ->label('Cobrar'),
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
     * Arma el texto que acompaña al vencimiento del cliente (tabla de clientes, ficha e inicio).
     *
     * Precondiciones:
     * - $client viene de la base; fecha_vencimiento puede estar vacía.
     *
     * Postcondiciones:
     * - Sin fecha, o si faltan más de 7 días: devuelve null (no se muestra nada).
     * - Si vence dentro de los próximos 7 días: "Vence hoy", "Vence mañana" o "Vence en N días".
     * - Si ya venció: "Venció ayer" o "Venció hace N días".
     * - No modifica datos.
     *
     * @return string|null Texto de ayuda, o null si no hace falta.
     */
    public static function getExpirationHint(Client $client): ?string
    {
        // Sin fecha de vencimiento no hay nada que aclarar.
        if ($client->fecha_vencimiento === null) {
            // No muestra texto.
            return null;
        }

        // Días desde hoy hasta el vencimiento: negativo si ya pasó, 0 si es hoy.
        $days = (int) today()->diffInDays($client->fecha_vencimiento->copy()->startOfDay(), absolute: false);

        // Elige el texto según la distancia.
        return match (true) {
            // Falta más de una semana: no hace falta avisar.
            $days > 7 => null,
            // Faltan entre 2 y 7 días.
            $days > 1 => "Vence en {$days} días",
            // Falta un día.
            $days === 1 => 'Vence mañana',
            // Es hoy.
            $days === 0 => 'Vence hoy',
            // Pasó ayer.
            $days === -1 => 'Venció ayer',
            // Pasó hace más de un día.
            default => 'Venció hace '.abs($days).' días',
        };
    }

    /**
     * Columnas por las que el buscador general (barra superior del panel) encuentra clientes.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Desde cualquier pantalla se puede buscar un cliente por nombre, apellido o DNI.
     *
     * @return array<string> Nombres de columnas de la tabla clients.
     */
    public static function getGloballySearchableAttributes(): array
    {
        // Mismas columnas que el buscador de la tabla.
        return ['nombre', 'apellido', 'dni'];
    }

    /**
     * Título de cada resultado del buscador general.
     *
     * Precondiciones:
     * - $record es un Client.
     *
     * Postcondiciones:
     * - Devuelve "Apellido, Nombre" (por ejemplo "Pérez, Juan").
     *
     * @param  Model  $record  Cliente encontrado.
     * @return string Texto principal del resultado.
     */
    public static function getGlobalSearchResultTitle(Model $record): string
    {
        // Apellido primero, como se ordena la tabla.
        return "{$record->apellido}, {$record->nombre}";
    }

    /**
     * Datos que acompañan a cada resultado del buscador general.
     *
     * Precondiciones:
     * - $record es un Client.
     *
     * Postcondiciones:
     * - Devuelve el DNI y el estado, y el vencimiento si tiene, para reconocer al cliente y su
     *   situación sin abrirlo.
     *
     * @param  Model  $record  Cliente encontrado.
     * @return array<string, string> Pares "etiqueta => valor".
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        // Devuelve solo los datos que tienen valor (array_filter saca el vencimiento vacío).
        return array_filter([
            // Documento, para distinguir homónimos.
            'DNI' => $record->dni,
            // Estado actual (Entrenando, En pausa, etc.).
            'Estado' => $record->estado->getLabel(),
            // Vencimiento de la cuota, si tiene.
            'Vence' => $record->fecha_vencimiento?->format('d/m/Y'),
        ]);
    }

    /**
     * Tablas relacionadas que se muestran en la ficha de un cliente.
     *
     * Precondiciones:
     * - Client tiene las relaciones memberships() y payments().
     *
     * Postcondiciones:
     * - La ficha del cliente muestra las pestañas "Membresías" y "Pagos", en ese orden.
     *   (La página de edición no las muestra: ver EditClient::getRelationManagers().)
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
            'create' => CreateClient::route('/crear'),
            'view' => ViewClient::route('/{record}'),
            'edit' => EditClient::route('/{record}/editar'),
        ];
    }

    /**
     * Consulta que usa Filament para encontrar el cliente de la URL (por ejemplo /admin/clientes/5/editar).
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
