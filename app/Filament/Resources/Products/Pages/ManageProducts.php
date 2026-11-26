<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Solo aparece para administradores (ver ProductPolicy).
            CreateAction::make()
                // Ícono del botón "Crear producto".
                ->icon(Heroicon::OutlinedPlus)
                // Sin "Crear y crear otro": el modal solo guarda o cancela.
                ->createAnother(false)
                // Ícono del modal.
                ->modalIcon(Heroicon::OutlinedShoppingBag)
                // Ayuda debajo del título.
                ->modalDescription('Nombre y precio son obligatorios; lo demás se puede completar después.')
                // Modal ancho: dos columnas.
                ->modalWidth(Width::TwoExtraLarge)
                // Botones a la derecha.
                ->modalFooterActionsAlignment(Alignment::End)
                // Cabecera en el color principal.
                ->extraModalWindowAttributes(['class' => 'orions-modal']),
        ];
    }
}
