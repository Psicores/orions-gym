{{--
    Detalle de una venta del kiosco, en la ventana "Detalle" del historial (ver SaleResource).
    Recibe $sale (con items, payments, client y anuladaPor). Estilos .orions-sale-* en
    filament/admin/styles.blade.php.
--}}
@php
    $money = fn ($amount): string => '$ '.number_format((float) $amount, fmod((float) $amount, 1) == 0 ? 0 : 2, ',', '.');
@endphp

<div class="orions-sale">
    <p class="orions-sale-meta">
        <x-filament::badge :color="$sale->estado->getColor()">{{ $sale->estado->getLabel() }}</x-filament::badge>
        @if ($sale->client)
            Socio: <strong>{{ $sale->client->apellido }}, {{ $sale->client->nombre }}</strong> (DNI {{ $sale->client->dni }})
        @else
            Venta de mostrador
        @endif
    </p>

    @if ($sale->estado === \App\Enums\SaleStatusEnum::Anulada)
        <p class="orions-sale-void">
            Anulada el {{ $sale->anulada_at?->format('d/m/Y H:i') }} por {{ $sale->anuladaPor?->name }}: {{ $sale->motivo_anulacion }}
        </p>
    @endif

    <table class="orions-sale-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Precio</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->nombre }}</td>
                    <td>{{ $item->cantidad }}</td>
                    <td>
                        {{ $money($item->precio_unitario) }}
                        @if ((float) $item->precio_unitario < (float) $item->precio_lista)
                            <s>{{ $money($item->precio_lista) }}</s>
                        @endif
                    </td>
                    <td>{{ $money($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if ((float) $sale->descuento > 0)
                <tr>
                    <td colspan="3">Subtotal</td>
                    <td>{{ $money($sale->subtotal) }}</td>
                </tr>
                <tr>
                    <td colspan="3">Descuento</td>
                    <td>− {{ $money($sale->descuento) }}</td>
                </tr>
            @endif
            <tr class="orions-sale-total">
                <td colspan="3">Total</td>
                <td>{{ $money($sale->total) }}</td>
            </tr>
        </tfoot>
    </table>

    <div>
        <p class="orions-sale-heading">Cobros</p>
        <ul class="orions-sale-payments">
            @foreach ($sale->payments as $payment)
                <li>
                    <span>{{ $payment->metodo->getLabel() }}</span>
                    <x-filament::badge :color="$payment->estado->getColor()" size="sm">{{ $payment->estado->getLabel() }}</x-filament::badge>
                    <span>{{ $money($payment->monto) }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
