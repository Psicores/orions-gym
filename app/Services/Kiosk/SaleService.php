<?php

namespace App\Services\Kiosk;

use App\Enums\PaymentMethodEnum;
use App\Enums\SalePaymentStatusEnum;
use App\Enums\SaleStatusEnum;
use App\Enums\StockMovementTypeEnum;
use App\Exceptions\SaleException;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de entrada para vender en el kiosco.
 *
 * quote() calcula precios y totales para mostrar el carrito; checkout() vuelve a calcular todo con
 * los productos bloqueados y guarda venta, renglones, movimientos de stock y cobros juntos.
 *
 * Reglas de precio (ver Product::priceFor()): un socio al día (Client::hasKioskDiscount()) paga el
 * precio de lista menos el descuento de socios; el precio en efectivo se usa solo si toda la venta se paga en efectivo; los descuentos
 * no se suman, se cobra el más barato.
 */
class SaleService
{
    /** Máximo de unidades de un producto en una venta (evita errores de tipeo como 1000). */
    public const int MAX_CANTIDAD = 999;

    /**
     * Calcula el presupuesto de un carrito sin guardar nada.
     *
     * Precondiciones:
     * - $cart es [id de producto => unidades].
     *
     * Postcondiciones:
     * - Devuelve los renglones en el orden del carrito con el precio de lista, el precio a cobrar
     *   (según $metodo y si hay socio) y las unidades que faltan de stock.
     * - No modifica nada.
     *
     * @param  array<int|string, int|string>  $cart  Carrito.
     * @param  Client|null  $client  Socio, si la venta es a un socio.
     * @param  PaymentMethodEnum|null  $metodo  Medio con el que se paga todo (null si es mixto o no se eligió).
     * @return SaleQuote El presupuesto.
     *
     * @throws SaleException Si el carrito está vacío, tiene cantidades inválidas o productos que no se venden.
     */
    public function quote(array $cart, ?Client $client = null, ?PaymentMethodEnum $metodo = null): SaleQuote
    {
        // Valida el carrito y trae los productos.
        $cart = $this->normalizeCart($cart);

        // Arma el presupuesto con esos productos.
        return $this->buildQuote($cart, Product::query()->whereKey(array_keys($cart))->get(), $client, $metodo);
    }

    /**
     * Registra una venta cobrada.
     *
     * Precondiciones:
     * - $seller es la cuenta del personal que vende.
     * - $cart es [id de producto => unidades]; $payments es una lista de
     *   ['metodo' => PaymentMethodEnum|string, 'monto' => importe en pesos].
     *
     * Postcondiciones:
     * - En una transacción: bloquea los productos (dos ventas a la vez no se pisan), recalcula los
     *   precios, controla el stock según el modo (estricto: no deja vender lo que falta; flexible:
     *   vende y el stock puede quedar negativo), y crea la venta pagada, sus renglones con copia de
     *   nombre y precios, un movimiento de venta por cada producto que controla stock y los cobros
     *   aprobados.
     * - Los cobros tienen que sumar exactamente el total.
     * - Si algo falla no queda nada guardado.
     *
     * @param  User  $seller  Quién vende.
     * @param  array<int|string, int|string>  $cart  Carrito.
     * @param  list<array{metodo: PaymentMethodEnum|string, monto: string|int|float}>  $payments  Cobros.
     * @param  Client|null  $client  Socio, si la venta es a un socio.
     * @return Sale La venta guardada, con renglones y cobros cargados.
     *
     * @throws SaleException Con un mensaje para mostrar si la venta no se puede hacer.
     */
    public function checkout(User $seller, array $cart, array $payments, ?Client $client = null): Sale
    {
        // Valida antes de abrir la transacción.
        $cart = $this->normalizeCart($cart);
        $payments = $this->normalizePayments($payments);

        // Un socio dado de baja no compra como socio.
        if ($client?->trashed()) {
            throw new SaleException('Ese socio está dado de baja.');
        }

        return DB::transaction(function () use ($seller, $cart, $payments, $client): Sale {
            // Bloquea los productos en orden de id (evita bloqueos cruzados entre dos ventas).
            $products = Product::query()->whereKey(array_keys($cart))->orderBy('id')->lockForUpdate()->get();

            // Precios con el medio de pago que corresponde.
            $quote = $this->buildQuote($cart, $products, $client, $this->pricingMethod($payments));

            // En modo estricto no se vende lo que falta.
            if (Product::stockIsStrict() && $quote->shortages() !== []) {
                throw new SaleException('No hay stock suficiente de: '.implode(', ', array_map(
                    fn (SaleQuoteLine $line): string => "{$line->product->nombre} (hay ".max(0, $line->product->stock).')',
                    $quote->shortages(),
                )).'.');
            }

            // Los cobros tienen que cubrir exactamente el total.
            $cobrado = array_sum(array_column($payments, 'cents'));
            if ($cobrado !== $quote->totalCents()) {
                throw new SaleException(sprintf(
                    'Los cobros suman $ %s y el total es $ %s.',
                    number_format($cobrado / 100, 2, ',', '.'),
                    number_format($quote->totalCents() / 100, 2, ',', '.'),
                ));
            }

            // La venta.
            $sale = Sale::create([
                'client_id' => $client?->getKey(),
                'vendido_por_user_id' => $seller->getKey(),
                'estado' => SaleStatusEnum::Pagada,
                'subtotal' => SaleQuote::toAmount($quote->subtotalCents()),
                'descuento' => SaleQuote::toAmount($quote->descuentoCents()),
                'total' => SaleQuote::toAmount($quote->totalCents()),
            ]);

            // Cada renglón, con su movimiento de stock.
            foreach ($quote->lines as $line) {
                $item = $sale->items()->create([
                    'product_id' => $line->product->getKey(),
                    // Copia del nombre del momento.
                    'nombre' => $line->product->nombre,
                    'cantidad' => $line->cantidad,
                    'precio_lista' => SaleQuote::toAmount($line->precioListaCents),
                    'precio_unitario' => SaleQuote::toAmount($line->precioUnitarioCents),
                    'subtotal' => SaleQuote::toAmount($line->subtotalCents()),
                ]);

                // Descuenta el stock si el producto lo controla.
                if ($line->product->controla_stock) {
                    $line->product->moveStock(-$line->cantidad, StockMovementTypeEnum::Venta, $seller, "Venta #{$sale->getKey()}", $item->getKey());
                }
            }

            // Cobros manuales: aprobados al momento.
            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'metodo' => $payment['metodo'],
                    'monto' => SaleQuote::toAmount($payment['cents']),
                    'estado' => SalePaymentStatusEnum::Aprobado,
                    'pagado_at' => now(),
                    'registrado_por_user_id' => $seller->getKey(),
                ]);
            }

