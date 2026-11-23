<?php

namespace App\Filament\Resources\AccessPoints\Pages;

use App\Filament\Resources\AccessPoints\AccessPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ManageAccessPoints extends ManageRecords
{
    protected static string $resource = AccessPointResource::class;

    protected ?string $subheading = 'Cada punto tiene su QR. El socio lo abre con la cámara del celular y confirma su ingreso.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Ícono del botón.
                ->icon(Heroicon::OutlinedPlus)
                // Sin "Crear y crear otro".
                ->createAnother(false)
                // Modal angosto.
                ->modalWidth(Width::Medium)
                // Botones a la derecha.
                ->modalFooterActionsAlignment(Alignment::End)
                // Cabecera en el color principal.
                ->extraModalWindowAttributes(['class' => 'orions-modal']),
        ];
    }
}
