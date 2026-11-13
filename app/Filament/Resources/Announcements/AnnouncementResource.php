<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\SiteSectionEnum;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    // URL en castellano: /admin/anuncios.
    protected static ?string $slug = 'anuncios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    // Va en el grupo "Admin del sitio" del menú, junto a lo demás que ven los socios y el público.
    protected static string|UnitEnum|null $navigationGroup = SiteSectionEnum::NAVIGATION_GROUP;

    // Segundo dentro del grupo, después de Planes (1); las secciones del sitio siguen desde 3 (ver ComingSoon).
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'anuncio';

    protected static ?string $pluralModelLabel = 'anuncios';

    protected static ?string $recordTitleAttribute = 'titulo';

    /**
     * Arma el formulario de alta y edición de anuncios del panel /admin.
     * Se muestra dentro de una ventana modal sobre el listado (no hay páginas aparte).
     *
     * Precondiciones:
     * - Existe la tabla announcements con las columnas de la migración create_announcements_table.
     * - El disco "public" está configurado y se corrió php artisan storage:link, para que la
     *   imagen se pueda ver desde el navegador.
     *
     * Postcondiciones:
     * - Solo el título es obligatorio: el anuncio puede llevar texto, imagen, las dos cosas o ninguna.
     * - La imagen se guarda en el disco "public", carpeta "announcements", y pesa hasta 2 MB.
     * - "Activo" viene encendido. Las fechas son opcionales; si se cargan las dos, el fin no
     *   puede ser anterior al inicio.
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El esquema con los campos del anuncio.
     */
    public static function form(Schema $schema): Schema
    {
        // Devuelve el esquema recibido con los campos del formulario cargados.
        return $schema
            // Un solo componente: la grilla que ordena los campos.
            ->components([
                // Grilla de dos columnas (una sola en el celular).
                Grid::make(['default' => 1, 'sm' => 2])
                    // Ocupa todo el ancho del modal.
                    ->columnSpanFull()
                    // Campos, en el orden en que se muestran.
                    ->schema([
                        // Título del anuncio.
                        TextInput::make('titulo')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Título')
                            // Ejemplo dentro del campo vacío.
                            ->placeholder('Ej.: Feriado del lunes: abrimos de 9 a 13')
                            // No se puede guardar vacío.
                            ->required()
                            // Máximo de caracteres, igual al de la columna string.
                            ->maxLength(255)
                            // Ocupa las dos columnas.
                            ->columnSpanFull(),
                        // Texto del anuncio, con formato (opcional).
                        RichEditor::make('contenido')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Contenido')
                            // Solo los botones de formato que hacen falta para un aviso corto.
                            ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList', 'undo', 'redo'])
                            // Aclara que se puede dejar vacío.
                            ->helperText('Opcional. Dejalo vacío si el anuncio es solo una imagen.')
                            // Ocupa las dos columnas.
                            ->columnSpanFull(),
                        // Imagen o banner del anuncio (opcional).
                        FileUpload::make('imagen_path')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Imagen')
                            // Solo acepta imágenes.
                            ->image()
                            // Disco público: los socios la ven desde su portal.
                            ->disk('public')
                            // Carpeta dentro del disco.
                            ->directory('announcements')
                            // Tamaño máximo en KB (2 MB, el límite actual de PHP).
                            ->maxSize(2048)
                            // Aclara que se puede dejar vacío.
                            ->helperText('Opcional. Dejala vacía si el anuncio es solo texto.')
                            // Ocupa las dos columnas.
                            ->columnSpanFull(),
                        // Desde cuándo se muestra (opcional).
                        DateTimePicker::make('starts_at')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Mostrar desde')
                            // Sin segundos: alcanza con día, hora y minutos.
                            ->seconds(false)
                            // Explica qué pasa si queda vacío.
                            ->helperText('Vacío: se muestra desde ahora.'),
                        // Hasta cuándo se muestra (opcional): la baja automática.
                        DateTimePicker::make('ends_at')
                            // Texto que ve el usuario sobre el campo.
                            ->label('Mostrar hasta')
                            // Sin segundos: alcanza con día, hora y minutos.
                            ->seconds(false)
                            // No puede terminar antes de empezar.
                            ->afterOrEqual('starts_at')
                            // Explica qué pasa si queda vacío.
                            ->helperText('Vacío: se muestra hasta que lo apagues.'),
                        // Interruptor para darlo de baja a mano.
                        Toggle::make('is_active')
                            // Texto que ve el usuario junto al interruptor.
                            ->label('Activo')
                            // Al crear viene encendido.
                            ->default(true)
                            // Explica para qué sirve.
                            ->helperText('Apagalo para dejar de mostrarlo sin borrarlo.')
                            // Ocupa las dos columnas.
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Arma la tabla del listado de anuncios del panel /admin.
     *
     * Precondiciones:
     * - Existe la tabla announcements con las columnas de la migración create_announcements_table.
     *
     * Postcondiciones:
     * - Lista todos los anuncios, del más nuevo al más viejo, con su imagen en miniatura
     *   cuadrada, el título, la situación actual (Visible, Programado, Finalizado o Apagado),
     *   el interruptor "Activo" y las dos fechas.
     * - El interruptor apaga o enciende el anuncio directamente desde la tabla, sin abrir nada.
     * - Se puede buscar por título, filtrar por activos o apagados, editar en una ventana modal
     *   y borrar (el borrado es definitivo y también olvida quiénes lo habían cerrado).
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada con columnas y acciones.
     */
    public static function table(Table $table): Table
    {
        // Devuelve la tabla recibida con columnas, filtros y acciones cargados.
        return $table
            // Título de cada registro (lo usan los modales de editar y borrar).
            ->recordTitleAttribute('titulo')
            // Columnas, de izquierda a derecha.
            ->columns([
                // Miniatura de la imagen (vacía si el anuncio es solo texto).
                ImageColumn::make('imagen_path')
                    // Encabezado de la columna.
                    ->label('Imagen')
                    // Las imágenes están en el disco público.
                    ->disk('public')
                    // Cuadrada y chica.
                    ->square()
                    ->imageSize(44),
                // Título del anuncio.
                TextColumn::make('titulo')
                    // Encabezado de la columna.
                    ->label('Título')
                    // Permite buscar por título.
                    ->searchable()
                    // Permite ordenar por título.
                    ->sortable()
                    // Si es muy largo, lo corta.
                    ->limit(50),
                // Situación actual: combina el interruptor con las fechas.
                TextColumn::make('situacion')
                    // Encabezado de la columna.
                    ->label('Situación')
                    // No es una columna de la base: la calcula el modelo.
                    ->state(fn (Announcement $record): string => $record->situacion())
                    // Se muestra como etiqueta de color.
                    ->badge()
                    // Verde si se ve, celeste si está programado, gris si ya no se muestra.
                    ->color(fn (string $state): string => match ($state) {
                        'Visible' => 'success',
                        'Programado' => 'info',
                        default => 'gray',
                    }),
                // Interruptor para apagarlo o encenderlo rápido.
                ToggleColumn::make('is_active')
                    // Encabezado de la columna.
                    ->label('Activo'),
                // Desde cuándo se muestra.
                TextColumn::make('starts_at')
                    // Encabezado de la columna.
                    ->label('Desde')
                    // Día y hora.
                    ->dateTime('d/m/Y H:i')
                    // Texto cuando no tiene fecha de inicio.
                    ->placeholder('Sin fecha')
                    // Permite ordenar por esta fecha.
                    ->sortable(),
                // Hasta cuándo se muestra.
                TextColumn::make('ends_at')
                    // Encabezado de la columna.
                    ->label('Hasta')
                    // Día y hora.
                    ->dateTime('d/m/Y H:i')
                    // Texto cuando no tiene fecha de fin.
                    ->placeholder('Sin fecha')
                    // Permite ordenar por esta fecha.
                    ->sortable(),
            ])
            // Los más nuevos primero.
            ->defaultSort('created_at', 'desc')
            // Filtros arriba de la tabla.
            ->filters([
                // Activos, apagados o todos.
                TernaryFilter::make('is_active')
                    // Texto del filtro.
                    ->label('Activo'),
            ])
            // Acciones de cada fila.
            ->recordActions([
                // Editar en una ventana modal con la cabecera en el color principal.
                EditAction::make()
                    // Ícono que acompaña al título del modal.
                    ->modalIcon(Heroicon::OutlinedPencilSquare)
                    // Modal ancho: tiene editor de texto e imagen.
                    ->modalWidth(Width::ThreeExtraLarge)
                    // Botones del pie alineados a la derecha.
                    ->modalFooterActionsAlignment(Alignment::End)
                    // Cabecera en el color principal (ver resources/views/filament/admin/styles.blade.php).
                    ->extraModalWindowAttributes(['class' => 'orions-modal']),
                // Borrado definitivo.
                DeleteAction::make(),
            ])
            // Acciones sobre varias filas seleccionadas.
            ->toolbarActions([
                // Agrupadas en un menú.
                BulkActionGroup::make([
                    // Borra los seleccionados.
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Páginas del recurso: una sola, el listado (crear y editar se hacen en modales).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve solo la página "index" en /admin/anuncios.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        // Única página del recurso.
        return [
            'index' => ManageAnnouncements::route('/'),
        ];
    }
}
