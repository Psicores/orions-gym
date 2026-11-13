{{--
    Firma de la agencia que desarrolló el sistema, para el sitio público y el portal del socio.
    Texto chico y apagado; el nombre es un enlace que se enciende al pasar por encima.
    Recibe opcionalmente $class con clases extra para el párrafo (alineación, márgenes).
--}}
<p class="text-xs text-ceniza {{ $class ?? '' }}">
    Desarrollado por
    <a
        href="https://devkorestudio.com"
        target="_blank"
        rel="noopener"
        class="font-semibold underline-offset-4 transition hover:text-salvia hover:underline focus-visible:text-salvia focus-visible:underline focus-visible:outline-none"
    >DevKore Studio</a>
</p>
