{{--
    Estilos propios del panel /admin. Se cargan después de los de Filament (ver AdminPanelProvider).

    Títulos: usan Anton, la misma fuente de los títulos de la página pública, en mayúsculas. El
    resto del panel (menú, formularios y tablas) conserva la fuente normal de Filament.

    Menú lateral: el enlace activo se pinta con un tinte del color principal y una barrita a la
    izquierda, y cada grupo con título (por ejemplo "Admin del sitio") arranca con una línea
    separadora y el título en mayúsculas chicas.

    .orions-qr-*: cartel del QR de ingreso (/admin/qr-de-ingreso); al imprimir sale solo el cartel.

    .orions-modal: ventana modal con cabecera en el color principal, sólido (el mismo tono de los
    botones). El cuerpo y el pie conservan el fondo normal del panel. Se activa en una acción con
    ->extraModalWindowAttributes(['class' => 'orions-modal']).
--}}
{{-- Carga solo Anton (la fuente de títulos del home) desde los assets compilados. --}}
@fonts('anton')

<style>
    /* Títulos con la fuente del home: nombre del panel, título de página, de sección, de modal, del login y de los carteles. */
    .fi-logo,
    .fi-header-heading,
    .fi-simple-header-heading,
    .fi-section-header-heading,
    .fi-modal-heading,
    .fi-empty-state-heading,
    .orions-qr-marca,
    .orions-qr-titulo {
        font-family: 'Anton', 'Bebas Neue', Impact, ui-sans-serif, sans-serif;
        /* Anton solo tiene un peso: sin esto el navegador la engrosa artificialmente. */
        font-weight: 400;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    /* Anton es angosta: los títulos chicos suben un punto para leerse igual de bien. */
    .fi-section-header-heading {
        font-size: 1.125rem;
        line-height: 1.5rem;
    }

    .fi-logo {
        font-size: 1.5rem;
        letter-spacing: 0.06em;
    }

    /* Cartel del QR de ingreso. */
    .orions-qr-cartel {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
        padding: 1.5rem 1rem;
        text-align: center;
    }

    .orions-qr-marca {
        font-size: 2.25rem;
        letter-spacing: 0.2em;
    }

    .orions-qr-imagen {
        width: min(20rem, 100%);
        height: auto;
        border-radius: 0.75rem;
        background: #fff;
    }

    .orions-qr-titulo {
        font-size: 1.5rem;
    }

    .orions-qr-ayuda,
    .orions-qr-nota {
        color: var(--gray-500);
        font-size: 0.875rem;
    }

    .orions-qr-nota {
        margin-top: 0.75rem;
        max-width: 40rem;
    }

    /* Firma de la agencia: chica y apagada, al pie del panel y debajo del ingreso. */
    .orions-firma {
        padding: 1rem 1.5rem 1.5rem;
        font-size: 0.75rem;
        line-height: 1rem;
        text-align: center;
        color: #9ca3a1;
    }

    .orions-firma a {
        font-weight: 600;
        text-underline-offset: 4px;
        transition: color 150ms;
    }

    .orions-firma a:hover,
    .orions-firma a:focus-visible {
        color: var(--primary-500);
        text-decoration: underline;
        outline: none;
    }

    /* Al imprimir: solo el cartel, sin menú, barra superior, título ni botones. */
    @media print {
        .fi-sidebar,
        .fi-topbar,
        .fi-header,
        .orions-firma,
        .orions-no-print {
            display: none !important;
        }

        .orions-qr-imagen {
            width: 12cm;
        }
    }

    /* Línea separadora arriba de cada grupo con título. */
    .fi-sidebar-nav-groups > .fi-sidebar-group:has(> .fi-sidebar-group-btn) {
        padding-top: 1.25rem;
        border-top: 1px solid var(--gray-200);
    }

    .dark .fi-sidebar-nav-groups > .fi-sidebar-group:has(> .fi-sidebar-group-btn) {
        border-top-color: rgb(255 255 255 / 0.1);
    }

    /* Título del grupo: chico, en mayúsculas y espaciado. */
    .fi-sidebar-group-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    /* Al pasar el mouse, el ícono toma el color principal. */
    .fi-sidebar-item.fi-sidebar-item-has-url > .fi-sidebar-item-btn:hover > .fi-icon {
        color: var(--primary-600);
    }

    /* Enlace activo: tinte del color principal y barrita a la izquierda. */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: var(--primary-50);
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn::before {
        content: '';
        position: absolute;
        inset-block: 0.5rem;
        inset-inline-start: 0;
        width: 3px;
        border-radius: 999px;
        background: var(--primary-600);
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label {
        font-weight: 600;
    }

    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: rgb(255 255 255 / 0.06);
    }

    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn::before {
        background: var(--primary-400);
    }

    .dark .fi-sidebar-item.fi-sidebar-item-has-url > .fi-sidebar-item-btn:hover > .fi-icon {
        color: var(--primary-400);
    }

    .orions-modal .fi-modal-header {
        position: relative;
        align-items: center;
        padding: 1.5rem;
        border-start-start-radius: 0.75rem;
        border-start-end-radius: 0.75rem;
        background: var(--primary-600);
    }

    .orions-modal .fi-modal-icon-bg {
        margin: 0;
        padding: 0.625rem;
        border: 1px solid rgb(255 255 255 / 0.3);
        border-radius: 0.875rem;
        background: rgb(255 255 255 / 0.16);
        box-shadow: 0 6px 16px -8px rgb(0 0 0 / 0.35);
    }

    .orions-modal .fi-modal-icon-bg > .fi-icon,
    .orions-modal .fi-modal-heading {
        color: #fff;
    }

    .orions-modal .fi-modal-heading {
        font-size: 1.375rem;
        line-height: 1.75rem;
    }

    .orions-modal .fi-modal-description {
        margin-top: 0.125rem;
        color: rgb(255 255 255 / 0.88);
    }

    .orions-modal .fi-modal-close-btn,
    .orions-modal .fi-modal-close-btn .fi-icon {
        color: rgb(255 255 255 / 0.85);
    }

    .orions-modal .fi-modal-close-btn:hover,
    .orions-modal .fi-modal-close-btn:focus-visible {
        background: rgb(255 255 255 / 0.16);
    }

    .orions-modal .fi-modal-close-btn:hover .fi-icon,
    .orions-modal .fi-modal-close-btn:focus-visible .fi-icon {
        color: #fff;
    }

    /*
        Pie alineado a la derecha: los botones comparten el ancho del más largo. La grilla mide lo
        que su contenido y reparte columnas iguales; el botón principal sigue en el extremo derecho.
    */
    .orions-modal .fi-modal-footer.fi-align-end .fi-modal-footer-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: 1fr;
        width: max-content;
        max-width: 100%;
        margin-inline-start: auto;
    }

    .orions-modal .fi-modal-footer.fi-align-end .fi-modal-footer-actions > :first-child {
        order: 1;
    }
</style>
