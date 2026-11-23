<?php

namespace App\Models;

use App\Enums\SaleStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venta del kiosco: uno o más productos, opcionalmente a un socio, con uno o más cobros.
 *
 * Se crea solo con App\Services\Kiosk\SaleService::checkout(), que calcula los precios, mueve el
 * stock y registra los cobros en una sola transacción. No se borra: se anula (KIO-05).
 *
 * subtotal es la suma a precio de lista; descuento, lo que se descontó (socio o efectivo); total,
 * lo cobrado. Siempre total = subtotal − descuento = suma de los renglones.
 */
#[Fillable([
    'client_id', 'vendido_por_user_id', 'estado', 'subtotal', 'descuento', 'total', 'cash_session_id',
    'anulada_at', 'anulada_por_user_id', 'motivo_anulacion',
])]
class Sale extends Model
{
    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            // Estado como caso del enum.
            'estado' => SaleStatusEnum::class,
            // Importes exactos.
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            // Fecha de anulación.
            'anulada_at' => 'datetime',
        ];
    }

    /**
     * Renglones de la venta.
     *
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        // Filas de sale_items con este sale_id.
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Cobros de la venta.
     *
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        // Filas de sale_payments con este sale_id.
        return $this->hasMany(SalePayment::class);
    }

    /**
     * Socio al que se le vendió (null en ventas de mostrador; incluye socios dados de baja).
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        // Columna client_id, con los dados de baja.
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * Quién vendió (incluye cuentas dadas de baja).
     *
     * @return BelongsTo<User, $this>
     */
    public function vendidoPor(): BelongsTo
    {
        // Columna vendido_por_user_id, con las cuentas dadas de baja.
        return $this->belongsTo(User::class, 'vendido_por_user_id')->withTrashed();
    }

    /**
     * Quién la anuló (incluye cuentas dadas de baja).
     *
     * @return BelongsTo<User, $this>
     */
    public function anuladaPor(): BelongsTo
    {
        // Columna anulada_por_user_id, con las cuentas dadas de baja.
        return $this->belongsTo(User::class, 'anulada_por_user_id')->withTrashed();
    }
}
