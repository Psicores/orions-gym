<?php

namespace App\Filament\Resources\Clients\Actions;

use App\Enums\MembershipStatusEnum;
use App\Models\Client;
use App\Models\Membership;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Acción "Dar días de gracia": pasa una membresía a Activo flex, corre su vencimiento y deja
 * al cliente en Activo temporal (la lógica está en Membership::grantGraceDays()).
 *
 * Se puede montar sobre dos tipos de registro:
 * - Una Membership (fila de la pestaña Membresías): actúa sobre esa membresía.
 * - Un Client (encabezado de la ficha): actúa sobre su membresía vigente.
 */
class GrantGraceDaysAction extends Action
{
    /**
     * Nombre interno de la acción (Filament lo usa para identificarla en la página).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve "grantGraceDays", así GrantGraceDaysAction::make() no necesita nombre.
     *
     * @return string|null Nombre por defecto de la acción.
     */
    public static function getDefaultName(): ?string
    {
        // Nombre fijo de la acción.
        return 'grantGraceDays';
    }

    /**
     * Configura el botón, el modal y la lógica de la acción.
     *
     * Precondiciones:
     * - La acción se monta sobre un registro Client o Membership.
     *
     * Postcondiciones:
     * - El botón no se muestra si no hay membresía a la que darle gracia (membresía vencida, o
     *   cliente sin membresía vigente).
     * - El modal pide la cantidad de días (7 por defecto, entre 1 y 60) y aclara sobre qué
     *   membresía actúa y cuándo vence hoy.
     * - Al confirmar: la membresía queda en Activo flex con el vencimiento extendido, el cliente
     *   en Activo temporal, se muestra una notificación con el nuevo vencimiento y se avisa a la
     *   página (evento "cliente-actualizado") para que refresque los datos del cliente.
     * - Si la gracia no se puede aplicar, se muestra el motivo y no se guarda nada.
     */
    protected function setUp(): void
    {
        // Mantiene la configuración base de las acciones de Filament.
        parent::setUp();

        // Texto del botón.
        $this->label('Dar días de gracia')
            // Ícono de reloj.
            ->icon(Heroicon::OutlinedClock)
            // Celeste, el mismo color que el estado Activo flex.
            ->color('info')
            // No se ofrece si no hay membresía a la que darle gracia.
            ->hidden(fn (Model $record): bool => self::resolveMembership($record) === null)
            // Título del modal con el plan de la membresía.
            ->modalHeading(fn (Model $record): string => 'Dar días de gracia ('.self::resolveMembership($record)?->plan->nombre.')')
            // Explica qué va a pasar al confirmar.
            ->modalDescription(fn (Model $record): string => 'Pasa la membresía a Activo flex y corre el vencimiento (hoy: '.self::resolveMembership($record)?->fecha_vencimiento->format('d/m/Y').'). Si ya venció, los días se cuentan desde hoy.')
            // Modal angosto: es un solo dato.
            ->modalWidth('md')
            // Texto del botón que confirma.
            ->modalSubmitActionLabel('Dar gracia')
            // Campos del modal.
            ->schema([
                // Cantidad de días a extender.
                TextInput::make('dias')
                    // Texto del campo.
                    ->label('Días de gracia')
                    // Solo enteros.
                    ->integer()
                    // Una semana por defecto.
                    ->default(7)
                    // Al menos un día.
                    ->minValue(1)
                    // Como mucho dos meses.
                    ->maxValue(60)
                    // Obligatorio.
                    ->required(),
            ])
            // Al confirmar, aplica la gracia desde el modelo (que valida estado y rango).
            ->action(function (array $data, Model $record, Component $livewire): void {
                // Membresía sobre la que actúa.
                $membership = self::resolveMembership($record);

                // Intenta aplicar la gracia.
                try {
                    // Sin membresía no hay nada que extender.
                    if ($membership === null) {
                        // Lo trata igual que cualquier otro motivo de rechazo.
                        throw new InvalidArgumentException('El cliente no tiene una membresía vigente.');
                    }

                    // Cambia estado y vencimiento de la membresía, y pasa al cliente a Activo temporal.
                    $membership->grantGraceDays((int) $data['dias']);
                } catch (InvalidArgumentException $exception) {
                    // Muestra el motivo (membresía vencida, sin membresía o días fuera de rango).
                    Notification::make()->title('No se pudieron dar los días de gracia')->body($exception->getMessage())->danger()->send();

                    // Sale sin guardar.
                    return;
                }

                // Confirma con la nueva fecha de vencimiento.
                Notification::make()
                    // Título de la notificación.
                    ->title('Días de gracia otorgados')
                    // Detalle con el nuevo vencimiento.
                    ->body('Ahora vence el '.$membership->fecha_vencimiento->format('d/m/Y').'.')
                    // Verde.
                    ->success()
                    // La muestra.
                    ->send();

                // Avisa a la página para que refresque el estado y el vencimiento del cliente.
                $livewire->dispatch('cliente-actualizado');
            });
    }

    /**
     * Devuelve la membresía a la que se le dan los días de gracia.
     *
     * Precondiciones:
     * - $record es un Client o una Membership.
     *
     * Postcondiciones:
     * - Sobre una Membership no vencida: devuelve esa membresía.
     * - Sobre un Client: devuelve su membresía vigente (Client::currentMembership).
     * - Devuelve null si la membresía está vencida, si el cliente no tiene membresía vigente o
     *   si el registro es de otro tipo.
     * - No modifica datos.
     */
    private static function resolveMembership(Model $record): ?Membership
    {
        // Elige según el tipo de registro.
        return match (true) {
            // Fila de membresía: esa misma, salvo que esté vencida.
            $record instanceof Membership => $record->estado === MembershipStatusEnum::Vencida ? null : $record,
            // Cliente: su membresía vigente (ya excluye las vencidas).
            $record instanceof Client => $record->currentMembership,
            // Cualquier otro registro: no aplica.
            default => null,
        };
    }
}
