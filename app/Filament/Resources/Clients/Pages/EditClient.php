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
     * Actualiza los campos Estado y Fecha de vencimiento del formulario cuando otra pestaña los
     * cambió (por ejemplo, al crear, editar o borrar una membresía o al dar días de gracia).
     *
     * Precondiciones:
     * - Algún componente de la página emitió el evento "cliente-actualizado" después de
     *   guardar cambios del cliente en la base.
     *
     * Postcondiciones:
     * - El registro de la página se vuelve a leer de la base y los campos estado y
     *   fecha_vencimiento del formulario muestran los valores actuales.
     * - Los demás campos del formulario conservan lo que el usuario haya editado sin guardar.
     * - Así, "Guardar cambios" no vuelve el estado a su valor anterior.
     */
    #[On('cliente-actualizado')]
    public function refreshClientStatus(): void
    {
        // Relee el cliente desde la base (cambió fuera de este formulario).
        $this->getRecord()->refresh();
        // Copia al formulario solo el estado y el vencimiento, sin tocar el resto de los campos.
        $this->refreshFormData(['estado', 'fecha_vencimiento']);
    }
}
