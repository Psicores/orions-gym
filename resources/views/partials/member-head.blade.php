{{--
    Cabecera <head> compartida por el login y el portal del socio.

    Además de los estilos, vincula el manifiesto y los íconos para que el celular permita instalar
    el portal como una app (PWA). Recibe $title: el título de la pantalla.
--}}
<meta charset="utf-8">
{{-- viewport-fit=cover: la app ocupa toda la pantalla y respeta el notch con las áreas seguras. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#161817">

<title>{{ $title }} · Orions</title>

{{-- PWA: manifiesto e íconos del gimnasio. --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icons/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/icons/apple-touch-icon.png') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Orions">

@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
