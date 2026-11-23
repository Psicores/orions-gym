<?php

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use App\Enums\SalePaymentStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cobro de una venta del kiosco.
 *
 * Es genérico desde el inicio para sumar pasarelas de pago sin rediseñar: un cobro manual nace
 * aprobado y sin proveedor; uno de pasarela nace pendiente con proveedor y referencia_externa
 * (únicas juntas: un aviso repetido no lo registra dos veces) y lo aprueba el aviso de la pasarela.
 */
#[Fillable([
    'sale_id', 'metodo', 'monto', 'estado', 'proveedor', 'referencia_externa', 'payload', 'pagado_at',
    'registrado_por_user_id', 'cash_session_id',
])]
class SalePayment extends Model
{
    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            // Medio como caso del enum.
            'metodo' => PaymentMethodEnum::class,
            // Estado como caso del enum.
            'estado' => SalePaymentStatusEnum::class,
            // Importe exacto.
            'monto' => 'decimal:2',
            // Datos crudos de la pasarela.
            'payload' => 'array',
            // Cuándo se acreditó.
            'pagado_at' => 'datetime',
        ];
    }

    /**
     * Venta cobrada.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        // Columna sale_id.
        return $this->belongsTo(Sale::class);
    }

    /**
     * Quién lo registró (null si lo confirmó una pasarela; incluye cuentas dadas de baja).
     *
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        // Columna registrado_por_user_id, con las cuentas dadas de baja.
        return $this->belongsTo(User::class, 'registrado_por_user_id')->withTrashed();
    }
}
