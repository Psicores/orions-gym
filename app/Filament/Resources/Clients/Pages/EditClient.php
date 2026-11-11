<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\Actions\RegisterPaymentAction;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RegisterPaymentAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Indica que el formulario del cliente y sus membresías se muestren como pestañas.
     *
     * Precondiciones:
     * - ClientResource::getRelations() devuelve al menos un relation manager.
     *
     * Postcondiciones:
     * - La página muestra las pestañas "Cliente" y "Membresías" en lugar de apilar la tabla
     *   debajo del formulario.
     *
     * @return bool Siempre true.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        // Activa las pestañas combinadas.
        return true;
    }

    /**
     * Nombre de la pestaña que contiene el formulario del cliente.
     *
     * Precondiciones:
     * - hasCombinedRelationManagerTabsWithContent() devuelve true.
     *
     * Postcondiciones:
     * - La primera pestaña se llama "Cliente".
     *
     * @return string Texto de la pestaña.
     */
    public function getContentTabLabel(): string
    {
        // Texto de la pestaña del formulario.
        return 'Cliente';
    }
}
