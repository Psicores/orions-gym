{{--
    QR de ingreso (ver App\Filament\Pages\CheckInQr). Recibe $qr (imagen como data URI) y $token.
    Al imprimir solo sale el cartel: el menú y los botones se ocultan (ver filament/admin/styles.blade.php).
--}}
<x-filament-panels::page>
    <x-filament::section>
        <div class="orions-qr-cartel">
            <p class="orions-qr-marca">Orions</p>
            <img src="{{ $qr }}" alt="Código QR de ingreso al gimnasio" width="640" height="640" class="orions-qr-imagen">
            <p class="orions-qr-titulo">Escaneá para registrar tu ingreso</p>
            <p class="orions-qr-ayuda">Abrí el portal del socio en tu celular y tocá "Escanear QR".</p>
        </div>
    </x-filament::section>

    <div class="orions-no-print">
        <x-filament::button icon="heroicon-o-printer" x-on:click="window.print()">
            Imprimir
        </x-filament::button>

        <p class="orions-qr-nota">Imprimilo y pegalo en el mostrador. Es siempre el mismo código: no hace falta volver a imprimirlo.</p>
    </div>
</x-filament-panels::page>
