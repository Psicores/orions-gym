{{--
    Pantalla de venta del kiosco (ver App\Filament\Pages\Kiosk). Recibe $quote (presupuesto del
    carrito, SaleQuote o null), $quoteError (texto o null) y $strict (modo de stock estricto).

    Pensada para PC y tablet, y usable en el celular: desde 900 px de ancho, grilla a la izquierda y
    venta a la derecha; en pantallas más angostas se apilan (en el celular, dos productos por fila) y
    una barra fija abajo lleva a la venta. Botones de 44 px o
    más para usar con el dedo. El buscador tiene el foco siempre que se agrega un producto, así un
    lector de códigos USB (escribe el código y manda Enter) funciona sin tocar nada.
    Estilos .orions-pos-* al final; colores del panel (nunca rojo: los avisos van en ámbar).
--}}
@php
    $percent = (int) config('gym.kiosk.member_discount_percent');
    $client = $this->client;
    $totalCents = $quote?->totalCents() ?? 0;
    $units = array_sum($this->cart);
@endphp

<x-filament-panels::page>
    <div class="orions-pos">
        {{-- Productos --}}
        <section class="orions-pos-catalog" aria-label="Productos">
            <div class="orions-pos-search">
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="orions-pos-search-icon" />
                <input
                    type="search"
                    wire:model.live.debounce.250ms="search"
                    wire:keydown.enter.prevent="addFromSearch"
                    x-data
                    x-init="$el.focus()"
                    x-on:kiosk-focus-search.window="$nextTick(() => $el.focus())"
                    placeholder="Buscar o escanear código"
                    aria-label="Buscar producto o escanear código"
                    autocomplete="off"
                    enterkeyhint="go"
                    class="orions-pos-search-input"
                >
            </div>

            @if ($this->categories->isNotEmpty())
                <div class="orions-pos-chips" role="group" aria-label="Categorías">
                    <button type="button" wire:click="$set('categoryId', null)" @class(['orions-pos-chip', 'is-active' => $categoryId === null]) aria-pressed="{{ $categoryId === null ? 'true' : 'false' }}">Todas</button>
                    @foreach ($this->categories as $category)
                        <button type="button" wire:key="cat-{{ $category->id }}" wire:click="$set('categoryId', {{ $category->id }})" @class(['orions-pos-chip', 'is-active' => $categoryId === $category->id]) aria-pressed="{{ $categoryId === $category->id ? 'true' : 'false' }}">{{ $category->nombre }}</button>
                    @endforeach
                </div>
            @endif

            @if ($this->products->isEmpty())
                <p class="orions-pos-empty">
                    @if (trim($search) !== '')
                        No hay productos con «{{ $search }}».
                    @else
                        Todavía no hay productos a la venta. Cargalos en
                        <a href="{{ \App\Filament\Resources\Products\ProductResource::getUrl() }}">Kiosco → Productos</a>.
                    @endif
                </p>
            @else
                <div class="orions-pos-grid">
                    @foreach ($this->products as $product)
                        @php
                            $inCart = $this->cart[$product->id] ?? 0;
                            $sinStock = $product->controla_stock && $product->stock <= 0;
                        @endphp
                        <button type="button" wire:key="product-{{ $product->id }}" wire:click="add({{ $product->id }})" class="orions-pos-tile" aria-label="Agregar {{ $product->nombre }}">
                            <span class="orions-pos-tile-img">
                                @if ($product->imagen_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->imagen_path) }}" alt="" loading="lazy">
                                @else
                                    <span aria-hidden="true">{{ mb_strtoupper(mb_substr($product->nombre, 0, 1)) }}</span>
                                @endif
                                @if ($inCart > 0)
                                    <span class="orions-pos-tile-count">{{ $inCart }}</span>
                                @endif
                            </span>
                            <span class="orions-pos-tile-name">{{ $product->nombre }}</span>
                            <span class="orions-pos-tile-foot">
                                <span class="orions-pos-tile-price">{{ $this->money(\App\Services\Kiosk\SaleQuote::toCents($product->precio)) }}</span>
                                @if ($product->controla_stock)
                                    <span @class(['orions-pos-tile-stock', 'is-low' => $sinStock || $product->hasLowStock()])>{{ $sinStock ? 'Sin stock' : 'Stock '.$product->stock }}</span>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Venta en curso --}}
        <aside id="orions-pos-venta" class="orions-pos-cart" aria-label="Venta">
            <div class="orions-pos-cart-head">
                <h2>Venta</h2>
                @if ($this->cart !== [] || $clientId !== null)
                    <button type="button" wire:click="clear" class="orions-pos-link">Vaciar</button>
                @endif
            </div>

            {{-- Socio --}}
            <div class="orions-pos-block">
                @if ($client)
                    <div class="orions-pos-client">
                        <div>
                            <p class="orions-pos-client-name">{{ $client->apellido }}, {{ $client->nombre }}</p>
                            <p class="orions-pos-muted">DNI {{ $client->dni }} · <x-filament::badge :color="$client->estado->getColor()" size="sm" class="orions-pos-inline-badge">{{ $client->estado->getLabel() }}</x-filament::badge></p>
                            @if ($client->hasKioskDiscount())
                                <p class="orions-pos-ok">{{ $percent }} % de descuento de socio</p>
                            @else
                                <p class="orions-pos-warn">Sin descuento: no está al día</p>
                            @endif
                        </div>
                        <button type="button" wire:click="removeClient" class="orions-pos-link">Quitar</button>
                    </div>
                @else
                    <label class="orions-pos-label" for="orions-pos-socio">Socio (opcional)</label>
                    <input id="orions-pos-socio" type="search" wire:model.live.debounce.300ms="clientSearch" placeholder="DNI o apellido" autocomplete="off" class="orions-pos-input">
                    @if ($this->clientResults->isNotEmpty())
                        <ul class="orions-pos-results">
                            @foreach ($this->clientResults as $result)
                                <li wire:key="client-{{ $result->id }}">
                                    <button type="button" wire:click="selectClient({{ $result->id }})">
                                        <span>{{ $result->apellido }}, {{ $result->nombre }}</span>
                                        <span class="orions-pos-muted">DNI {{ $result->dni }} · {{ $result->estado->getLabel() }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif (mb_strlen(trim($clientSearch)) >= 2)
                        <p class="orions-pos-muted">No hay socios con «{{ $clientSearch }}».</p>
                    @endif
                @endif
            </div>

            {{-- Renglones --}}
            <div class="orions-pos-block orions-pos-lines">
                @if ($quoteError)
                    <p class="orions-pos-alert">{{ $quoteError }}</p>
                @endif

                @if ($this->cart === [])
                    <p class="orions-pos-muted">Tocá un producto o escaneá su código para empezar.</p>
                @elseif ($quote)
                    <ul>
                        @foreach ($quote->lines as $line)
                            <li wire:key="line-{{ $line->product->id }}" class="orions-pos-line">
                                <div class="orions-pos-line-info">
                                    <p class="orions-pos-line-name">{{ $line->product->nombre }}</p>
                                    <p class="orions-pos-muted">
                                        {{ $this->money($line->precioUnitarioCents) }} c/u
                                        @if ($line->precioUnitarioCents < $line->precioListaCents)
                                            <s>{{ $this->money($line->precioListaCents) }}</s>
                                        @endif
                                    </p>
                                    @if ($line->faltante > 0)
                                        <p class="orions-pos-warn">{{ 'Hay '.max(0, $line->product->stock).' en stock. '.($strict ? 'No alcanza para vender.' : 'Se vende igual.') }}</p>
                                    @endif
                                </div>
                                <div class="orions-pos-qty">
                                    <button type="button" wire:click="changeQuantity({{ $line->product->id }}, -1)" aria-label="Uno menos de {{ $line->product->nombre }}">−</button>
                                    <span aria-live="polite">{{ $line->cantidad }}</span>
                                    <button type="button" wire:click="changeQuantity({{ $line->product->id }}, 1)" aria-label="Uno más de {{ $line->product->nombre }}">+</button>
                                </div>
                                <p class="orions-pos-line-total">{{ $this->money($line->subtotalCents()) }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Pago --}}
            <div class="orions-pos-block">
                <p class="orions-pos-label">Cómo paga</p>
                <div class="orions-pos-segmented" role="group" aria-label="Cómo paga">
                    @foreach (['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', \App\Filament\Pages\Kiosk::MIXED => 'Mixto'] as $value => $label)
                        <button type="button" wire:click="$set('metodo', '{{ $value }}')" @class(['is-active' => $metodo === $value]) aria-pressed="{{ $metodo === $value ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>

                @if ($metodo === 'efectivo')
                    <label class="orions-pos-label" for="orions-pos-pagacon">Paga con (opcional)</label>
                    <input id="orions-pos-pagacon" type="number" inputmode="decimal" min="0" step="0.01" wire:model.live.debounce.400ms="pagaCon" placeholder="Para calcular el vuelto" class="orions-pos-input">
                    @php $vuelto = $quote ? $this->changeCents($totalCents) : null; @endphp
                    @if ($vuelto !== null)
                        <p @class(['orions-pos-change', 'is-short' => $vuelto < 0])>
                            {{ $vuelto < 0 ? 'Faltan '.$this->money(-$vuelto) : 'Vuelto: '.$this->money($vuelto) }}
                        </p>
                    @endif
                @elseif ($metodo === \App\Filament\Pages\Kiosk::MIXED)
                    <label class="orions-pos-label" for="orions-pos-mixto">En efectivo</label>
                    <input id="orions-pos-mixto" type="number" inputmode="decimal" min="0" step="0.01" wire:model.live.debounce.400ms="montoEfectivo" placeholder="Monto en efectivo" class="orions-pos-input">
                    @if ($quote && is_numeric($montoEfectivo))
                        @php $resto = $totalCents - \App\Services\Kiosk\SaleQuote::toCents($montoEfectivo); @endphp
                        <p @class(['orions-pos-change', 'is-short' => $resto <= 0])>
                            {{ $resto > 0 ? 'Por transferencia: '.$this->money($resto) : 'El efectivo tiene que ser menos que el total' }}
                        </p>
                    @endif
                    <p class="orions-pos-muted">En pago mixto se cobra el precio normal (sin precio en efectivo).</p>
                @endif
            </div>

            {{-- Totales y cobro --}}
            <div class="orions-pos-totals">
                @if ($quote && $quote->descuentoCents() > 0)
                    <p><span>Subtotal</span><span>{{ $this->money($quote->subtotalCents()) }}</span></p>
                    <p class="orions-pos-ok"><span>Descuento</span><span>− {{ $this->money($quote->descuentoCents()) }}</span></p>
                @endif
                <p class="orions-pos-total"><span>Total</span><span>{{ $this->money($totalCents) }}</span></p>
            </div>

            <button
                type="button"
                wire:click="checkout"
                wire:loading.attr="disabled"
                wire:target="checkout"
                @disabled($quote === null)
                class="orions-pos-pay"
            >
                <span wire:loading.remove wire:target="checkout">Cobrar {{ $quote ? $this->money($totalCents) : '' }}</span>
                <span wire:loading wire:target="checkout">Cobrando…</span>
            </button>
        </aside>
    </div>

    {{-- En pantallas angostas: acceso rápido a la venta, abajo. --}}
    @if ($units > 0)
        <a href="#orions-pos-venta" class="orions-pos-bar">
            <span>{{ $units }} {{ $units === 1 ? 'producto' : 'productos' }}</span>
            <span>Ver venta · {{ $this->money($totalCents) }}</span>
        </a>
    @endif

    <style>
        .orions-pos {
            display: grid;
            /* Una columna que nunca supera el ancho de la pantalla (sin esto, la fila de categorías la estiraba). */
            grid-template-columns: minmax(0, 1fr);
            gap: 1.25rem;
            align-items: start;
        }

        /* Las dos partes pueden achicarse por debajo del ancho de su contenido. */
        .orions-pos > * {
            min-width: 0;
        }

        /* Celular: dos productos por fila, con la imagen más baja para ver más productos de una vez.
           Con .orions-pos adelante, para ganarles a las reglas generales que vienen más abajo. */
        @media (max-width: 639.98px) {
            .orions-pos .orions-pos-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.5rem;
            }

            .orions-pos .orions-pos-tile-img {
                aspect-ratio: 4 / 3;
                font-size: 2rem;
            }

            .orions-pos .orions-pos-cart {
                padding: 1rem;
            }

            .orions-pos .orions-pos-segmented button {
                font-size: 0.875rem;
            }
        }

        @media (min-width: 900px) {
            .orions-pos {
                grid-template-columns: minmax(0, 1fr) 23rem;
            }

            .orions-pos-cart {
                position: sticky;
                top: 5rem;
                max-height: calc(100vh - 6rem);
                overflow-y: auto;
            }

            .orions-pos-bar {
                display: none !important;
            }
        }

        @media (min-width: 1400px) {
            .orions-pos {
                grid-template-columns: minmax(0, 1fr) 26rem;
            }
        }

        /* Buscador grande: también recibe el lector de códigos. */
        .orions-pos-search {
            position: relative;
        }

        .orions-pos-search-icon {
            position: absolute;
            inset-block: 0;
            inset-inline-start: 1rem;
            width: 1.375rem;
            height: 1.375rem;
            margin-block: auto;
            color: var(--gray-400);
            pointer-events: none;
        }

        .orions-pos-search-input,
        .orions-pos-input {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 0.75rem;
            background: #fff;
            color: var(--gray-950);
        }

        .orions-pos-search-input {
            min-height: 3.25rem;
            padding: 0.75rem 1rem 0.75rem 3rem;
            font-size: 1.0625rem;
        }

        .orions-pos-input {
            min-height: 2.75rem;
            padding: 0.5rem 0.875rem;
            font-size: 1rem;
        }

        .orions-pos-search-input:focus,
        .orions-pos-input:focus {
            outline: 2px solid var(--primary-500);
            outline-offset: 1px;
            border-color: transparent;
        }

        .dark .orions-pos-search-input,
        .dark .orions-pos-input {
            border-color: rgb(255 255 255 / 0.12);
            background: rgb(255 255 255 / 0.05);
            color: #fff;
        }

        /* Categorías: se desplazan de costado si no entran. */
        .orions-pos-chips {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.875rem;
            overflow-x: auto;
            padding-bottom: 0.25rem;
            scrollbar-width: thin;
        }

        .orions-pos-chip {
            flex: none;
            min-height: 2.75rem;
            padding: 0.5rem 1.125rem;
            border: 1px solid var(--gray-300);
            border-radius: 999px;
            background: #fff;
            font-weight: 600;
            font-size: 0.9375rem;
            white-space: nowrap;
        }

        .orions-pos-chip.is-active {
            border-color: var(--primary-600);
            background: var(--primary-600);
            color: #fff;
        }

        .dark .orions-pos-chip:not(.is-active) {
            border-color: rgb(255 255 255 / 0.12);
            background: rgb(255 255 255 / 0.05);
        }

        /* Grilla de productos. */
        .orions-pos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .orions-pos-tile {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            padding: 0.625rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.875rem;
            background: #fff;
            text-align: start;
            transition: border-color 120ms, box-shadow 120ms, transform 80ms;
            touch-action: manipulation;
        }

        .orions-pos-tile:hover,
        .orions-pos-tile:focus-visible {
            border-color: var(--primary-400);
            box-shadow: 0 6px 18px -10px rgb(22 24 23 / 0.4);
            outline: none;
        }

        .orions-pos-tile:active {
            transform: scale(0.97);
        }

        .dark .orions-pos-tile {
            border-color: rgb(255 255 255 / 0.1);
            background: var(--gray-900);
        }

        .orions-pos-tile-img {
            position: relative;
            display: grid;
            place-items: center;
            aspect-ratio: 1;
            overflow: hidden;
            border-radius: 0.625rem;
            background: var(--primary-50);
            color: var(--primary-700);
            font-family: 'Anton', 'Bebas Neue', Impact, ui-sans-serif, sans-serif;
            font-size: 2.5rem;
        }

        .dark .orions-pos-tile-img {
            background: rgb(122 174 169 / 0.15);
            color: var(--primary-300);
        }

        .orions-pos-tile-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .orions-pos-tile-count {
            position: absolute;
            top: 0.375rem;
            inset-inline-end: 0.375rem;
            min-width: 1.75rem;
            height: 1.75rem;
            padding-inline: 0.375rem;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: var(--primary-600);
            color: #fff;
            font-family: ui-sans-serif, system-ui, sans-serif;
            font-size: 0.875rem;
            font-weight: 700;
        }

        .orions-pos-tile-name {
            font-weight: 600;
            font-size: 0.9375rem;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .orions-pos-tile-foot {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 0.25rem;
            margin-top: auto;
        }

        .orions-pos-tile-price {
            font-weight: 700;
            font-size: 1.0625rem;
            font-variant-numeric: tabular-nums;
        }

        .orions-pos-tile-stock {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .orions-pos-tile-stock.is-low {
            color: var(--warning-700);
            font-weight: 600;
        }

        .dark .orions-pos-tile-stock.is-low {
            color: var(--warning-400);
        }

        .orions-pos-empty {
            margin-top: 1.5rem;
            color: var(--gray-500);
        }

        .orions-pos-empty a,
        .orions-pos-link {
            color: var(--primary-600);
            font-weight: 600;
        }

        .orions-pos-link {
            min-height: 2.75rem;
            padding-inline: 0.5rem;
        }

        /* Panel de la venta. */
        .orions-pos-cart {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.25rem;
            border: 1px solid var(--gray-200);
            border-radius: 1rem;
            background: #fff;
            scroll-margin-top: 5rem;
        }

        .dark .orions-pos-cart {
            border-color: rgb(255 255 255 / 0.1);
            background: var(--gray-900);
        }

        .orions-pos-cart-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .orions-pos-cart-head h2 {
            font-family: 'Anton', 'Bebas Neue', Impact, ui-sans-serif, sans-serif;
            font-weight: 400;
            font-size: 1.5rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .orions-pos-block {
            display: grid;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .dark .orions-pos-block {
            border-top-color: rgb(255 255 255 / 0.1);
        }

        .orions-pos-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-600);
        }

        .dark .orions-pos-label {
            color: var(--gray-300);
        }

        .orions-pos-muted {
            font-size: 0.8125rem;
            color: var(--gray-500);
        }

        .orions-pos-ok {
            color: var(--primary-700);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .dark .orions-pos-ok {
            color: var(--primary-300);
        }

        .orions-pos-warn {
            color: var(--warning-700);
            font-size: 0.8125rem;
            font-weight: 600;
        }

        .dark .orions-pos-warn {
            color: var(--warning-400);
        }

        .orions-pos-alert {
            padding: 0.625rem 0.875rem;
            border-radius: 0.625rem;
            background: var(--warning-50);
            color: var(--warning-800);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .dark .orions-pos-alert {
            background: rgb(217 119 47 / 0.15);
            color: var(--warning-300);
        }

        .orions-pos-inline-badge {
            display: inline-flex;
            vertical-align: middle;
        }

        /* Socio. */
        .orions-pos-client {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .orions-pos-client-name {
            font-weight: 600;
        }

        .orions-pos-results {
            display: grid;
            gap: 0.25rem;
        }

        .orions-pos-results button {
            display: grid;
            width: 100%;
            min-height: 2.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.625rem;
            text-align: start;
        }

        .orions-pos-results button:hover,
        .orions-pos-results button:focus-visible {
            background: var(--primary-50);
            outline: none;
        }

        .dark .orions-pos-results button:hover,
        .dark .orions-pos-results button:focus-visible {
            background: rgb(255 255 255 / 0.06);
        }

        /* Renglones. */
        .orions-pos-lines ul {
            display: grid;
            gap: 0.75rem;
        }

        .orions-pos-line {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.25rem 0.75rem;
            align-items: center;
        }

        .orions-pos-line-name {
            font-weight: 600;
            line-height: 1.25;
        }

        .orions-pos-line-total {
            grid-column: 2;
            justify-self: end;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .orions-pos-qty {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            grid-row: span 2;
        }

        .orions-pos-qty button {
            width: 2.75rem;
            height: 2.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.625rem;
            font-size: 1.25rem;
            font-weight: 700;
            touch-action: manipulation;
        }

        .dark .orions-pos-qty button {
            border-color: rgb(255 255 255 / 0.15);
        }

        .orions-pos-qty span {
            min-width: 1.75rem;
            text-align: center;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        /* Medio de pago: botones juntos. */
        .orions-pos-segmented {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.375rem;
            padding: 0.25rem;
            border-radius: 0.875rem;
            background: var(--gray-100);
        }

        .dark .orions-pos-segmented {
            background: rgb(255 255 255 / 0.06);
        }

        .orions-pos-segmented button {
            min-height: 2.75rem;
            border-radius: 0.625rem;
            font-weight: 600;
            font-size: 0.9375rem;
        }

        .orions-pos-segmented button.is-active {
            background: #fff;
            color: var(--primary-700);
            box-shadow: 0 1px 3px rgb(22 24 23 / 0.2);
        }

        .dark .orions-pos-segmented button.is-active {
            background: var(--primary-600);
            color: #fff;
        }

        .orions-pos-change {
            font-weight: 700;
            font-size: 1.0625rem;
            color: var(--primary-700);
        }

        .orions-pos-change.is-short {
            color: var(--warning-700);
        }

        .dark .orions-pos-change {
            color: var(--primary-300);
        }

        .dark .orions-pos-change.is-short {
            color: var(--warning-400);
        }

        /* Totales. */
        .orions-pos-totals {
            display: grid;
            gap: 0.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .dark .orions-pos-totals {
            border-top-color: rgb(255 255 255 / 0.1);
        }

        .orions-pos-totals p {
            display: flex;
            justify-content: space-between;
            font-variant-numeric: tabular-nums;
        }

        .orions-pos-total {
            font-family: 'Anton', 'Bebas Neue', Impact, ui-sans-serif, sans-serif;
            font-size: 2rem;
            line-height: 1.1;
            letter-spacing: 0.02em;
        }

        .orions-pos-pay {
            min-height: 3.75rem;
            border-radius: 0.875rem;
            background: var(--primary-600);
            color: #fff;
            font-size: 1.25rem;
            font-weight: 700;
            touch-action: manipulation;
            transition: background-color 120ms;
        }

        .orions-pos-pay:hover:not(:disabled) {
            background: var(--primary-700);
        }

        .orions-pos-pay:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Barra fija para ir a la venta (solo en pantallas angostas). */
        .orions-pos-bar {
            position: sticky;
            bottom: 1rem;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem 1.25rem;
            border-radius: 0.875rem;
            background: var(--primary-600);
            color: #fff;
            font-weight: 700;
            box-shadow: 0 10px 24px -10px rgb(22 24 23 / 0.55);
        }
    </style>
</x-filament-panels::page>
