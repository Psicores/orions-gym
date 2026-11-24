<?php

namespace App\Filament\Resources\Products\Actions;

use App\Enums\StockMovementTypeEnum;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Botones de stock de cada producto: cargar mercadería, ajustar después de contar y ver el
 * historial. Solo aparecen en productos que controlan stock y no están dados de baja.
 */
class StockActions
{
    /**
     * "Cargar mercadería": suma unidades que llegaron. Lo puede hacer cualquiera del personal
     * (la mercadería suele llegar a recepción); queda registrado quién la cargó.
     *
     * Precondiciones:
     * - Hay una cuenta del personal con sesión.
     *
     * Postcondiciones:
     * - Al confirmar, registra una entrada por la cantidad (1 a 99.999) con la nota opcional y
     *   avisa el stock nuevo.
     *
     * @return Action El botón configurado.
     */
    public static function receive(): Action
    {
        return Action::make('receiveStock')
            ->label('Cargar mercadería')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            // Solo en productos vigentes que cuentan stock.
            ->visible(fn (Product $record): bool => self::tracksStock($record))
            ->modalHeading(fn (Product $record): string => "Cargar mercadería: {$record->nombre}")
            ->modalDescription(fn (Product $record): string => "Stock actual: {$record->stock}.")
            ->modalWidth(Width::Medium)
            ->modalFooterActionsAlignment(Alignment::End)
            ->extraModalWindowAttributes(['class' => 'orions-modal'])
            ->modalSubmitActionLabel('Cargar')
            ->schema([
                // Unidades que llegaron.
                TextInput::make('cantidad')
                    ->label('Unidades que llegaron')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(99999)
                    ->required()
                    ->autofocus(),
                // Detalle opcional.
                TextInput::make('nota')
                    ->label('Nota')
                    ->placeholder('Ej.: proveedor, número de remito')
                    ->maxLength(255),
            ])
            ->action(function (Product $record, array $data): void {
                // Registra la entrada.
                $record->moveStock((int) $data['cantidad'], StockMovementTypeEnum::Entrada, self::user(), $data['nota'] ?? null);

                // Confirma con el stock nuevo.
                Notification::make()
                    ->success()
                    ->title("Cargadas {$data['cantidad']} unidades. Stock: {$record->stock}.")
                    ->send();
            });
    }

    /**
     * "Ajustar stock": deja el stock en lo que se contó (rotura, vencido, diferencia). Solo para
     * administradores, con motivo obligatorio.
     *
     * Precondiciones:
     * - Hay una cuenta del personal con sesión.
     *
     * Postcondiciones:
     * - Al confirmar, registra un ajuste por la diferencia entre lo contado y el stock actual (si
     *   no hay diferencia no registra nada) y avisa el resultado.
     *
     * @return Action El botón configurado.
     */
    public static function adjust(): Action
    {
        return Action::make('adjustStock')
            ->label('Ajustar stock')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            // Solo administradores y en productos vigentes que cuentan stock.
            ->visible(fn (Product $record): bool => self::tracksStock($record) && self::isAdmin())
            ->modalHeading(fn (Product $record): string => "Ajustar stock: {$record->nombre}")
            ->modalDescription(fn (Product $record): string => "Stock según el sistema: {$record->stock}. Cargá lo que contaste.")
            ->modalWidth(Width::Medium)
            ->modalFooterActionsAlignment(Alignment::End)
            ->extraModalWindowAttributes(['class' => 'orions-modal'])
            ->modalSubmitActionLabel('Ajustar')
            ->schema([
                // Lo que hay de verdad.
                TextInput::make('contado')
                    ->label('Unidades contadas')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(99999)
                    ->required()
                    ->autofocus(),
                // Por qué cambia.
                TextInput::make('nota')
                    ->label('Motivo')
                    ->placeholder('Ej.: conteo mensual, 2 latas rotas')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Product $record, array $data): void {
                // Registra la diferencia, si la hay.
                $movement = $record->adjustStockTo((int) $data['contado'], self::user(), $data['nota']);

                // Avisa qué pasó.
                Notification::make()
                    ->success()
                    ->title($movement === null
                        ? 'El stock ya coincidía; no se registró nada.'
                        : sprintf('Stock ajustado a %d (%+d).', $record->stock, $movement->cantidad))
                    ->send();
            });
    }

    /**
     * "Movimientos": abre el historial de stock filtrado por el producto.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve un enlace a la pantalla de movimientos con el filtro del producto. Visible para
     *   todo el personal en productos que controlan stock.
     *
     * @return Action El botón configurado.
     */
    public static function history(): Action
    {
        return Action::make('stockHistory')
            ->label('Movimientos')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            // Solo si cuenta stock (dado de baja también, para revisar).
            ->visible(fn (Product $record): bool => $record->controla_stock)
            ->url(fn (Product $record): string => StockMovementResource::getUrl(parameters: [
                'filters' => ['product_id' => ['value' => $record->getKey()]],
            ]));
    }

    /**
     * Indica si al producto se le puede mover el stock desde el panel.
     *
     * Postcondiciones:
     * - true si controla stock y no está dado de baja. No modifica nada.
     */
    private static function tracksStock(Product $record): bool
    {
        // Vigente y con control de stock.
        return $record->controla_stock && ! $record->trashed();
    }

    /**
     * Indica si quien usa el panel es administrador.
     *
     * Postcondiciones:
     * - true solo con una cuenta del personal administradora con sesión. No modifica nada.
     */
    private static function isAdmin(): bool
    {
        // Sesión del panel.
        $user = Auth::guard('web')->user();

        // Cuenta del personal con rol de administrador.
        return $user instanceof User && $user->isSiteAdmin();
    }

    /**
     * Cuenta del personal con sesión, para registrar quién movió el stock.
     *
     * Precondiciones:
     * - Se llama desde una acción del panel (hay sesión).
     *
     * Postcondiciones:
     * - Devuelve la cuenta; sin sesión del personal corta con 403. No modifica nada.
     */
    private static function user(): User
    {
        // Sesión del panel.
        $user = Auth::guard('web')->user();

        // Sin cuenta del personal no se mueve nada.
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
