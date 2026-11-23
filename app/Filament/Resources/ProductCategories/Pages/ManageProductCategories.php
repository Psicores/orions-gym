<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ManageProductCategories extends ManageRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Ícono del botón "Crear categoría".
                ->icon(Heroicon::OutlinedPlus)
                // Solo guardar o cancelar.
                ->createAnother(false)
                // Modal angosto: dos campos.
                ->modalWidth(Width::Medium)
                // Botones a la derecha.
                ->modalFooterActionsAlignment(Alignment::End)
                // Cabecera en el color principal.
                ->extraModalWindowAttributes(['class' => 'orions-modal']),
        ];
    }
}
