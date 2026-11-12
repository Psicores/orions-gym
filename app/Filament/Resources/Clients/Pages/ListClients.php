<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Enums\ClientStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\Plan;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use InvalidArgumentException;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Ícono del botón "Crear cliente".
                ->icon(Heroicon::OutlinedUserPlus)
                // Sin el botón "Crear y crear otro": el modal solo guarda o cancela.
                ->createAnother(false)
                // Ícono que acompaña al título del modal.
                ->modalIcon(Heroicon::OutlinedUserPlus)
                // Texto de ayuda debajo del título.
                ->modalDescription('Completá los datos del cliente. La foto y el certificado se pueden cargar después.')
                // Modal mediano: los campos van de a dos por fila.
                ->modalWidth(Width::TwoExtraLarge)
                // Botones del pie alineados a la derecha.
                ->modalFooterActionsAlignment(Alignment::End)
                // Cabecera en el color principal (ver resources/views/filament/admin/styles.blade.php).
                ->extraModalWindowAttributes(['class' => 'orions-modal'])
                // Paso siguiente: al guardar, cambia este modal por el de crearle la membresía al cliente nuevo.
                ->after(fn (Client $record) => $this->replaceMountedAction('createMembership', ['client' => $record->getKey()])),
        ];
    }

    /**
     * Acción "Crear membresía": el paso siguiente (opcional) al alta de un cliente. Crea la
     * membresía y, en el mismo paso, registra su pago.
     *
     * No tiene botón propio: se abre sola, en un modal, apenas se guarda un cliente nuevo
     * (ver getHeaderActions()), con el id de ese cliente en el argumento "client".
     *
     * Precondiciones:
     * - El argumento "client" es el id de un cliente existente.
     * - Existe al menos un plan activo para poder elegirlo.
     * - Hay un usuario logueado en el panel (queda como quien cobró).
     *
     * Postcondiciones:
     * - El modal pide el plan, las fechas (por defecto, de hoy a dentro de un mes), el monto
     *   (al elegir el plan sugiere su precio) y el medio de pago (por defecto, efectivo).
     * - "Crear membresía": guarda la membresía en estado Entrenando (ver
     *   Client::startMembership()), registra el pago a nombre del usuario logueado (ver
     *   Client::registerPayment()), deja al cliente en Entrenando, avisa y abre su ficha.
     * - La membresía y el pago se guardan juntos: si uno falla, no queda ninguno.
     * - "Ahora no": cierra el modal sin guardar nada; el cliente queda creado, sin membresía,
     *   sin pago y con el estado que se le eligió en el alta.
     * - Si la membresía o el pago no se pueden guardar, muestra el motivo y no guarda nada.
     *
     * @return Action La acción configurada.
     */
    public function createMembershipAction(): Action
    {
        // Acción sin botón visible: solo se monta desde el alta de clientes.
        return Action::make('createMembership')
            // Título del modal con el nombre del cliente recién creado.
            ->modalHeading(function (array $arguments): string {
                // Cliente al que se le crea la membresía.
                $client = Client::query()->findOrFail($arguments['client']);

                // Arma el título.
                return "Membresía de {$client->nombre} {$client->apellido}";
            })
            // Aclara que el alta ya se guardó y qué pasa si se sigue.
            ->modalDescription('El cliente ya quedó creado. Al crear la membresía se registra el pago y pasa a Entrenando.')
            // Ícono que acompaña al título del modal.
            ->modalIcon(Heroicon::OutlinedIdentification)
            // Modal angosto: son pocos datos.
            ->modalWidth(Width::Large)
            // Botones del pie alineados a la derecha.
            ->modalFooterActionsAlignment(Alignment::End)
            // Cabecera en el color principal (ver resources/views/filament/admin/styles.blade.php).
            ->extraModalWindowAttributes(['class' => 'orions-modal'])
            // Texto del botón que confirma.
            ->modalSubmitActionLabel('Crear membresía')
            // Texto del botón que saltea el paso: el cliente queda creado igual.
            ->modalCancelActionLabel('Ahora no')
            // Campos del modal.
            ->schema([
                // Plan que contrata.
                Select::make('plan_id')
                    // Texto del campo.
                    ->label('Plan')
                    // Solo planes activos (los dados de baja no se ofrecen), por nombre.
                    ->options(fn (): array => Plan::query()->orderBy('nombre')->pluck('nombre', 'id')->all())
                    // Permite escribir para filtrar los planes.
                    ->searchable()
                    // Obligatorio.
                    ->required()
                    // Avisa al servidor apenas cambia, para sugerir el monto.
                    ->live()
                    // Al elegir un plan, sugiere su precio como monto; el usuario puede cambiarlo.
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('monto', Plan::query()->find($state)?->precio)),
                // Las dos fechas, lado a lado.
                Grid::make(2)
                    // Campos de la fila.
                    ->schema([
                        // Primer día de la membresía.
                        DatePicker::make('fecha_inicio')
                            // Texto del campo.
                            ->label('Fecha de inicio')
                            // Viene cargada la fecha de hoy.
                            ->default(now())
                            // Obligatoria.
                            ->required(),
                        // Último día de la membresía.
                        DatePicker::make('fecha_vencimiento')
                            // Texto del campo.
                            ->label('Fecha de vencimiento')
                            // Viene cargada la fecha de dentro de un mes.
                            ->default(now()->addMonth())
                            // No puede ser anterior a la fecha de inicio.
                            ->afterOrEqual('fecha_inicio')
                            // Obligatoria.
                            ->required(),
                    ]),
                // Monto cobrado por la membresía.
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
                    // Viene elegido efectivo, el medio más común en el mostrador.
                    ->default(PaymentMethodEnum::Efectivo)
                    // Botones uno al lado del otro.
                    ->inline()
                    // Obligatorio.
                    ->required(),
            ])
            // Lógica al confirmar el modal.
            ->action(function (array $data, array $arguments, Action $action): void {
                // Cliente al que se le crea la membresía.
                $client = Client::query()->findOrFail($arguments['client']);
                // Plan elegido (solo entre los activos).
                $plan = Plan::query()->findOrFail($data['plan_id']);
                // Usuario logueado: es quien cobró.
                $user = auth()->user();
                // Medio de pago (el campo puede devolver el caso del enum o su valor).
                $paymentMethod = $data['metodo_pago'] instanceof PaymentMethodEnum ? $data['metodo_pago'] : PaymentMethodEnum::from($data['metodo_pago']);

                // Sin usuario no hay a nombre de quién registrar el pago.
                if (! $user instanceof User) {
                    // Avisa el error en pantalla.
                    Notification::make()->title('No se pudo crear la membresía')->danger()->send();

                    // Deja el modal abierto, sin guardar nada.
                    $action->halt();
                }

                // Intenta guardar; los modelos validan plan, fechas y monto.
                try {
                    // Membresía y pago en una transacción: o se guardan los dos o ninguno.
                    [$membership, $payment] = DB::transaction(function () use ($client, $plan, $data, $paymentMethod, $user): array {
                        // Crea la membresía y pasa al cliente a Entrenando.
                        $membership = $client->startMembership($plan, Carbon::parse($data['fecha_inicio']), Carbon::parse($data['fecha_vencimiento']));
                        // Registra el pago de esa membresía a nombre del usuario logueado (monto como texto, para no perder centavos).
                        $payment = $client->registerPayment($membership, (string) $data['monto'], $paymentMethod, $user);

                        // Devuelve los dos registros creados.
                        return [$membership, $payment];
                    });
                } catch (InvalidArgumentException $exception) {
                    // Muestra el motivo.
                    Notification::make()->title('No se pudo crear la membresía')->body($exception->getMessage())->danger()->send();

                    // Deja el modal abierto, sin guardar nada.
                    $action->halt();
                }

                // Monto cobrado, formateado en pesos.
                $amount = Number::currency((float) $payment->monto, in: 'ARS', locale: 'es_AR');

                // Confirma con el plan, el vencimiento, el cobro y el nuevo estado del cliente.
                Notification::make()
                    // Título de la notificación.
                    ->title('Membresía creada y pago registrado')
                    // Detalle: plan, vencimiento, cobro y estado.
                    ->body("{$plan->nombre} · vence {$membership->fecha_vencimiento->format('d/m/Y')} · {$amount} en ".mb_strtolower($payment->metodo_pago->getLabel()).". {$client->nombre} quedó en {$client->estado->getLabel()}.")
                    // Verde.
                    ->success()
                    // La muestra.
                    ->send();
            })
            // Después de crearla abre la ficha del cliente, con la membresía y el pago ya cargados.
            ->successRedirectUrl(fn (array $arguments): string => ClientResource::getUrl('view', ['record' => $arguments['client']]));
    }

    /**
     * Pestañas arriba de la tabla para filtrar los clientes por estado con un clic.
     *
     * Precondiciones:
     * - Existe la tabla clients con la columna estado.
     *
     * Postcondiciones:
     * - Devuelve la pestaña "Todos" seguida de una por cada estado de ClientStatusEnum, en el
     *   orden del enum (Entrenando, En pausa, Activo temporal, Inactivo).
     * - Cada pestaña muestra cuántos clientes activos (no dados de baja) hay en ese estado, con
     *   el color del estado; "Todos" muestra el total.
     * - Elegir una pestaña filtra la tabla por ese estado; la búsqueda y los filtros siguen
     *   funcionando dentro de la pestaña.
     * - Los contadores salen de una sola consulta agrupada.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        // Cuenta los clientes por estado en una sola consulta: ["entrenando" => 12, "en_pausa" => 3, ...].
        $counts = Client::query()
            // Sin orden, para que la agrupación sea válida.
            ->reorder()
            // Agrupa por estado.
            ->groupBy('estado')
            // Trae "estado => cantidad" directo de la base, sin pasar por el cast del modelo.
            ->toBase()
            // Columnas: el estado y su cantidad.
            ->selectRaw('estado, count(*) as total')
            // Lo convierte en un mapa estado => cantidad.
            ->pluck('total', 'estado');

        // La primera pestaña muestra todos los clientes.
        $tabs = [
            // Sin filtro; el contador es la suma de todos los estados.
            'todos' => Tab::make('Todos')->badge((int) $counts->sum()),
        ];

        // Agrega una pestaña por cada estado posible.
        foreach (ClientStatusEnum::cases() as $status) {
            // La clave de la pestaña (va en la URL) es el valor del estado.
            $tabs[$status->value] = Tab::make($status->getLabel())
                // Cantidad de clientes en ese estado (0 si no hay ninguno).
                ->badge((int) ($counts[$status->value] ?? 0))
                // Mismo color que el badge del estado en la tabla.
                ->badgeColor($status->getColor())
                // Al elegirla, filtra la tabla por ese estado.
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('estado', $status));
        }

        // Devuelve las pestañas en orden.
        return $tabs;
    }
}
