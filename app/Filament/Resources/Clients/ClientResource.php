<?php

namespace App\Filament\Resources\Clients;

use App\Enums\ClientStatusEnum;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('apellido')
                            ->label('Apellido')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('dni')
                            ->label('DNI')
                            ->required()
                            ->maxLength(255)
                            ->unique(),
                        DatePicker::make('fecha_nacimiento')
                            ->label('Fecha de nacimiento')
                            ->required()
                            ->maxDate(now()),
                        TextInput::make('cel')
                            ->label('Celular')
                            ->tel()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(),
                    ]),
                Section::make('Cuota')
                    ->columns(2)
                    ->schema([
                        Select::make('estado')
                            ->label('Estado')
                            ->options(ClientStatusEnum::class)
                            ->default(ClientStatusEnum::Inactivo)
                            ->required(),
                        DatePicker::make('fecha_vencimiento')
                            ->label('Fecha de vencimiento'),
                    ]),
                Section::make('Salud y documentación')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('foto_path')
                            ->label('Foto')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('clients/fotos')
                            ->maxSize(2048),
                        FileUpload::make('certificado_salud_path')
                            ->label('Certificado de salud')
                            ->disk('public')
                            ->directory('clients/certificados')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(2048)
                            ->openable()
                            ->downloadable(),
                        Textarea::make('observaciones_dolencias')
                            ->label('Observaciones / dolencias')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('apellido')
            ->columns([
                ImageColumn::make('foto_path')
                    ->label('Foto')
                    ->disk('public')
                    ->circular(),
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->sortable(),
                TextColumn::make('apellido')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('fecha_vencimiento')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('apellido')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
