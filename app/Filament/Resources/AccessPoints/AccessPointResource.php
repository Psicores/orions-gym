<?php

namespace App\Filament\Resources\AccessPoints;

use App\Filament\Pages\CheckInQr;
use App\Filament\Resources\AccessPoints\Pages\ManageAccessPoints;
use App\Models\AccessPoint;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * "QR de ingreso" del panel: los puntos de acceso del gimnasio, cada uno con su QR para imprimir.
 *
 * Apagar un punto o cambiarle el código invalida su QR impreso. No se borran: los ingresos
 * registrados guardan por cuál entró cada socio.
 */
class AccessPointResource extends Resource
{
    protected static ?string $model = AccessPoint::class;

    // URL en castellano: /admin/qr-de-ingreso.
    protected static ?string $slug = 'qr-de-ingreso';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'QR de ingreso';

    // En el menú va después de Clientes (que usa el orden 1).
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'punto de acceso';

    protected static ?string $pluralModelLabel = 'puntos de acceso';

    protected static ?string $recordTitleAttribute = 'nombre';

    /**
     * Formulario de alta y edición: solo el nombre (el código se genera solo).
     *
     * Precondiciones:
     * - Existe la tabla access_points.
     *
     * Postcondiciones:
     * - El nombre es obligatorio y de hasta 80 caracteres.
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El esquema con el campo.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Nombre del lugar.
                TextInput::make('nombre')
                    // Texto sobre el campo.
                    ->label('Nombre')
                    // Ejemplo.
                    ->placeholder('Ej.: Recepción')
                    // Sale en el cartel impreso.
                    ->helperText('Sale impreso debajo del QR.')
                    // No se puede guardar vacío.
                    ->required()
                    // Igual que la columna.
                    ->maxLength(80),
            ]);
    }

    /**
     * Lista de puntos de acceso.
     *
     * Precondiciones:
     * - Existe la tabla access_points.
     *
     * Postcondiciones:
     * - Muestra nombre, si está prendido (se cambia desde la lista) y los ingresos de hoy.
     * - Cada punto se puede imprimir, renombrar o recibir un código nuevo (con confirmación).
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            // Cuenta los ingresos de hoy de cada punto en la misma consulta.
            ->modifyQueryUsing(fn ($query) => $query->withCount(['attendances as ingresos_hoy' => fn ($attendances) => $attendances->whereDate('fecha_hora', today())]))
            // Columnas.
            ->columns([
                // Nombre.
                TextColumn::make('nombre')
                    // Encabezado.
                    ->label('Nombre')
                    // Ordenable.
                    ->sortable(),
                // Prendido o apagado; apagado, el QR deja de servir.
                ToggleColumn::make('activo')
                    // Encabezado.
                    ->label('Activo'),
                // Ingresos de hoy por este punto.
                TextColumn::make('ingresos_hoy')
                    // Encabezado.
                    ->label('Ingresos hoy')
                    // Como número.
                    ->numeric(),
            ])
            // Por orden de creación.
            ->defaultSort('id')
            // Acciones de cada fila.
            ->recordActions([
                // Abre el cartel para imprimir.
                Action::make('imprimir')
                    // Texto del botón.
                    ->label('Imprimir QR')
                    // Ícono.
                    ->icon(Heroicon::OutlinedPrinter)
                    // Solo si está prendido.
                    ->visible(fn (AccessPoint $record): bool => $record->activo)
                    // Cartel de este punto.
                    ->url(fn (AccessPoint $record): string => CheckInQr::getUrl(['punto' => $record->id])),
                // Renombrar.
                EditAction::make()
                    // Modal angosto.
                    ->modalWidth(Width::Medium)
                    // Botones a la derecha.
                    ->modalFooterActionsAlignment(Alignment::End)
                    // Cabecera en el color principal.
                    ->extraModalWindowAttributes(['class' => 'orions-modal']),
                // Código nuevo: el QR impreso deja de servir.
                Action::make('cambiarCodigo')
                    // Texto del botón.
                    ->label('Cambiar código')
                    // Ícono.
                    ->icon(Heroicon::OutlinedArrowPath)
                    // En ámbar: invalida lo impreso.
                    ->color('warning')
                    // Pide confirmación.
                    ->requiresConfirmation()
                    // Título del aviso.
                    ->modalHeading('¿Cambiar el código de este QR?')
                    // Qué pasa.
                    ->modalDescription('El QR impreso deja de servir al instante. Usalo si alguien sacó una foto del cartel y lo usa desde afuera. Después imprimí el nuevo.')
                    // Botón de confirmar.
                    ->modalSubmitActionLabel('Cambiar código')
                    // Genera el código y avisa.
                    ->action(function (AccessPoint $record): void {
                        // Código nuevo.
                        $record->regenerateToken();
                        // Aviso.
                        Notification::make()->title('Código cambiado: imprimí el QR nuevo')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            // Única página: la lista; crear y renombrar se abren en ventanas modales.
            'index' => ManageAccessPoints::route('/'),
        ];
    }
}
