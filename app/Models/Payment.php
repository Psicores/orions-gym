<?php

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pago: plata que entregó un cliente para una de sus membresías.
 *
 * Guarda cuánto pagó, cómo, cuándo y qué usuario del staff lo cobró
 * (registrado_por_user_id), para poder rendir cuentas de la caja.
 */
#[Fillable([
    'client_id',
    'membership_id',
    'monto',
    'metodo_pago',
    'fecha_pago',
    'registrado_por_user_id',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - monto guarda un decimal con 2 decimales.
     * - metodo_pago guarda uno de los valores de PaymentMethodEnum (efectivo, transferencia).
     * - fecha_pago guarda fecha y hora.
     *
     * Postcondiciones:
     * - monto se lee como texto con 2 decimales (por ejemplo "25000.50"), sin errores de redondeo.
     * - $payment->metodo_pago devuelve un caso de PaymentMethodEnum, y al asignarle un caso
     *   se guarda su valor de texto.
     * - fecha_pago se lee como objeto Carbon.
     * - Si la base tiene un medio que no existe en el enum, leerlo lanza un ValueError.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // Monto como decimal exacto de 2 posiciones (plata: nunca float).
            'monto' => 'decimal:2',
            // Medio de pago como el enum PaymentMethodEnum en lugar de texto suelto.
            'metodo_pago' => PaymentMethodEnum::class,
            // Fecha y hora del cobro (Carbon).
            'fecha_pago' => 'datetime',
        ];
    }
}
