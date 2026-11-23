<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Ícono del botón.
                ->icon(Heroicon::OutlinedPlus)
                // Sin "Crear y crear otro".
                ->createAnother(false)
                // Ícono del título del modal.
                ->modalIcon(Heroicon::OutlinedUserPlus)
                // Ayuda debajo del título.
                ->modalDescription('La cuenta queda lista para entrar por /ingresar.')
                // Modal angosto.
                ->modalWidth(Width::Large)
                // Botones a la derecha.
                ->modalFooterActionsAlignment(Alignment::End)
                // Cabecera en el color principal.
                ->extraModalWindowAttributes(['class' => 'orions-modal'])
                // El email lo carga un administrador: queda verificado, requisito para entrar al panel.
                ->after(fn (User $record) => $record->markEmailAsVerified()),
        ];
    }
}
