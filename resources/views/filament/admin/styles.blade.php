{{--
    Estilos propios del panel /admin. Se cargan después de los de Filament (ver AdminPanelProvider).

    .orions-modal: ventana modal con cabecera en el color principal, sólido (el mismo tono de los
    botones). El cuerpo y el pie conservan el fondo normal del panel. Se activa en una acción con
    ->extraModalWindowAttributes(['class' => 'orions-modal']).
--}}
<style>
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
        font-size: 1.125rem;
        line-height: 1.5rem;
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
