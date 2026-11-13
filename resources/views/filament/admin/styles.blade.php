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

    Calendario de los campos de fecha (.fi-fo-date-time-picker-panel): cabecera en el color
    principal con el mes en Anton, días redondos, el día elegido relleno en el color principal y
    el día de hoy con un aro naranja. Vale para todos los campos (ver AppServiceProvider).
    El script del final le agrega a esa cabecera dos flechas (.orions-cal-nav) para pasar al mes
    anterior o al siguiente sin abrir la lista de meses.
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

    /* Calendario de los campos de fecha: tarjeta con borde y esquinas como las del resto del panel. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel {
        min-width: 19rem;
        padding: 0 1rem 1rem;
        overflow: hidden;
        border-radius: 0.875rem;
        box-shadow: 0 18px 40px -16px rgb(22 24 23 / 0.45), 0 0 0 1px rgb(22 24 23 / 0.08);
    }

    /* Cabecera: banda en el color principal, de lado a lado, con mes y año en blanco. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-panel-header {
        margin: 0 -1rem 0.875rem;
        padding: 0.875rem 1rem;
        background: var(--primary-600);
    }

    /* Mes: con la fuente de los títulos. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-month-select {
        font-family: 'Anton', 'Bebas Neue', Impact, ui-sans-serif, sans-serif;
        font-size: 1.25rem;
        font-weight: 400;
        line-height: 1.75rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #fff;
        background-color: transparent;
        /* Flecha del desplegable en blanco. */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='white' stroke-width='1.75' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0 center;
        background-size: 1.25rem;
        padding-inline-end: 1.75rem;
        appearance: none;
    }

    /* La lista de meses se abre sobre fondo claro: ahí el texto va oscuro y en la fuente normal. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-month-select option {
        font-family: ui-sans-serif, system-ui, sans-serif;
        letter-spacing: normal;
        text-transform: none;
        color: #161817;
        background: #fff;
    }

    /* Año: blanco, en una pastilla translúcida. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-year-input {
        width: 4.5rem;
        padding: 0.125rem 0.375rem;
        border-radius: 0.5rem;
        font-weight: 600;
        color: #fff;
        background: rgb(255 255 255 / 0.16);
    }

    /* Foco de mes y año: aro blanco, que se ve sobre la banda de color. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-month-select:focus-visible,
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-year-input:focus-visible {
        outline: 2px solid #fff;
        outline-offset: 2px;
    }

    /* Días de la semana: chicos, en mayúsculas y espaciados, como los títulos de grupo del menú. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar-header-day {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    /* Días: redondos y con los números alineados. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar .fi-fo-date-time-picker-calendar-day {
        aspect-ratio: 1;
        display: grid;
        place-items: center;
        border-radius: 999px;
        font-variant-numeric: tabular-nums;
        line-height: 1;
        transition: background-color 120ms, color 120ms;
    }

    /* Al pasar el mouse: tinte del color principal. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar .fi-fo-date-time-picker-calendar-day:not(.fi-disabled):not(.fi-selected):is(:hover, :focus-visible) {
        color: var(--primary-800);
        background: var(--primary-100);
    }

    /* Hoy: aro naranja (el contraste de la paleta). */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar .fi-fo-date-time-picker-calendar-day.fi-fo-date-time-picker-calendar-day-today:not(.fi-selected) {
        font-weight: 700;
        color: #b85f1f;
        box-shadow: inset 0 0 0 2px #d9772f;
    }

    /* Día elegido: relleno en el color principal. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar .fi-fo-date-time-picker-calendar-day.fi-selected,
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar .fi-fo-date-time-picker-calendar-day.fi-selected:is(:hover, :focus-visible) {
        font-weight: 700;
        color: #fff;
        background: var(--primary-600);
    }

    /* Hora: separada del calendario por una línea, con cada casillero como una cajita. */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-time-inputs {
        margin-top: 0.875rem;
        padding-top: 0.875rem;
        border-top: 1px solid var(--gray-200);
    }

    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-time-inputs input {
        width: 3rem;
        padding: 0.375rem 0;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        background: var(--gray-100);
    }

    /* Modo oscuro. */
    .dark .fi-fo-date-time-picker .fi-fo-date-time-picker-panel {
        box-shadow: 0 18px 40px -16px rgb(0 0 0 / 0.7), 0 0 0 1px rgb(255 255 255 / 0.1);
    }

    .dark .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-panel-header {
        background: var(--primary-700);
    }

    .dark .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar .fi-fo-date-time-picker-calendar-day:not(.fi-disabled):not(.fi-selected):is(:hover, :focus-visible) {
        color: #fff;
        background: rgb(122 174 169 / 0.22);
    }

    .dark .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar .fi-fo-date-time-picker-calendar-day.fi-fo-date-time-picker-calendar-day-today:not(.fi-selected) {
        color: #f0a469;
    }

    .dark .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar .fi-fo-date-time-picker-calendar-day.fi-selected,
    .dark .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-calendar .fi-fo-date-time-picker-calendar-day.fi-selected:is(:hover, :focus-visible) {
        color: #fff;
        background: var(--primary-500);
    }

    .dark .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-time-inputs {
        border-top-color: rgb(255 255 255 / 0.1);
    }

    .dark .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .fi-fo-date-time-picker-time-inputs input {
        background: rgb(255 255 255 / 0.06);
    }

    /* Flechas de mes anterior / siguiente en la cabecera del calendario (las agrega el script de abajo). */
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .orions-cal-nav {
        display: grid;
        flex: none;
        place-items: center;
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        color: #fff;
        background: rgb(255 255 255 / 0.16);
        cursor: pointer;
        transition: background-color 120ms;
    }

    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .orions-cal-nav:hover,
    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .orions-cal-nav:focus-visible {
        background: rgb(255 255 255 / 0.3);
        outline: none;
    }

    .fi-fo-date-time-picker .fi-fo-date-time-picker-panel .orions-cal-nav svg {
        width: 1.125rem;
        height: 1.125rem;
    }
