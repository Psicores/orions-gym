<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edición de los datos del cliente (datos personales, estado, foto, certificado, observaciones).
 *
 * El trabajo diario (cobrar, membresías, pagos) se hace en la ficha (ViewClient); esta página
 * es solo el formulario.
 */
class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * Botones del encabezado de la edición.
     *
     * Precondiciones:
     * - La página tiene cargado un cliente.
     *
     * Postcondiciones:
     * - Ofrece volver a la ficha, dar de baja al cliente y, si está dado de baja, restaurarlo.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        // Acciones del encabezado.
        return [
            // Vuelve a la ficha sin guardar.
            ViewAction::make()->label('Ver ficha'),
            // Da de baja al cliente (baja lógica).
            DeleteAction::make(),
            // Restaura a un cliente dado de baja.
            RestoreAction::make(),
        ];
    }

    /**
     * Tablas relacionadas de esta página: ninguna.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - La edición muestra solo el formulario; Membresías y Pagos están en la ficha del cliente.
     *
     * @return array<class-string>
     */
    public function getRelationManagers(): array
    {
        // Sin pestañas de membresías ni pagos acá.
        return [];
    }

    /**
     * A dónde ir después de guardar.
     *
     * Precondiciones:
     * - El cliente se guardó correctamente.
     *
     * Postcondiciones:
     * - Devuelve la URL de la ficha del cliente, para seguir trabajando desde ahí.
     *
     * @return string URL de la ficha.
     */
    protected function getRedirectUrl(): string
    {
        // Vuelve a la ficha del cliente recién guardado.
        return $this->getResourceUrl('view', ['record' => $this->getRecord()]);
    }
}
