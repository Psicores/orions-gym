<?php

namespace App\Filament\Resources\Clients\Actions;

use App\Models\Client;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Acción "Restablecer contraseña": para el socio que olvidó la contraseña del portal. Lo deja
 * entrando otra vez con su DNI, y el portal lo obliga a elegir una nueva
 * (la lógica está en Client::resetPassword()).
 *
 * Se monta sobre un Client (encabezado de la ficha).
 */
class ResetMemberPasswordAction extends Action
{
    /**
     * Nombre interno de la acción (Filament lo usa para identificarla en la página).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve "resetMemberPassword", así ResetMemberPasswordAction::make() no necesita nombre.
     *
     * @return string|null Nombre por defecto de la acción.
     */
    public static function getDefaultName(): ?string
    {
        // Nombre fijo de la acción.
        return 'resetMemberPassword';
    }

    /**
     * Configura el botón, la confirmación y la lógica de la acción.
     *
     * Precondiciones:
     * - La acción se monta sobre un registro Client.
     *
     * Postcondiciones:
     * - El botón solo se muestra si el socio ya eligió una contraseña propia: mientras siga
     *   entrando con el DNI no hay nada que restablecer.
     * - Al hacer clic pide confirmación y explica qué va a pasar.
     * - Al confirmar: la contraseña del socio vuelve a ser su DNI, los dispositivos recordados
     *   tienen que volver a ingresar y se muestra una notificación con lo que hay que decirle.
     * - Nadie ve ni elige la contraseña nueva: la elige el socio en su próximo ingreso.
     */
    protected function setUp(): void
    {
        // Configuración base de Filament.
        parent::setUp();

        // Texto del botón.
        $this->label('Restablecer contraseña')
            // Ícono de llave.
            ->icon(Heroicon::OutlinedKey)
            // Gris: es una gestión ocasional, no compite con el cobro.
            ->color('gray')
            // Solo si tiene una contraseña propia para borrar.
            ->visible(fn (Client $record): bool => $record->hasCustomPassword())
            // Pide confirmación antes de aplicar.
            ->requiresConfirmation()
            // Título del modal.
            ->modalHeading('Restablecer la contraseña del portal')
            // Explica qué pasa al confirmar.
            ->modalDescription(fn (Client $record): string => "{$record->nombre} va a volver a entrar con su DNI como usuario y contraseña, y el portal le va a pedir que elija una nueva. La contraseña actual deja de servir.")
            // Ícono del modal.
            ->modalIcon(Heroicon::OutlinedKey)
            // Texto del botón que confirma.
            ->modalSubmitActionLabel('Restablecer')
            // Lógica al confirmar.
            ->action(function (Client $record): void {
                // Borra la contraseña propia: vuelve a valer el DNI.
                $record->resetPassword();

                // Confirma y recuerda qué decirle al socio.
                Notification::make()
                    // Título de la notificación.
                    ->title('Contraseña restablecida')
                    // Detalle.
                    ->body('Avisale que entre con su DNI como usuario y contraseña, y que elija una nueva.')
                    // Verde.
                    ->success()
                    // La muestra.
                    ->send();
            });
    }
}
