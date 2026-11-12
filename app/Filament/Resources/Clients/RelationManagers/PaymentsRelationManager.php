<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\PaymentMethodEnum;
use App\Models\Payment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Attributes\On;

/**
 * Pestaña "Pagos" dentro de la ficha de un cliente: historial de toda la plata que entregó,
 * con fecha, monto, medio, membresía y quién se la cobró.
 *
 * Es de solo lectura a propósito: los pagos se cargan con la acción "Registrar pago" y no se
 * editan ni se borran desde acá, para que el historial de caja no se pueda alterar.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pagos';

    protected static ?string $modelLabel = 'pago';

    protected static ?string $pluralModelLabel = 'pagos';

    /**
     * Arma la tabla con el historial de pagos del cliente.
     *
     * Precondiciones:
     * - Se muestra dentro de la ficha de un cliente existente.
     *
     * Postcondiciones:
     * - Lista solo los pagos de ese cliente, del más nuevo al más viejo.
     * - Muestra fecha y hora, monto en pesos, medio de pago, plan de la membresía (aunque el
     *   plan esté dado de baja) y el usuario que lo cobró.
     * - Muestra al pie el total cobrado de los pagos listados.
     * - Permite filtrar por medio de pago.
     * - No ofrece crear, editar ni borrar pagos.
     *
     * @param  Table  $table  Tabla vacía que entrega Filament.
     * @return Table La tabla configurada con columnas y filtros.
     */
    public function table(Table $table): Table
    {
        // Devuelve la tabla recibida con columnas y filtros cargados.
        return $table
            // Usa la fecha del pago como título de cada fila en mensajes.
            ->recordTitle(fn (Payment $record): string => 'pago del '.$record->fecha_pago->format('d/m/Y H:i'))
            // Columnas de la tabla.
            ->columns([
                // Cuándo se cobró.
                TextColumn::make('fecha_pago')
                    // Encabezado de la columna.
                    ->label('Fecha')
                    // Formato argentino con hora.
                    ->dateTime('d/m/Y H:i')
                    // Permite ordenar por fecha.
                    ->sortable(),
                // Cuánto se cobró.
                TextColumn::make('monto')
                    // Encabezado de la columna.
                    ->label('Monto')
                    // Formato en pesos argentinos (por ejemplo $ 25.000,50).
                    ->money('ARS', locale: 'es_AR')
                    // Permite ordenar por monto.
                    ->sortable()
                    // Al pie, suma de los montos listados.
                    ->summarize(
                        // Suma los montos de los pagos que muestra la tabla.
                        Sum::make()
                            // Texto del total.
                            ->label('Total')
                            // Mismo formato en pesos.
                            ->money('ARS', locale: 'es_AR'),
                    ),
                // Cómo pagó.
                TextColumn::make('metodo_pago')
                    // Encabezado de la columna.
                    ->label('Medio')
                    // Etiqueta con nombre, color e ícono definidos en el enum.
                    ->badge(),
                // A qué membresía se imputó (se muestra el nombre de su plan).
                TextColumn::make('membership.plan.nombre')
                    // Encabezado de la columna.
                    ->label('Membresía'),
                // Quién cobró.
                TextColumn::make('registradoPor.name')
                    // Encabezado de la columna.
                    ->label('Cobró'),
            ])
            // Al entrar, muestra primero el pago más reciente.
            ->defaultSort('fecha_pago', 'desc')
            // Filtros disponibles en la tabla.
            ->filters([
                // Filtro por medio de pago (efectivo / transferencia).
                SelectFilter::make('metodo_pago')
                    // Texto del filtro.
                    ->label('Medio de pago')
                    // Opciones tomadas del enum.
                    ->options(PaymentMethodEnum::class),
            ]);
    }

    /**
     * Vuelve a cargar la tabla cuando se registra un pago en otra parte de la página.
     *
     * Precondiciones:
     * - La acción "Registrar pago" emitió el evento "pago-registrado" en esta página.
     *
     * Postcondiciones:
     * - Livewire vuelve a dibujar este componente, así el pago nuevo aparece sin recargar la página.
     * - No modifica datos.
     */
    #[On('pago-registrado')]
    public function refreshAfterPaymentRegistered(): void
    {
        // No hace falta código: atender el evento ya provoca que Livewire redibuje la tabla.
    }
}
