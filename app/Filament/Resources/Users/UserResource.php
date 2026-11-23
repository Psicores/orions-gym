<?php

namespace App\Filament\Resources\Users;

use App\Enums\SiteSectionEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * Cuentas del personal del gimnasio (administradores y profes) que entran al panel.
 *
 * Solo la ven los administradores. Una cuenta nueva queda con el email verificado y entra
 * directamente por /ingresar con la contraseña que le cargaron.
 */
class UserResource extends Resource
{
    // Solo para administradores.
    use AdminOnly;

    protected static ?string $model = User::class;

    // URL en castellano: /admin/personal.
    protected static ?string $slug = 'personal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    // Va en el grupo "Admin del sitio", al final.
    protected static string|UnitEnum|null $navigationGroup = SiteSectionEnum::NAVIGATION_GROUP;

    // Después de planes (1), anuncios (2) y las secciones del sitio (desde 3).
    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Personal';

    protected static ?string $modelLabel = 'cuenta del personal';

    protected static ?string $pluralModelLabel = 'personal';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Indica si $record es la cuenta de quien está usando el panel.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve true si $record es la cuenta con sesión. No modifica nada.
     *
     * @param  User|null  $record  Cuenta de la fila o del formulario (null al crear).
     * @return bool true si es la propia cuenta.
     */
    public static function isOwnAccount(?User $record): bool
    {
        // Compara con la cuenta con sesión (al crear no hay registro).
        return $record !== null && $record->is(Auth::guard('web')->user());
    }

    /**
     * Arma el formulario de alta y edición de una cuenta del personal (en una ventana modal).
     *
     * Precondiciones:
     * - Existe la tabla users con la columna role.
     *
     * Postcondiciones:
     * - Nombre, email (único, también entre las cuentas dadas de baja) y rol son obligatorios.
     * - La contraseña es obligatoria al crear (8 caracteres o más, con letras y números). Al
     *   editar es opcional: vacía, no se cambia.
     * - El administrador no puede cambiarse su propio rol, así el panel nunca se queda sin
     *   administrador.
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El esquema con los campos.
     */
    public static function form(Schema $schema): Schema
    {
        // Devuelve el esquema con los campos, en el orden en que se muestran.
        return $schema
            ->components([
                // Nombre a mostrar.
                TextInput::make('name')
                    // Texto sobre el campo.
                    ->label('Nombre')
                    // No se puede guardar vacío.
                    ->required()
                    // Igual que la columna.
                    ->maxLength(255),
                // Email con el que entra por /ingresar.
                TextInput::make('email')
                    // Texto sobre el campo.
                    ->label('Email')
                    // Valida el formato.
                    ->email()
                    // No se puede guardar vacío.
                    ->required()
                    // Igual que la columna.
                    ->maxLength(255)
                    // Ningún otro usuario (ni dado de baja) puede tenerlo; al editar ignora la propia cuenta.
                    ->unique(ignoreRecord: true),
                // Rol de la cuenta.
                Select::make('role')
                    // Texto sobre el campo.
                    ->label('Rol')
                    // Opciones del enum (Administrador, Profe).
                    ->options(UserRoleEnum::class)
                    // Por defecto, profe.
                    ->default(UserRoleEnum::Profe)
                    // Sin opción vacía.
                    ->selectablePlaceholder(false)
                    // No se puede guardar vacío.
                    ->required()
                    // Nadie se cambia su propio rol (un campo deshabilitado no se guarda).
                    ->disabled(fn (?User $record): bool => static::isOwnAccount($record))
                    // Explica por qué está bloqueado.
                    ->helperText(fn (?User $record): ?string => static::isOwnAccount($record) ? 'No podés cambiar tu propio rol.' : null),
                // Contraseña de ingreso.
                TextInput::make('password')
                    // Texto sobre el campo.
                    ->label(fn (?User $record): string => $record === null ? 'Contraseña inicial' : 'Contraseña nueva')
                    // Oculta lo que se escribe.
                    ->password()
                    // Botón para verla y poder dictarla.
                    ->revealable()
                    // Obligatoria solo al crear.
                    ->required(fn (?User $record): bool => $record === null)
                    // Mínimo 8, con letras y números (igual que la de los socios).
                    ->rule(Password::min(8)->letters()->numbers())
                    // Igual que la columna.
                    ->maxLength(255)
                    // Si quedó vacía, no se guarda (conserva la anterior).
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    // Qué hacer con ella.
                    ->helperText(fn (?User $record): string => $record === null
                        ? 'Pasásela a la persona: entra por /ingresar con su email y esta contraseña.'
                        : 'Dejala vacía para no cambiarla.'),
            ]);
    }

    /**
     * Arma el listado de cuentas del personal.
     *
     * Precondiciones:
     * - Existe la tabla users con las columnas role y deleted_at.
     *
     * Postcondiciones:
     * - Lista las cuentas activas por nombre, con email y rol; el filtro muestra las dadas de baja.
     * - Cada cuenta se puede editar, dar de baja o restaurar. Nadie puede darse de baja a sí mismo.
     * - No hay borrado definitivo: los pagos guardan quién los cobró.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada.
     */
    public static function table(Table $table): Table
    {
        // Devuelve la tabla con columnas, filtros y acciones.
        return $table
            // Columnas visibles.
            ->columns([
                // Nombre.
                TextColumn::make('name')
                    // Encabezado.
                    ->label('Nombre')
                    // Entra en el buscador.
                    ->searchable()
                    // Ordenable.
                    ->sortable(),
                // Email.
                TextColumn::make('email')
                    // Encabezado.
                    ->label('Email')
                    // Entra en el buscador.
                    ->searchable(),
                // Rol, como etiqueta de color.
                TextColumn::make('role')
                    // Encabezado.
                    ->label('Rol')
                    // Etiqueta con el color del enum.
                    ->badge(),
            ])
            // Al entrar, por nombre.
            ->defaultSort('name')
            // Activas, dadas de baja o todas.
            ->filters([
                // Filtro de bajas.
                TrashedFilter::make(),
            ])
            // Acciones de cada fila.
            ->recordActions([
                // Edición en una ventana modal.
                EditAction::make()
                    // Ícono del título.
                    ->modalIcon(Heroicon::OutlinedPencilSquare)
                    // Modal angosto.
                    ->modalWidth(Width::Large)
                    // Botones a la derecha.
                    ->modalFooterActionsAlignment(Alignment::End)
                    // Cabecera en el color principal.
                    ->extraModalWindowAttributes(['class' => 'orions-modal']),
                // Baja lógica: no puede volver a entrar.
                DeleteAction::make()
                    // Texto del botón.
                    ->label('Dar de baja')
                    // Aclara qué pasa.
                    ->modalDescription('No va a poder entrar al panel. Sigue figurando en los pagos que cobró y la podés restaurar.')
                    // Nadie se da de baja a sí mismo.
                    ->hidden(fn (User $record): bool => static::isOwnAccount($record)),
                // Vuelve a habilitar una cuenta dada de baja.
                RestoreAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            // Única página: el listado; crear y editar se abren en ventanas modales.
            'index' => ManageUsers::route('/'),
        ];
    }
}
