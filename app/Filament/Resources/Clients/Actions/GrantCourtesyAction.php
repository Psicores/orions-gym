<?php

namespace App\Filament\Resources\Clients\Actions;

use App\Enums\MembershipStatusEnum;
use App\Models\Client;
use App\Models\Membership;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Acción "Dar cortesía": pasa una membresía a Activo flex y deja al cliente en Activo temporal,
 * para que el socio pueda entrar aunque su fecha haya terminado, por confianza del dueño
 * (la lógica está en Membership::grantCourtesy()).
 *
 * Se puede montar sobre dos tipos de registro:
 * - Una Membership (fila de la pestaña Membresías): actúa sobre esa membresía.
 * - Un Client (encabezado de la ficha): actúa sobre su membresía vigente.
 */
class GrantCourtesyAction extends Action
{
    /**
     * Nombre interno de la acción (Filament lo usa para identificarla en la página).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve "grantCourtesy", así GrantCourtesyAction::make() no necesita nombre.
     *
     * @return string|null Nombre por defecto de la acción.
     */
    public static function getDefaultName(): ?string
    {
        // Nombre fijo de la acción.
        return 'grantCourtesy';
    }

    /**
     * Configura el botón, la confirmación y la lógica de la acción.
     *
     * Precondiciones:
     * - La acción se monta sobre un registro Client o Membership.
     *
     * Postcondiciones:
     * - El botón solo se muestra si hay una membresía Entrenando o En pausa a la que darle
     *   cortesía (no en vencidas ni en las que ya la tienen, ni en clientes sin membresía vigente).
     * - Al hacer clic pide confirmación, aclarando sobre qué membresía actúa y que la cortesía
     *   dura hasta que el socio renueve.
     * - Al confirmar: la membresía queda en Activo flex (sin cambiar su fecha ni sus créditos),
     *   el cliente en Activo temporal, se muestra una notificación y se avisa a la página
     *   (evento "cliente-actualizado") para que refresque los datos del cliente.
     * - Si la cortesía no se puede aplicar, se muestra el motivo y no se guarda nada.
     */
    protected function setUp(): void
    {
        // Configuración base de Filament.
        parent::setUp();

        // Texto del botón.
        $this->label('Dar cortesía')
            // Ícono de regalo.
            ->icon(Heroicon::OutlinedGift)
            // Celeste: es una excepción, no la acción principal.
            ->color('info')
            // Sin membresía a la que darle cortesía, no se muestra.
            ->hidden(fn (Model $record): bool => self::resolveMembership($record) === null)
            // Pide confirmación antes de aplicar.
            ->requiresConfirmation()
            // Título del modal, con el plan de la membresía.
            ->modalHeading(fn (Model $record): string => 'Dar cortesía ('.self::resolveMembership($record)?->plan->nombre.')')
            // Explica qué pasa al confirmar.
            ->modalDescription(fn (Model $record): string => 'La membresía pasa a Activo flex: el socio va a poder escanear y entrar aunque su fecha ('.self::resolveMembership($record)?->fecha_vencimiento->format('d/m/Y').') haya terminado. La cortesía dura hasta que renueve.')
            // Ícono del modal.
            ->modalIcon(Heroicon::OutlinedGift)
            // Texto del botón que confirma.
            ->modalSubmitActionLabel('Dar cortesía')
            // Lógica al confirmar.
            ->action(function (Model $record, Component $livewire): void {
                // Membresía sobre la que actúa.
                $membership = self::resolveMembership($record);

                // Intenta dar la cortesía; el modelo valida el estado.
                try {
                    // Sin membresía no hay nada que habilitar.
                    if ($membership === null) {
                        // Corta con el motivo.
                        throw new InvalidArgumentException('El cliente no tiene una membresía a la que darle cortesía.');
                    }

                    // Pasa la membresía a Activo flex y el cliente a Activo temporal.
                    $membership->grantCourtesy();
                } catch (InvalidArgumentException $exception) {
                    // Muestra el motivo.
                    Notification::make()->title('No se pudo dar la cortesía')->body($exception->getMessage())->danger()->send();

                    // No sigue.
                    return;
                }

                // Confirma.
                Notification::make()
                    // Título de la notificación.
                    ->title('Cortesía otorgada')
                    // Detalle.
                    ->body('Puede entrar hasta que renueve.')
                    // Verde.
                    ->success()
                    // La muestra.
                    ->send();

                // Avisa a la página para que refresque el estado del cliente.
                $livewire->dispatch('cliente-actualizado');
            });
    }

    /**
     * Devuelve la membresía a la que se le da la cortesía.
     *
     * Precondiciones:
     * - $record es un Client o una Membership.
     *
     * Postcondiciones:
     * - Sobre una Membership Entrenando o En pausa: devuelve esa membresía.
     * - Sobre un Client: devuelve su membresía vigente (Client::currentMembership) si está
     *   Entrenando o En pausa.
     * - Devuelve null si la membresía está vencida o ya tiene cortesía (Activo flex), si el
     *   cliente no tiene membresía vigente o si el registro es de otro tipo.
     * - No modifica datos.
     *
     * @param  Model  $record  Cliente o membresía sobre el que se monta la acción.
     * @return Membership|null La membresía a habilitar, o null si no corresponde.
     */
    private static function resolveMembership(Model $record): ?Membership
    {
        // Membresía candidata según el tipo de registro.
        $membership = match (true) {
            // Fila de la pestaña Membresías.
            $record instanceof Membership => $record,
            // Ficha del cliente: su membresía vigente.
            $record instanceof Client => $record->currentMembership,
            // Cualquier otro registro no aplica.
            default => null,
        };

        // Solo sirve si está en un estado al que tiene sentido darle cortesía.
        return in_array($membership?->estado, [MembershipStatusEnum::Entrenando, MembershipStatusEnum::EnPausa], true)
            ? $membership
            : null;
    }
}
