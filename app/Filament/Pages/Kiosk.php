<?php

namespace App\Filament\Pages;

use App\Enums\PaymentMethodEnum;
use App\Enums\SaleStatusEnum;
use App\Exceptions\SaleException;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Kiosk\SaleQuote;
use App\Services\Kiosk\SaleService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * Pantalla de venta del kiosco (punto de venta), pensada para PC y tablet.
 *
 * A la izquierda, la grilla de productos (destacados y más vendidos primero) con botones por
 * categoría y un buscador que también recibe el lector de códigos USB (escribe el código y manda
 * Enter). A la derecha, el carrito con el socio opcional, el medio de pago y el botón de cobrar.
 * Los precios y el cobro los calcula App\Services\Kiosk\SaleService. La usa todo el personal.
 */
class Kiosk extends Page
{
    protected string $view = 'filament.pages.kiosk';

    // URL en castellano: /admin/kiosco.
    protected static ?string $slug = 'kiosco';

    protected static ?string $title = 'Kiosco';

    protected static ?string $navigationLabel = 'Vender';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    // Grupo "Kiosco", primero.
    protected static string|UnitEnum|null $navigationGroup = ProductResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 0;

    // Usa todo el ancho: grilla y carrito lado a lado.
    protected Width|string|null $maxContentWidth = Width::Full;

    /** Productos que muestra la grilla como máximo. */
    public const int GRID_LIMIT = 48;

    /** Días hacia atrás para ordenar por más vendidos. */
    public const int BEST_SELLER_DAYS = 30;

    /** Medio mixto: parte en efectivo y el resto por transferencia. */
    public const string MIXED = 'mixto';

    /** @var array<int, int> Carrito: [id de producto => unidades]. */
    public array $cart = [];

    /** Categoría elegida para filtrar la grilla (null = todas). */
    public ?int $categoryId = null;

    /** Texto del buscador (o código que mandó el lector). */
    public string $search = '';

    /** Socio de la venta (null = venta de mostrador). */
    public ?int $clientId = null;

    /** Texto para buscar al socio (DNI o apellido). */
    public string $clientSearch = '';

    /** Medio de pago: "efectivo", "transferencia" o "mixto". */
    public string $metodo = 'efectivo';

    /** En pago mixto, cuánto paga en efectivo (el resto va por transferencia). */
    public ?string $montoEfectivo = null;

    /** En efectivo, con cuánto paga (para calcular el vuelto); opcional. */
    public ?string $pagaCon = null;

    /**
     * Suma una unidad de un producto al carrito.
     *
     * Precondiciones:
     * - $productId es un producto activo.
     *
     * Postcondiciones:
     * - Si existe y está activo, el carrito tiene una unidad más (hasta SaleService::MAX_CANTIDAD).
     * - El buscador queda vacío y con el foco, listo para el próximo producto o código.
     *
     * @param  int  $productId  Producto.
     */
    public function add(int $productId): void
    {
        // Solo productos que se venden.
        if (! Product::query()->whereKey($productId)->where('activo', true)->exists()) {
            return;
        }

        // Una más, sin pasar el tope.
        $this->cart[$productId] = min(SaleService::MAX_CANTIDAD, ($this->cart[$productId] ?? 0) + 1);
        // Listo para el siguiente.
        $this->search = '';
        $this->dispatch('kiosk-focus-search');
    }

    /**
     * Enter en el buscador: agrega el producto del código leído o el único que coincide.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Si el texto es exactamente el código de barras de un producto activo, lo agrega.
     * - Si no, y la búsqueda deja un solo producto en la grilla, agrega ese.
     * - Si no, avisa que no encontró el código y deja el texto para corregirlo.
     */
    public function addFromSearch(): void
    {
        // Sin espacios (el lector o un pegado pueden traerlos).
        $code = preg_replace('/\s+/', '', $this->search);

        // Nada escrito: nada que hacer.
        if ($code === '') {
            return;
        }

        // Código exacto de un producto a la venta.
        $product = Product::query()
            ->where('activo', true)
            ->where('barcode', $code)
            ->first();

        // Si no, el único resultado de la búsqueda.
        if ($product === null && $this->products->count() === 1) {
            $product = $this->products->first();
        }

        // Nada para agregar.
        if ($product === null) {
            Notification::make()->warning()->title("No hay ningún producto con «{$this->search}».")->send();

            return;
        }

        $this->add($product->getKey());
    }

    /**
     * Cambia las unidades de un renglón (con − y +).
     *
     * Postcondiciones:
     * - Suma $delta; si queda en 0 o menos, saca el producto; nunca pasa el tope.
     *
     * @param  int  $productId  Producto.
     * @param  int  $delta  Unidades a sumar (negativo resta).
     */
    public function changeQuantity(int $productId, int $delta): void
    {
        // Solo productos que están en el carrito.
        if (! isset($this->cart[$productId])) {
            return;
        }

        // Nueva cantidad.
        $cantidad = $this->cart[$productId] + $delta;

        // En 0 se saca; si no, con tope.
        if ($cantidad <= 0) {
            unset($this->cart[$productId]);
        } else {
            $this->cart[$productId] = min(SaleService::MAX_CANTIDAD, $cantidad);
        }
    }

