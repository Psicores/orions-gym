<?php

namespace App\Filament\Resources\Clients\Actions;

use App\Enums\PaymentMethodEnum;
use App\Models\Client;
use App\Models\Membership;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Acción "Registrar pago" (cobro rápido) sobre un cliente.
 *
 * Abre un modal con la membresía a la que se imputa el pago (solo activas o pausadas),
 * el monto (sugiere el precio del plan) y el medio de pago, y guarda el pago a nombre
 * del usuario logueado mediante Client::registerPayment().
 *
 * Se usa en la tabla de clientes (una por fila) y en el encabezado de la edición del cliente.
 */
class RegisterPaymentAction extends Action
{
    /**
     * Nombre interno de la acción (Filament lo usa para identificarla en la página).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve "registerPayment", así RegisterPaymentAction::make() no necesita nombre.
     *
     * @return string|null Nombre por defecto de la acción.
     */
    public static function getDefaultName(): ?string
    {
        // Nombre fijo de la acción.
        return 'registerPayment';
    }

    /**
     * Configura el botón, el modal y la lógica de guardado de la acción.
     *
     * Precondiciones:
     * - La acción se monta sobre un registro Client (fila de la tabla o página de edición).
     * - Hay un usuario logueado en el panel.
     *
     * Postcondiciones:
     * - El botón queda deshabilitado (con un aviso) si el cliente no tiene membresías que admitan pagos.
     * - Al confirmar el modal se crea un Payment con el usuario logueado como registrado_por_user_id,
     *   se muestra una notificación y se avisa al resto de la página (evento "pago-registrado")
     *   para que el historial de pagos se actualice.
     * - Si el pago no se puede registrar, se muestra el motivo y no se guarda nada.
     */
    protected function setUp(): void
    {
        // Mantiene la configuración base de las acciones de Filament.
        parent::setUp();

        // Texto del botón.
        $this->label('Registrar pago')
            // Ícono de billetes.
            ->icon(Heroicon::OutlinedBanknotes)
            // Verde, para distinguirlo de editar/borrar.
            ->color('success')
            // Título del modal con el nombre del cliente.
            ->modalHeading(fn (Client $record): string => "Registrar pago de {$record->nombre} {$record->apellido}")
            // Texto del botón que confirma el modal.
            ->modalSubmitActionLabel('Registrar')
            // Deshabilita el botón si no hay a qué membresía imputar el pago.
            ->disabled(fn (Client $record): bool => ! $record->memberships()->acceptingPayments()->exists())
            // Explica por qué está deshabilitado (sin aviso cuando está habilitado).
            ->tooltip(fn (Client $record): ?string => $record->memberships()->acceptingPayments()->exists()
                // Habilitado: sin tooltip.
                ? null
                // Deshabilitado: dice qué falta.
                : 'El cliente no tiene membresías activas o en pausa')
            // Valores iniciales del modal: la membresía más reciente, el precio de su plan y efectivo.
            ->fillForm(fn (Client $record): array => self::getDefaultFormData($record))
            // Campos del modal.
            ->schema(fn (Client $record): array => [
                // Membresía a la que se imputa el pago.
                Select::make('membership_id')
                    // Texto del campo.
                    ->label('Membresía')
                    // Solo las membresías activas o pausadas del cliente, la más nueva primero.
                    ->options(fn (): array => self::getPayableMembershipOptions($record))
                    // Obligatorio: todo pago se imputa a una membresía.
                    ->required()
                    // Avisa al servidor apenas cambia, para recalcular el monto sugerido.
                    ->live()
                    // Al elegir otra membresía, sugiere el precio de su plan como monto.
                    ->afterStateUpdated(function (?string $state, Set $set) use ($record): void {
                        // Busca la membresía elegida entre las del cliente, con su plan.
                        $membership = $record->memberships()->with('plan')->find($state);
                        // Si existe, copia el precio del plan al campo monto.
                        if ($membership !== null) {
                            // Sugiere el precio; el usuario puede cambiarlo.
                            $set('monto', $membership->plan->precio);
                        }
                    }),
                // Monto cobrado.
                TextInput::make('monto')
                    // Texto del campo.
                    ->label('Monto')
                    // Solo números.
                    ->numeric()
                    // Muestra el signo $ antes del número.
                    ->prefix('$')
                    // Permite centavos.
                    ->step(0.01)
                    // No acepta cero ni negativos.
                    ->minValue(0.01)
                    // Máximo que entra en la columna decimal(10,2).
                    ->maxValue(99999999.99)
                    // Obligatorio.
                    ->required(),
                // Medio de pago, como botones para elegir con un clic.
                ToggleButtons::make('metodo_pago')
                    // Texto del campo.
                    ->label('Medio de pago')
                    // Opciones, colores e íconos tomados del enum.
                    ->options(PaymentMethodEnum::class)
                    // Botones uno al lado del otro.
                    ->inline()
                    // Obligatorio.
                    ->required(),
            ])
            // Lógica al confirmar el modal.
            ->action(function (array $data, Client $record, Component $livewire): void {
                // Busca la membresía elegida, solo entre las del cliente.
                $membership = $record->memberships()->find($data['membership_id']);
                // Usuario logueado: es quien cobró.
                $user = auth()->user();

                // Si la membresía no es del cliente o no hay usuario, no sigue.
                if (! $membership instanceof Membership || ! $user instanceof User) {
                    // Avisa el error en pantalla.
                    Notification::make()->title('No se pudo registrar el pago')->danger()->send();

                    // Sale sin guardar.
                    return;
                }

                // Intenta guardar el pago; el modelo valida membresía y monto.
                try {
                    // Crea el pago a nombre del usuario logueado.
                    $payment = $record->registerPayment(
                        // Membresía elegida.
                        $membership,
                        // Monto como texto, para no perder centavos.
                        (string) $data['monto'],
                        // Medio de pago (el campo puede devolver el caso del enum o su valor).
                        $data['metodo_pago'] instanceof PaymentMethodEnum ? $data['metodo_pago'] : PaymentMethodEnum::from($data['metodo_pago']),
                        // Quién cobró.
                        $user,
                    );
                } catch (InvalidArgumentException $exception) {
                    // Muestra el motivo (membresía vencida, de otro cliente o monto inválido).
                    Notification::make()->title('No se pudo registrar el pago')->body($exception->getMessage())->danger()->send();

                    // Sale sin guardar.
                    return;
                }

                // Confirma el cobro con el monto formateado en pesos y el medio usado.
                Notification::make()
                    // Título de la notificación.
                    ->title('Pago registrado')
                    // Detalle: monto y medio de pago.
                    ->body(Number::currency((float) $payment->monto, in: 'ARS', locale: 'es_AR').' en '.mb_strtolower($payment->metodo_pago->getLabel()))
                    // Verde.
                    ->success()
                    // La muestra.
                    ->send();

                // Avisa a la página para que la pestaña "Pagos" vuelva a cargar la tabla.
                $livewire->dispatch('pago-registrado');
            });
    }