            // Devuelve la venta completa.
            return $sale->load(['items', 'payments']);
        });
    }

    /**
     * Anula una venta: devuelve el stock y marca los cobros como reintegrados.
     *
     * Precondiciones:
     * - $user es la cuenta que anula (el permiso lo controla quien llama; ver SalePolicy::void()).
     * - $motivo no está vacío.
     *
     * Postcondiciones:
     * - En una transacción, con la venta bloqueada: la venta queda Anulada con quién, cuándo y el
     *   motivo; cada renglón de un producto que controla stock suma sus unidades con un movimiento
     *   de anulación; los cobros aprobados pasan a reintegrados y los pendientes a rechazados.
     * - La venta anulada no suma en los reportes. No se puede deshacer.
     *
     * @param  Sale  $sale  Venta a anular.
     * @param  User  $user  Quién la anula.
     * @param  string  $motivo  Por qué.
     * @return Sale La venta anulada, con renglones y cobros cargados.
     *
     * @throws SaleException Si ya estaba anulada o falta el motivo.
     */
    public function void(Sale $sale, User $user, string $motivo): Sale
    {
        // Sin motivo no se anula.
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new SaleException('Escribí el motivo de la anulación.');
        }

        return DB::transaction(function () use ($sale, $user, $motivo): Sale {
            // Vuelve a leer la venta bloqueándola: dos anulaciones a la vez no devuelven el stock dos veces.
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            // Una sola vez.
            if ($locked->estado === SaleStatusEnum::Anulada) {
                throw new SaleException("La venta #{$locked->getKey()} ya estaba anulada.");
            }

            // Marca la venta.
            $locked->update([
                'estado' => SaleStatusEnum::Anulada,
                'anulada_at' => now(),
                'anulada_por_user_id' => $user->getKey(),
                'motivo_anulacion' => mb_substr($motivo, 0, 255),
            ]);

            // Devuelve el stock de cada renglón (también de productos dados de baja).
            foreach ($locked->items()->with('product')->get() as $item) {
                if ($item->product?->controla_stock) {
                    $item->product->moveStock($item->cantidad, StockMovementTypeEnum::Anulacion, $user, "Anulación de la venta #{$locked->getKey()}", $item->getKey());
                }
            }

            // Cobros: lo recibido se reintegra; lo que no llegó a acreditarse, se rechaza.
            $locked->payments()->where('estado', SalePaymentStatusEnum::Aprobado)->update(['estado' => SalePaymentStatusEnum::Reintegrado]);
            $locked->payments()->where('estado', SalePaymentStatusEnum::Pendiente)->update(['estado' => SalePaymentStatusEnum::Rechazado]);

            // Devuelve la venta actualizada.
            $sale->setRawAttributes($locked->getAttributes(), true);

            return $sale->load(['items', 'payments']);
        });
    }

    /**
     * Medio que define los precios: efectivo solo si todo se paga en efectivo.
     *
     * Precondiciones:
     * - $payments ya pasó por normalizePayments().
     *
     * Postcondiciones:
     * - Devuelve el medio si todos los cobros son del mismo medio; si son mixtos, null (precio de
     *   lista). No modifica nada.
     *
     * @param  list<array{metodo: PaymentMethodEnum, cents: int}>  $payments  Cobros.
     * @return PaymentMethodEnum|null Medio para calcular precios.
     */
    private function pricingMethod(array $payments): ?PaymentMethodEnum
    {
        // Medios distintos usados.
        $metodos = array_unique(array_map(fn (array $payment): string => $payment['metodo']->value, $payments));

        // Uno solo: ese; varios: ninguno.
        return count($metodos) === 1 ? PaymentMethodEnum::from($metodos[0]) : null;
    }

    /**
     * Arma el presupuesto con productos ya traídos de la base.
     *
     * Precondiciones:
     * - $cart ya pasó por normalizeCart().
     *
     * Postcondiciones:
     * - Devuelve un renglón por producto del carrito, en su orden. No modifica nada.
     *
     * @param  array<int, int>  $cart  Carrito validado.
     * @param  Collection<int, Product>  $products  Productos del carrito.
     * @param  Client|null  $client  Socio.
     * @param  PaymentMethodEnum|null  $metodo  Medio para calcular precios.
     * @return SaleQuote El presupuesto.
     *
     * @throws SaleException Si falta un producto (dado de baja) o está apagado.
     */
    private function buildQuote(array $cart, Collection $products, ?Client $client, ?PaymentMethodEnum $metodo): SaleQuote
    {
        // Productos por id.
        $products = $products->keyBy('id');
        // Descuento solo para socios al día (la venta queda vinculada al cliente igual).
        $esSocio = $client?->hasKioskDiscount() === true;
        // Renglones.
        $lines = [];

        foreach ($cart as $productId => $cantidad) {
            // Producto de este renglón.
            $product = $products->get($productId);

            // Dado de baja o inexistente.
            if ($product === null) {
                throw new SaleException('Uno de los productos ya no está a la venta. Sacalo del carrito.');
            }

            // Apagado.
            if (! $product->activo) {
                throw new SaleException("{$product->nombre} no está a la venta.");
            }

            $lines[] = new SaleQuoteLine(
                product: $product,
                cantidad: $cantidad,
                precioListaCents: SaleQuote::toCents($product->precio),
                precioUnitarioCents: SaleQuote::toCents($product->priceFor($metodo, $esSocio)),
                faltante: $product->stockShortage($cantidad),
            );
        }

        return new SaleQuote($lines);
    }

    /**
     * Valida el carrito.
     *
     * Postcondiciones:
     * - Devuelve [id de producto (int) => unidades (int)]. No modifica nada.
     *
     * @param  array<int|string, int|string>  $cart  Carrito tal como llega.
     * @return array<int, int> Carrito validado.
     *
     * @throws SaleException Si está vacío o alguna cantidad no es un entero entre 1 y MAX_CANTIDAD.
     */
    private function normalizeCart(array $cart): array
    {
        // Sin productos no hay venta.
        if ($cart === []) {
            throw new SaleException('El carrito está vacío.');
        }

        $normalized = [];

        foreach ($cart as $productId => $cantidad) {
            // Solo enteros positivos razonables.
            if (filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_CANTIDAD]]) === false) {
                throw new SaleException('Cada producto tiene que llevar entre 1 y '.self::MAX_CANTIDAD.' unidades.');
            }

            $normalized[(int) $productId] = (int) $cantidad;
        }

        return $normalized;
    }

    /**
     * Valida los cobros.
     *
     * Postcondiciones:
     * - Devuelve una lista de ['metodo' => PaymentMethodEnum, 'cents' => int > 0]. No modifica nada.
     *
     * @param  list<array{metodo: PaymentMethodEnum|string, monto: string|int|float}>  $payments  Cobros tal como llegan.
     * @return list<array{metodo: PaymentMethodEnum, cents: int}> Cobros validados.
     *
     * @throws SaleException Si no hay cobros, un medio no existe o un monto no es mayor que 0.
     */
    private function normalizePayments(array $payments): array
    {
        // Sin cobro no hay venta (el fiado llega más adelante).
        if ($payments === []) {
            throw new SaleException('Falta elegir cómo paga.');
        }

        $normalized = [];

        foreach ($payments as $payment) {
            // Medio conocido.
            $metodo = $payment['metodo'] ?? null;
            $metodo = is_string($metodo) ? PaymentMethodEnum::tryFrom($metodo) : $metodo;

            if (! $metodo instanceof PaymentMethodEnum) {
                throw new SaleException('Medio de pago desconocido.');
            }

            // Monto positivo.
            $monto = $payment['monto'] ?? null;
            $cents = is_numeric($monto) ? SaleQuote::toCents($monto) : 0;

            if ($cents <= 0) {
                throw new SaleException('Cada cobro tiene que ser mayor que $ 0.');
            }

            $normalized[] = ['metodo' => $metodo, 'cents' => $cents];
        }

        return $normalized;
    }
}
