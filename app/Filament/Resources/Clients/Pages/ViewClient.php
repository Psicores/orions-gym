<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\Actions\GrantCourtesyAction;
use App\Filament\Resources\Clients\Actions\RegisterPaymentAction;
use App\Filament\Resources\Clients\Actions\ResetMemberPasswordAction;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

/**
 * Ficha del cliente: la pantalla de trabajo de recepción.
 *
 * Muestra el resumen del cliente (ClientResource::infolist()) y, en pestañas, sus membresías
 * y sus pagos. Desde el encabezado se cobra, se da cortesía o se pasa a editar los datos.
 */
class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * Título de la página: el nombre del cliente en lugar del genérico "Ver cliente".
     *
     * Precondiciones:
     * - La página tiene cargado un cliente.
     *
     * Postcondiciones:
     * - Devuelve "Nombre Apellido".
     *
     * @return string Título de la página.
     */
    public function getTitle(): string
    {
        // Nombre y apellido del cliente de la ficha.
        return "{$this->getRecord()->nombre} {$this->getRecord()->apellido}";
    }

    /**
     * Botones del encabezado de la ficha.
     *
     * Precondiciones:
     * - La página tiene cargado un cliente.
     *
     * Postcondiciones:
     * - Ofrece, en este orden: registrar un pago, dar cortesía a la membresía vigente
     *   (solo si tiene una Entrenando o En pausa), restablecer la contraseña del portal
     *   (solo si el socio ya eligió una propia) y editar los datos del cliente.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        // Acciones en orden de uso en recepción.
        return [
            // Cobro rápido.
            RegisterPaymentAction::make(),
            // Cortesía sobre la membresía vigente; con borde para que no compita con el cobro.
            GrantCourtesyAction::make()->outlined(),
            // Para el socio que olvidó su contraseña: vuelve a entrar con el DNI.
            ResetMemberPasswordAction::make()->outlined(),
            // Pasa al formulario de datos del cliente.
            EditAction::make(),
        ];
    }

    /**
     * Indica que el resumen del cliente, sus membresías y sus pagos se muestren como pestañas.
     *
     * Precondiciones:
     * - ClientResource::getRelations() devuelve al menos un relation manager.
     *
     * Postcondiciones:
     * - La página muestra las pestañas "Ficha", "Membresías" y "Pagos" en lugar de apilar las
     *   tablas debajo del resumen.
     *
     * @return bool Siempre true.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        // Activa las pestañas combinadas.
        return true;
    }

    /**
     * Nombre de la pestaña que contiene el resumen del cliente.
     *
     * Precondiciones:
     * - hasCombinedRelationManagerTabsWithContent() devuelve true.
     *
     * Postcondiciones:
     * - La primera pestaña se llama "Ficha".
     *
     * @return string Texto de la pestaña.
     */
    public function getContentTabLabel(): string
    {
        // Texto de la pestaña del resumen.
        return 'Ficha';
    }

    /**
     * Vuelve a leer el cliente cuando algo cambió sus datos desde la misma página
     * (un cobro, una cortesía, o crear, editar o borrar una membresía).
     *
     * Precondiciones:
     * - Alguna acción o pestaña emitió "cliente-actualizado" o "pago-registrado" después de
     *   guardar los cambios en la base.
     *
     * Postcondiciones:
     * - El cliente y sus datos relacionados (membresía vigente, último pago) se releen de la
     *   base, así la ficha muestra el estado, el vencimiento, los créditos y el último pago
     *   actuales sin recargar la página.
     * - No modifica datos.
     */
    #[On('cliente-actualizado')]
    #[On('pago-registrado')]
    public function refreshClient(): void
    {
        // Descarta los datos relacionados que estaban en memoria (membresía vigente, último pago).
        $this->getRecord()->unsetRelations();
        // Relee los atributos del cliente desde la base.
        $this->getRecord()->refresh();
    }
}
