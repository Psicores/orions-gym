{{--
    Botón "Kiosco" de la barra superior del panel, a la izquierda del buscador: lleva a la pantalla
    de venta desde cualquier lugar (ver App\Filament\Pages\Kiosk y AdminPanelProvider). En pantallas
    chicas muestra solo el ícono.
--}}
<x-filament::button
    tag="a"
    :href="\App\Filament\Pages\Kiosk::getUrl()"
    icon="heroicon-o-shopping-cart"
    size="sm"
    labeled-from="sm"
    :outlined="! request()->routeIs(\App\Filament\Pages\Kiosk::getRouteName())"
    tooltip="Vender en el kiosco"
    class="orions-kiosk-btn"
>
    Kiosco
</x-filament::button>
