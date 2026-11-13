{{--
    QR de ingreso (ver App\Filament\Pages\CheckInQr). Recibe $qr (imagen como data URI) y $token.
    Al imprimir solo sale el cartel: el menú y los botones se ocultan (ver filament/admin/styles.blade.php).
--}}
<x-filament-panels::page>
    <x-filament::section>
        <div class="orions-qr-cartel">
            <p class="orions-qr-marca">Orions</p>
            <img src="{{ $qr }}" alt="Código QR de ingreso al gimnasio" width="320" height="320" class="orions-qr-imagen">
            <p class="orions-qr-titulo">Escaneá para registrar tu ingreso</p>
            <p class="orions-qr-ayuda">Abrí el portal del socio en tu celular y tocá "Escanear QR".</p>
        </div>
    </x-filament::section>

    <div class="orions-no-print">
        <x-filament::button icon="heroicon-o-printer" x-on:click="window.print()">
            Imprimir
        </x-filament::button>

        <p class="orions-qr-nota">
            Imprimilo y pegalo en el mostrador. El código es <code>{{ $token }}</code>: si lo cambiás
            (variable <code>GYM_CHECKIN_TOKEN</code>), el cartel impreso deja de servir y hay que imprimir uno nuevo.
        </p>
    </div>
</x-filament-panels::page>
