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
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Acción "Registrar pago" (cobro rápido).
 *
 * Abre un modal con la membresía a la que se imputa el pago, el monto (sugiere el precio del
 * plan según el medio: el precio en efectivo si se paga en efectivo, el normal si no) y el
 * medio de pago, y guarda el pago a nombre del usuario logueado mediante
 * Client::registerPayment().
 *
 * Se puede montar sobre dos tipos de registro:
 * - Un Client (tabla de clientes y encabezado de la ficha): si el cliente tiene una sola
 *   membresía cobrable se muestra como resumen; si tiene varias, se elige en un selector.
 * - Una Membership (fila de la pestaña Membresías): el pago va directo a esa membresía.
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
     * - La acción se monta sobre un registro Client o Membership.
     * - Hay un usuario logueado en el panel.
     *
     * Postcondiciones:
     * - El botón queda deshabilitado (con un aviso) si no hay ninguna membresía a la que cobrarle.
     * - El modal pide la membresía solo cuando hay más de una opción; si no, la muestra como resumen.
     * - Al confirmar el modal se crea un Payment con el usuario logueado como registrado_por_user_id,
     *   el cliente pasa a Entrenando, se muestra una notificación y se avisa al resto de la página
     *   (eventos "pago-registrado" y "cliente-actualizado") para que el historial de pagos y la
     *   ficha del cliente se actualicen.
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
            ->modalHeading(function (Model $record): string {
                // Cliente al que se le cobra (el registro, o el dueño de la membresía).
                $client = self::resolveClient($record);

                // Arma el título.
                return "Registrar pago de {$client->nombre} {$client->apellido}";
            })
            // Modal angosto: son tres datos.
            ->modalWidth('md')
            // Texto del botón que confirma el modal.
            ->modalSubmitActionLabel('Registrar')
            // Deshabilita el botón si no hay a qué membresía imputar el pago.
            ->disabled(fn (Model $record): bool => ! self::hasPayableMembership($record))
            // Explica por qué está deshabilitado (sin aviso cuando está habilitado).
            ->tooltip(fn (Model $record): ?string => self::hasPayableMembership($record)
                // Habilitado: sin tooltip.
                ? null
                // Deshabilitado: dice qué falta.
                : 'No hay membresías activas o en pausa para cobrar')
            // Valores iniciales del modal: la membresía sugerida, el precio de su plan y efectivo.
            ->fillForm(fn (Model $record): array => self::getDefaultFormData($record))
            // Campos del modal.
            ->schema(function (Model $record): array {
                // Cliente al que se le cobra.
                $client = self::resolveClient($record);
                // Membresía ya decidida (fila de membresía, o la única cobrable del cliente); null si hay que elegir.
                $fixedMembership = self::resolveFixedMembership($record);

                // Devuelve los campos en orden.
                return [
                    // Primer campo: la membresía, como resumen o como selector según el caso.
                    $fixedMembership !== null
                        // Una sola opción: se muestra como dato, sin pedir un clic de más.
                        ? TextEntry::make('membership_summary')
                            // Texto del dato.
                            ->label('Membresía')
                            // Plan, vencimiento y estado de la membresía.
                            ->state(self::getMembershipLabel($fixedMembership))
                        // Varias opciones: se elige a cuál imputar el pago.
                        : Select::make('membership_id')
                            // Texto del campo.
                            ->label('Membresía')
                            // Solo las membresías activas o pausadas del cliente, la más nueva primero.
                            ->options(fn (): array => self::getPayableMembershipOptions($client))
                            // Obligatorio: todo pago se imputa a una membresía.
                            ->required()
                            // Avisa al servidor apenas cambia, para recalcular el monto sugerido.
                            ->live()
                            // Al elegir otra membresía, sugiere como monto el precio de su plan para el medio de pago elegido.
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($client): void {
                                // Busca la membresía elegida entre las del cliente, con su plan.
                                $membership = $client->memberships()->with('plan')->find($state);
                                // Si existe, copia el precio del plan al campo monto.
                                if ($membership !== null) {
                                    // Sugiere el precio según el medio de pago; el usuario puede cambiarlo.
                                    $set('monto', $membership->plan->priceFor($get('metodo_pago')));
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
                        ->required()
                        // Avisa al servidor apenas cambia, para recalcular el monto sugerido.
                        ->live()
                        // Al cambiar el medio, sugiere el precio que le corresponde: en efectivo, el precio en efectivo del plan; si no, el normal.
                        ->afterStateUpdated(function (PaymentMethodEnum|string|null $state, Get $get, Set $set) use ($client, $fixedMembership): void {
                            // Membresía a cobrar: la ya decidida o la elegida en el selector, con su plan.
                            $membership = $fixedMembership ?? $client->memberships()->with('plan')->find($get('membership_id'));
                            // Si hay membresía, actualiza el monto.
                            if ($membership !== null) {
                                // Precio del plan para el medio elegido; el usuario puede cambiarlo.
                                $set('monto', $membership->plan->priceFor($state));
                            }
                        }),
                ];
            })
            // Lógica al confirmar el modal.
            ->action(function (array $data, Model $record, Component $livewire): void {
                // Cliente al que se le cobra.
                $client = self::resolveClient($record);
                // Membresía: la ya decidida o, si se eligió en el selector, la elegida entre las del cliente.
                $membership = self::resolveFixedMembership($record)
                    ?? $client->memberships()->find($data['membership_id'] ?? null);
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
                    $payment = $client->registerPayment(
                        // Membresía a la que se imputa.
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

                // Avisa a la página que el cliente cambió (la ficha relee su estado y su último pago).
                $livewire->dispatch('cliente-actualizado');
                // Avisa a la página para que la pestaña "Pagos" vuelva a cargar la tabla.
                $livewire->dispatch('pago-registrado');
            });
    }

    /**
     * Devuelve el cliente al que se le cobra, según sobre qué registro está montada la acción.
     *
     * Precondiciones:
     * - $record es un Client o una Membership.
     *
     * Postcondiciones:
     * - Si es un Client, devuelve ese mismo objeto (así sus cambios se ven en la página).
     * - Si es una Membership, devuelve su cliente (aunque esté dado de baja).
     * - No modifica datos.
     *
     * @throws InvalidArgumentException Si el registro no es de ninguno de los dos tipos.
     */
    private static function resolveClient(Model $record): Client
    {
        // Elige según el tipo de registro.
        return match (true) {
            // La acción está sobre el cliente.
            $record instanceof Client => $record,
            // La acción está sobre una membresía: el cliente es su dueño.
            $record instanceof Membership => $record->client,
            // Cualquier otro registro es un error de programación.
            default => throw new InvalidArgumentException('RegisterPaymentAction solo se usa sobre clientes o membresías.'),
        };
    }

    /**
     * Devuelve la membresía a la que va el pago cuando no hay nada que elegir.
     *
     * Precondiciones:
     * - $record es un Client o una Membership.
     *
     * Postcondiciones:
     * - Sobre una Membership: devuelve esa membresía.
     * - Sobre un Client con exactamente una membresía cobrable: devuelve esa (con su plan cargado).
     * - Sobre un Client con ninguna o con varias: devuelve null (no hay, o hay que elegir).
     * - No modifica datos.
     */
    private static function resolveFixedMembership(Model $record): ?Membership
    {
        // En una fila de membresía, el pago va a esa membresía.
        if ($record instanceof Membership) {
            // No hay nada que elegir.
            return $record;
        }

        // Trae como mucho dos membresías cobrables del cliente (alcanza para saber si hay una sola).
        $payable = self::resolveClient($record)->memberships()->acceptingPayments()->with('plan')->limit(2)->get();

        // Si hay exactamente una, es esa; si no, null.
        return $payable->count() === 1 ? $payable->first() : null;
    }

    /**
     * Indica si hay alguna membresía a la que se le pueda cobrar desde este registro.
     *
     * Precondiciones:
     * - $record es un Client o una Membership.
     *
     * Postcondiciones:
     * - Sobre una Membership: true si su estado admite pagos (no está vencida).
     * - Sobre un Client: true si tiene al menos una membresía que admite pagos.
     * - No modifica datos.
     */
    private static function hasPayableMembership(Model $record): bool
    {
        // En una fila de membresía, depende solo de su estado.
        if ($record instanceof Membership) {
            // Activa o pausada: se le puede cobrar.
            return $record->estado->acceptsPayments();
        }

        // En un cliente, alcanza con que exista una membresía cobrable.
        return self::resolveClient($record)->memberships()->acceptingPayments()->exists();
    }

    /**
     * Arma los valores con los que se abre el modal.
     *
     * Precondiciones:
     * - $record es un Client o una Membership.
     *
     * Postcondiciones:
     * - membership_id es la membresía ya decidida o, si hay varias, la cobrable con fecha de
     *   inicio más reciente (null si no hay ninguna).
     * - monto es el precio en efectivo del plan de esa membresía, o el precio normal si el plan
     *   no tiene precio en efectivo (null si no hay membresía).
     * - metodo_pago es efectivo.
     * - No modifica datos.
     *
     * @return array{membership_id: int|null, monto: string|null, metodo_pago: string}
     */
    private static function getDefaultFormData(Model $record): array
    {
        // Usa la membresía ya decidida o, si hay que elegir, sugiere la cobrable más reciente del cliente.
        $membership = self::resolveFixedMembership($record)
            ?? self::resolveClient($record)->memberships()->acceptingPayments()->with('plan')->latest('fecha_inicio')->latest('id')->first();

        // Devuelve los valores iniciales de cada campo.
        return [
            // Membresía sugerida.
            'membership_id' => $membership?->id,
            // Precio del plan en efectivo (el medio que viene elegido) como monto sugerido.
            'monto' => $membership?->plan->priceFor(PaymentMethodEnum::Efectivo),
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
     *   primero, con el texto de getMembershipLabel().
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
            ->mapWithKeys(fn (Membership $membership): array => [$membership->id => self::getMembershipLabel($membership)])
            // Lo devuelve como array común.
            ->all();
    }

    /**
     * Texto que identifica una membresía en el modal.
     *
     * Precondiciones:
     * - $membership existe y tiene plan (aunque esté dado de baja).
     *
     * Postcondiciones:
     * - Devuelve plan, vencimiento y estado, por ejemplo "3 días · vence 11/12/2026 · Entrenando".
     * - No modifica datos.
     */
    private static function getMembershipLabel(Membership $membership): string
    {
        // Une los tres datos con un punto medio.
        return "{$membership->plan->nombre} · vence {$membership->fecha_vencimiento->format('d/m/Y')} · {$membership->estado->getLabel()}";
    }
}
