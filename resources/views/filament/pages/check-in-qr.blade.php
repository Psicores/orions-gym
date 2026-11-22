{{--
    QR de ingreso (ver App\Filament\Pages\CheckInQr). Recibe $qr (imagen como data URI) y $token,
    o, si falta configurar el código, los dos en null y $sugerencia con un código al azar.
    Al imprimir solo sale el cartel: el menú y los botones se ocultan (ver filament/admin/styles.blade.php).
--}}
<x-filament-panels::page>
    @if ($qr === null)
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">Falta configurar el código del QR</x-slot>

            <div class="orions-qr-config">
                <p>Mientras tanto, los socios no pueden registrar su ingreso escaneando. Para habilitarlo:</p>
                <ol>
                    <li>En el <code>.env</code> del servidor, agregá esta línea (el código es al azar; podés usar este u otro de 16 caracteres o más):
                        <pre>GYM_CHECKIN_TOKEN={{ $sugerencia }}</pre>
                    </li>
                    <li>Corré <code>php artisan config:cache</code> en la consola del servidor.</li>
                    <li>Volvé a esta pantalla, imprimí el QR nuevo y reemplazá el del mostrador.</li>
                </ol>
            </div>
        </x-filament::section>
    @else
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
    @endif
</x-filament-panels::page>
