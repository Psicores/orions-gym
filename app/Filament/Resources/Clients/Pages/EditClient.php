<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\Actions\RegisterPaymentAction;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

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
     * Indica que el formulario del cliente, sus membresías y sus pagos se muestren como pestañas.
     *
     * Precondiciones:
     * - ClientResource::getRelations() devuelve al menos un relation manager.
     *
     * Postcondiciones:
     * - La página muestra las pestañas "Cliente", "Membresías" y "Pagos" en lugar de apilar las tablas
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

    /**
     * Actualiza el campo Estado del formulario cuando otra pestaña cambió el estado del cliente
     * (por ejemplo, al dar días de gracia desde Membresías).
     *
     * Precondiciones:
     * - Algún componente de la página emitió el evento "estado-cliente-actualizado" después de
     *   guardar un nuevo estado del cliente en la base.
     *
     * Postcondiciones:
     * - El registro de la página se vuelve a leer de la base y el campo estado del formulario
     *   muestra el valor actual.
     * - Los demás campos del formulario conservan lo que el usuario haya editado sin guardar.
     * - Así, "Guardar cambios" no vuelve el estado a su valor anterior.
     */
    #[On('estado-cliente-actualizado')]
    public function refreshClientStatus(): void
    {
        // Relee el cliente desde la base (el estado cambió fuera de este formulario).
        $this->getRecord()->refresh();
        // Copia al formulario solo el estado, sin tocar el resto de los campos.
        $this->refreshFormData(['estado']);
    }
}