    /**
     * Saca un producto del carrito.
     *
     * @param  int  $productId  Producto.
     */
    public function remove(int $productId): void
    {
        // Afuera.
        unset($this->cart[$productId]);
    }

    /**
     * Vacía la venta en curso (carrito, socio y pago) y vuelve al estado inicial.
     */
    public function clear(): void
    {
        // Todo a cero.
        $this->reset(['cart', 'search', 'clientId', 'clientSearch', 'metodo', 'montoEfectivo', 'pagaCon']);
        $this->dispatch('kiosk-focus-search');
    }

    /**
     * Elige el socio de la venta.
     *
     * Postcondiciones:
     * - Si el cliente existe y no está dado de baja, queda como socio de la venta y se limpia la
     *   búsqueda.
     *
     * @param  int  $clientId  Cliente.
     */
    public function selectClient(int $clientId): void
    {
        // Solo clientes vigentes.
        if (Client::query()->whereKey($clientId)->exists()) {
            $this->clientId = $clientId;
            $this->clientSearch = '';
        }
    }

    /**
     * Quita el socio: vuelve a ser venta de mostrador.
     */
    public function removeClient(): void
    {
        // Sin socio.
        $this->clientId = null;
    }

    /**
     * Cobra la venta.
     *
     * Precondiciones:
     * - Hay una cuenta del personal con sesión.
     *
     * Postcondiciones:
     * - Arma los cobros según el medio (todo en efectivo, todo por transferencia o mixto) por el
     *   total del presupuesto y llama a SaleService::checkout().
     * - Si sale bien: avisa "Venta #N cobrada" (con el vuelto si se cargó con cuánto paga) y deja
     *   la pantalla lista para la próxima venta.
     * - Si no: avisa en ámbar el motivo y deja la venta como estaba para corregirla.
     *
     * @param  SaleService  $service  Servicio de ventas.
     */
    public function checkout(SaleService $service): void
    {
        // Quién vende.
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User, 403);

        try {
            // Total con los precios de ahora.
            $total = $service->quote($this->cart, $this->client, $this->pricingMethod())->totalCents();
            // Cobros según el medio.
            $payments = $this->buildPayments($total);
            // Vuelto (solo en efectivo con "paga con").
            $vuelto = $this->changeCents($total);

            // Guarda todo.
            $sale = $service->checkout($user, $this->cart, $payments, $this->client);
        } catch (SaleException $exception) {
            // Motivo en ámbar, sin perder la venta.
            Notification::make()->warning()->title('No se pudo cobrar')->body($exception->getMessage())->send();

            return;
        }

        // Confirmación.
        Notification::make()
            ->success()
            ->title("Venta #{$sale->getKey()} cobrada: {$this->money(SaleQuote::toCents($sale->total))}")
            ->body($vuelto !== null && $vuelto > 0 ? "Vuelto: {$this->money($vuelto)}" : null)
            ->send();

