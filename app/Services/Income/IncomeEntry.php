<?php

namespace App\Services\Income;

use App\Enums\PaymentMethodEnum;
use Carbon\CarbonInterface;

/**
 * Un ingreso de plata, venga de una cuota o del kiosco, con lo necesario para rendir la caja.
 */
final readonly class IncomeEntry
{
    /** Origen: pago de cuota. */
    public const string CUOTA = 'cuota';

    /** Origen: cobro de una venta del kiosco. */
    public const string KIOSCO = 'kiosco';

    /**
     * @param  CarbonInterface  $fecha  Cuándo entró.
     * @param  string  $origen  self::CUOTA o self::KIOSCO.
     * @param  PaymentMethodEnum  $metodo  Medio de pago.
     * @param  int  $cents  Importe en centavos.
     * @param  int|null  $userId  Quién lo cobró (null si lo confirmó una pasarela sola).
     * @param  string  $userName  Nombre de quien lo cobró ("Pasarela" si no hay).
     * @param  string  $detalle  A quién o qué ("Pérez, Juan" o "Venta #12").
     */
    public function __construct(
        public CarbonInterface $fecha,
        public string $origen,
        public PaymentMethodEnum $metodo,
        public int $cents,
        public ?int $userId,
        public string $userName,
        public string $detalle,
    ) {}
}
