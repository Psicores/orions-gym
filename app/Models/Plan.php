<?php

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plan del catálogo: lo que el gimnasio vende (por ejemplo "3 veces por semana" o "Libre").
 *
 * creditos_otorgados es la cantidad de clases que habilita el plan; por convención,
 * 999 representa un plan libre (sin límite práctico).
 *
 * Usa baja lógica: un plan dado de baja deja de ofrecerse pero se conserva, para que las
 * membresías que lo usaron sigan mostrando su historial.
 *
 * precio es el precio de lista (transferencia y cualquier otro medio); precio_efectivo es
 * el precio con descuento por pagar en efectivo, y es opcional: si está vacío, en efectivo
 * se cobra el precio de lista.
 */
#[Fillable(['nombre', 'precio', 'precio_efectivo', 'creditos_otorgados'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - La columna precio es decimal(10,2) y creditos_otorgados es un entero sin signo.
     *
     * Postcondiciones:
     * - $plan->precio devuelve un string con exactamente 2 decimales (por ejemplo "25000.00"),
     *   sin errores de redondeo de punto flotante.
     * - $plan->creditos_otorgados devuelve un int.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // El precio se maneja como decimal con 2 decimales (string exacto, no float).
            'precio' => 'decimal:2',
            // El precio en efectivo, igual; si la columna está vacía devuelve null.
            'precio_efectivo' => 'decimal:2',
            // Los créditos se manejan como número entero.
            'creditos_otorgados' => 'integer',
        ];
    }

    /**
     * Devuelve el precio del plan que corresponde al medio de pago elegido.
     *
     * Precondiciones:
     * - El plan tiene precio cargado; precio_efectivo puede estar vacío.
     * - $metodoPago es un caso del enum, su valor de texto ("efectivo", "transferencia") o null.
     *
     * Postcondiciones:
     * - Si el medio es efectivo y el plan tiene precio_efectivo, devuelve ese precio.
     * - En cualquier otro caso (otro medio, medio vacío o desconocido, o plan sin precio en
     *   efectivo) devuelve el precio de lista.
     * - El resultado es un string con 2 decimales (por ejemplo "53000.00").
     * - No modifica datos.
     *
     * @param  PaymentMethodEnum|string|null  $metodoPago  Medio de pago elegido en el formulario.
     * @return string Precio a sugerir como monto.
     */
    public function priceFor(PaymentMethodEnum|string|null $metodoPago): string
    {
        // Convierte el valor de texto al caso del enum (null si está vacío o no existe).
        $metodoPago = is_string($metodoPago) ? PaymentMethodEnum::tryFrom($metodoPago) : $metodoPago;

        // En efectivo, y solo si el plan tiene ese precio cargado, se usa el precio en efectivo.
        if ($metodoPago === PaymentMethodEnum::Efectivo && $this->precio_efectivo !== null) {
            // Precio con descuento por pago en efectivo.
            return $this->precio_efectivo;
        }

        // En el resto de los casos, el precio de lista.
        return $this->precio;
    }

    /**
     * Membresías que se contrataron con este plan.
     *
     * Precondiciones:
     * - Existe la tabla memberships con la columna plan_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "un plan tiene muchas membresías", sin ejecutar la consulta.
     * - $plan->memberships devuelve una colección (vacía si nadie lo contrató).
     * - No modifica datos.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        // Busca en memberships las filas cuyo plan_id es el id de este plan.
        return $this->hasMany(Membership::class);
    }
}
