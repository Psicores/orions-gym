<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ManagePlans extends ManageRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Ícono del botón "Crear plan".
                ->icon(Heroicon::OutlinedPlus)
                // Sin el botón "Crear y crear otro": el modal solo guarda o cancela.
                ->createAnother(false)
                // Ícono que acompaña al título del modal.
                ->modalIcon(Heroicon::OutlinedCreditCard)
                // Texto de ayuda debajo del título.
                ->modalDescription('Definí el nombre, el precio y las clases que habilita.')
                // Modal angosto: el formulario tiene solo tres campos.
                ->modalWidth(Width::Large)
                // Botones del pie alineados a la derecha.
                ->modalFooterActionsAlignment(Alignment::End)
                // Cabecera en el color principal (ver resources/views/filament/admin/styles.blade.php).
                ->extraModalWindowAttributes(['class' => 'orions-modal']),
        ];
    }
}