</style>

<script>
    /*
        Flechas del calendario. Filament solo trae la lista de meses y el casillero del año; acá se
        suman dos botones a la cabecera. Se agregan la primera vez que se usa cada campo de fecha y
        mueven el mes a través del componente Alpine del propio calendario (focusedDate).
    */
    (() => {
        // Dibujo de la flecha; "d" es el trazo (izquierda o derecha).
        const icon = (d) => `<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${d}"/></svg>`;

        // Crea un botón que suma "step" meses (-1 o 1) al mes que muestra el calendario.
        const makeButton = (picker, step, label, d) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'orions-cal-nav';
            button.setAttribute('aria-label', label);
            button.innerHTML = icon(d);
            // Evita que el clic le saque el foco al calendario (se cerraría).
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                // Estado del calendario de Filament.
                const data = window.Alpine?.$data(picker);
                if (data?.focusedDate) {
                    // Cambia el mes; Filament actualiza solo la lista de meses, el año y los días.
                    data.focusedDate = data.focusedDate.add(step, 'month');
                }
            });

            return button;
        };

        // Agrega las flechas a la cabecera del calendario de un campo, una sola vez.
        const addArrows = (target) => {
            const picker = target instanceof Element ? target.closest('.fi-fo-date-time-picker') : null;
            const header = picker?.querySelector('.fi-fo-date-time-picker-panel-header');
            if (! header || header.querySelector('.orions-cal-nav')) {
                return;
            }
            // Componente Alpine del calendario (el elemento que tiene focusedDate).
            const root = header.closest('[x-data]');
            header.prepend(makeButton(root, -1, 'Mes anterior', 'M12 5l-5 5 5 5'));
            header.append(makeButton(root, 1, 'Mes siguiente', 'M8 5l5 5-5 5'));
        };

        // Cualquier interacción con un campo de fecha (foco o clic) asegura que tenga sus flechas.
        document.addEventListener('focusin', (event) => addArrows(event.target));
        document.addEventListener('click', (event) => addArrows(event.target), true);
    })();
</script>