    /**
     * Arma los valores con los que se abre el modal.
     *
     * Precondiciones:
     * - $client existe en la base.
     *
     * Postcondiciones:
     * - membership_id es la membresía que admite pagos con fecha de inicio más reciente
     *   (null si no hay ninguna).
     * - monto es el precio del plan de esa membresía (null si no hay membresía).
     * - metodo_pago es efectivo.
     * - No modifica datos.
     *
     * @return array{membership_id: int|null, monto: string|null, metodo_pago: string}
     */
    private static function getDefaultFormData(Client $client): array
    {
        // Busca la membresía cobrable más reciente del cliente, con su plan para leer el precio.
        $membership = $client->memberships()->acceptingPayments()->with('plan')->latest('fecha_inicio')->latest('id')->first();

        // Devuelve los valores iniciales de cada campo.
        return [
            // Membresía sugerida.
            'membership_id' => $membership?->id,
            // Precio del plan como monto sugerido.
            'monto' => $membership?->plan->precio,
            // Efectivo, el medio más común en el mostrador.
            'metodo_pago' => PaymentMethodEnum::Efectivo->value,
        ];
    }

    /**
     * Lista las membresías del cliente a las que se puede imputar un pago, para el selector.
     *
     * Precondiciones:
     * - $client existe en la base.
     *
     * Postcondiciones:
     * - Devuelve "id => texto" solo con membresías activas o pausadas, la de inicio más reciente
     *   primero. El texto muestra plan, vencimiento y estado (por ejemplo
     *   "3x semana · vence 11/12/2026 · Entrenando").
     * - No modifica datos.
     *
     * @return array<int, string>
     */
    private static function getPayableMembershipOptions(Client $client): array
    {
        // Consulta las membresías del cliente.
        return $client->memberships()
            // Solo las que admiten pagos.
            ->acceptingPayments()
            // Trae el plan en la misma tanda para no hacer una consulta por fila.
            ->with('plan')
            // La más reciente primero.
            ->latest('fecha_inicio')
            // Desempata por la creada último.
            ->latest('id')
            // Ejecuta la consulta.
            ->get()
            // Convierte cada membresía en "id => texto".
            ->mapWithKeys(fn (Membership $membership): array => [
                // Texto con plan, vencimiento y estado.
                $membership->id => "{$membership->plan->nombre} · vence {$membership->fecha_vencimiento->format('d/m/Y')} · {$membership->estado->getLabel()}",
            ])
            // Lo devuelve como array común.
            ->all();
    }
}
