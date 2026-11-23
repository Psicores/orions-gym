{{--
    Cartel con el QR de un punto de acceso (ver App\Filament\Pages\CheckInQr). Recibe
    $accessPoint, $qr (imagen como data URI) y $url (lo que lleva el QR), o los tres en null si no
    hay ningún punto activo.
    Al imprimir solo sale el cartel: el menú y los botones se ocultan (ver filament/admin/styles.blade.php).
--}}
<x-filament-panels::page>
    @if ($qr === null)
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">No hay puntos de acceso activos</x-slot>

            <div class="orions-qr-config">
                <p>Mientras tanto, los socios no pueden registrar su ingreso con el QR. Creá o prendé un punto en <a href="{{ \App\Filament\Resources\AccessPoints\AccessPointResource::getUrl() }}">QR de ingreso</a>.</p>
            </div>
        </x-filament::section>
    @else
    <x-filament::section>
        <div class="orions-qr-cartel">
            <p class="orions-qr-marca">Orions</p>
            <img src="{{ $qr }}" alt="Código QR de ingreso: {{ $accessPoint->nombre }}" width="640" height="640" class="orions-qr-imagen">
            <p class="orions-qr-titulo">Escaneá para registrar tu ingreso</p>
            <p class="orions-qr-ayuda">Apuntá la cámara del celular al código y tocá el enlace. También podés usar "Escanear QR" en el portal del socio.</p>
            <p class="orions-qr-ayuda">{{ $accessPoint->nombre }}</p>
        </div>
    </x-filament::section>

    <div class="orions-no-print">
        <x-filament::button icon="heroicon-o-printer" x-on:click="window.print()">
            Imprimir
        </x-filament::button>

        <p class="orions-qr-nota">El QR lleva la dirección {{ $url }}. Si cambia el dominio del sitio o le cambiás el código al punto, hay que volver a imprimirlo.</p>
    </div>
    @endif
</x-filament-panels::page>