        // Lista para la próxima.
        $this->clear();
    }

    /**
     * Socio elegido, o null.
     */
    #[Computed]
    public function client(): ?Client
    {
        // Sin socio: venta de mostrador.
        return $this->clientId === null ? null : Client::query()->find($this->clientId);
    }

    /**
     * Clientes que coinciden con la búsqueda de socio (hasta 6).
     *
     * Postcondiciones:
     * - Con menos de 2 caracteres devuelve una colección vacía. Busca por DNI (desde el principio),
     *   apellido o nombre, solo clientes vigentes.
     *
     * @return Collection<int, Client>
     */
    #[Computed]
    public function clientResults(): Collection
    {
        // Texto a buscar.
        $search = trim($this->clientSearch);

        // Muy corto: nada.
        if (mb_strlen($search) < 2) {
            return new Collection;
        }

        return Client::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('dni', 'like', "{$search}%")
                ->orWhere('apellido', 'like', "%{$search}%")
                ->orWhere('nombre', 'like', "%{$search}%"))
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->limit(6)
            ->get();
    }

    /**
     * Categorías para los botones de filtro, en su orden.
     *
     * @return Collection<int, ProductCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        // Solo las que tienen productos a la venta.
        return ProductCategory::query()
            ->whereHas('products', fn (Builder $query): Builder => $query->where('activo', true))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Productos de la grilla.
     *
     * Postcondiciones:
     * - Productos activos de la categoría elegida (o de todas) que coinciden con la búsqueda por
     *   nombre o código de barras.
     * - Orden: destacados, después los más vendidos de los últimos BEST_SELLER_DAYS días, después
     *   alfabético. Hasta GRID_LIMIT productos. No modifica nada.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        // Texto a buscar.
        $search = trim($this->search);

        return Product::query()
            ->where('activo', true)
            // Categoría elegida.
            ->when($this->categoryId !== null, fn (Builder $query): Builder => $query->where('product_category_id', $this->categoryId))
            // Búsqueda.
            ->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->where('nombre', 'like', "%{$search}%")
                ->orWhere('barcode', $search)))
            // Unidades vendidas en los últimos días (ventas no anuladas).
            ->withSum(['saleItems as vendidos' => fn (Builder $query): Builder => $query
                ->where('created_at', '>=', now()->subDays(self::BEST_SELLER_DAYS))
                ->whereHas('sale', fn (Builder $query): Builder => $query->where('estado', '!=', SaleStatusEnum::Anulada))], 'cantidad')
            ->orderByDesc('destacado')
            ->orderByDesc('vendidos')
            ->orderBy('nombre')
            ->limit(self::GRID_LIMIT)
            ->get();
    }

    /**
     * Datos para la vista: el presupuesto del carrito (o el error si algo ya no se vende).
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        // Sin carrito no hay presupuesto.
        if ($this->cart === []) {
            return ['quote' => null, 'quoteError' => null, 'strict' => Product::stockIsStrict()];
        }

        try {
            // Precios según socio y medio de pago.
            $quote = app(SaleService::class)->quote($this->cart, $this->client, $this->pricingMethod());
            $quoteError = null;
        } catch (SaleException $exception) {
            // Por ejemplo, un producto que se apagó mientras estaba en el carrito.
            $quote = null;
            $quoteError = $exception->getMessage();
        }

        return ['quote' => $quote, 'quoteError' => $quoteError, 'strict' => Product::stockIsStrict()];
    }

    /**
     * Medio que define los precios: el elegido, o null si es mixto (precio de lista).
     */
    public function pricingMethod(): ?PaymentMethodEnum
    {
        // Mixto no tiene precio en efectivo.
        return PaymentMethodEnum::tryFrom($this->metodo);
    }

    /**
     * Vuelto en centavos, si se paga todo en efectivo y se cargó con cuánto paga.
     *
     * Postcondiciones:
     * - null si no corresponde o no se cargó; si no, lo que paga menos el total (puede ser
     *   negativo: falta plata). No modifica nada.
     *
     * @param  int  $totalCents  Total de la venta.
     * @return int|null Vuelto en centavos.
     */
    public function changeCents(int $totalCents): ?int
    {
        // Solo en efectivo y con un número cargado.
        if ($this->metodo !== PaymentMethodEnum::Efectivo->value || ! is_numeric($this->pagaCon)) {
            return null;
        }

        return SaleQuote::toCents($this->pagaCon) - $totalCents;
    }

    /**
     * Importe para mostrar: "$ 1.500" o "$ 1.234,50".
     *
     * @param  int  $cents  Centavos.
     * @return string Importe con formato argentino.
     */
    public function money(int $cents): string
    {
        // Sin decimales si son ",00".
        $decimals = $cents % 100 === 0 ? 0 : 2;

        return '$ '.number_format($cents / 100, $decimals, ',', '.');
    }

    /**
     * Arma los cobros según el medio elegido.
     *
     * Postcondiciones:
     * - Efectivo o transferencia: un cobro por el total.
     * - Mixto: el efectivo cargado y el resto por transferencia; los dos tienen que ser mayores
     *   que 0. No modifica nada.
     *
     * @param  int  $totalCents  Total a cobrar.
     * @return list<array{metodo: PaymentMethodEnum, monto: string}> Cobros.
     *
     * @throws SaleException Si el medio no existe o el efectivo del mixto no deja resto.
     */
    private function buildPayments(int $totalCents): array
    {
        // Un solo medio: todo por ese.
        if (($metodo = PaymentMethodEnum::tryFrom($this->metodo)) !== null) {
            return [['metodo' => $metodo, 'monto' => SaleQuote::toAmount($totalCents)]];
        }

        // Solo queda el mixto.
        if ($this->metodo !== self::MIXED) {
            throw new SaleException('Elegí cómo paga.');
        }

        // Parte en efectivo.
        $efectivo = is_numeric($this->montoEfectivo) ? SaleQuote::toCents($this->montoEfectivo) : 0;

        // Tiene que haber algo en efectivo y algo por transferencia.
        if ($efectivo <= 0 || $efectivo >= $totalCents) {
            throw new SaleException('En pago mixto, el efectivo tiene que ser más de $ 0 y menos que el total.');
        }

        return [
            ['metodo' => PaymentMethodEnum::Efectivo, 'monto' => SaleQuote::toAmount($efectivo)],
            ['metodo' => PaymentMethodEnum::Transferencia, 'monto' => SaleQuote::toAmount($totalCents - $efectivo)],
        ];
    }
}
