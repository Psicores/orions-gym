# Módulos y funcionalidades

Inventario de lo que hace el sistema hoy, módulo por módulo. Sirve de referencia técnica y de base
comercial: cada módulo empieza con qué problema le resuelve al gimnasio y sigue con el detalle.

Estado: rama `kiosco-dev` (incluye el kiosco). Stack: Laravel 13, Filament 5 (panel), Livewire 4 y
Alpine (portal), Tailwind 4, PHP 8.3+, MySQL en producción.

## Mapa de módulos

| # | Módulo | Para quién | Depende de |
| --- | --- | --- | --- |
| 1 | Núcleo: panel, personal y seguridad | Dueño y profes | — |
| 2 | Socios, membresías y cobros | Recepción | 1 |
| 3 | Ingreso con QR y asistencias | Socios y recepción | 2 |
| 4 | Portal del socio (PWA) | Socios | 2 |
| 5 | Anuncios | Dueño → socios | 1 (se muestra en 4 y 8) |
| 6 | Kiosco | Recepción | 1 (descuento de socio usa 2) |
| 7 | Ingresos (rendición de caja) | Dueño y profes | 2 y/o 6 |
| 8 | Página pública (landing) | Público | — |
| 9 | Gestión de la página pública | Dueño | 8 |

Transversal a todos: optimización de imágenes, auditoría (quién hizo qué), manual de uso integrado.

---

## 1. Núcleo: panel, personal y seguridad

**Qué resuelve.** Un solo panel para el dueño y los profes, cada uno con lo que le corresponde, con
la seguridad de un sistema que maneja datos personales y plata.

- **Ingreso único** en `/ingresar` para personal y socios (el sistema detecta quién es).
- **Roles:** Administrador (todo) y Profe (atención, cobros y ventas; sin precios, sitio ni personal).
- **Alta de personal** desde el panel (Personal), baja lógica, rol editable.
- **Verificación en dos pasos** opcional u obligatoria (`STAFF_TWO_FACTOR_REQUIRED`), con códigos de
  recuperación y comando de rescate (`staff:disable-two-factor`).
- **Protección contra fuerza bruta:** bloqueo por usuario+IP y límite por IP; respuesta de tiempo
  constante (no revela si un DNI existe).
- **Cabeceras de seguridad**, HTTPS forzado, CSP (parte bloqueante, parte en modo aviso con registro).
- **Datos sensibles cifrados** (observaciones de salud) y archivos privados (foto, certificado).
- **Manual de uso** dentro del panel (solo administradores).
- **Escritorio** con contadores por estado, lista "Para cobrar" y lista "Para reponer" (kiosco).

Técnico: `App\Models\User`, `UserRoleEnum`, `App\Filament\Concerns\AdminOnly`, políticas en
`app/Policies`, `MemberSessionController`, middlewares `SecurityHeaders`,
`EnsureMemberSessionIsCurrent`, `EnsureMemberHasOwnPassword`.

## 2. Socios, membresías y cobros

**Qué resuelve.** Saber en todo momento quién está al día, quién debe y cuánto, sin planillas.

- **Ficha del socio:** datos personales, DNI, celular normalizado (+549…), email, foto, certificado
  de salud, observaciones de salud cifradas, consentimientos (avisos por WhatsApp y novedades, con
  fecha y origen).
- **Estados automáticos:** Entrenando (al día), En pausa (le toca renovar), Activo temporal
  (cortesía), Inactivo. El vencimiento se calcula solo a partir de las membresías.
- **Planes** con precio de lista, precio en efectivo y créditos (clases); 999 = libre. Baja lógica.
- **Membresías:** contrato con un plan, fechas, créditos restantes, estados de acceso; cambio de
  plan, cobro por adelantado.
- **Cobros:** efectivo o transferencia, monto sugerido según el medio, quién cobró. Punto único de
  entrada `Client::registerPayment()`.
- **Cortesía:** dejar entrar a un socio que debe, hasta que renueve.
- **Lista "Para cobrar"** con cobro en un clic.
- **Tareas automáticas nocturnas:** cierre de ciclos (01:00) y recarga mensual de créditos (01:05).
- **Restablecer la contraseña** del portal de un socio desde la ficha.

Técnico: `Client`, `Plan`, `Membership`, `Payment`, `ClientStatusEnum`, `MembershipStatusEnum`,
`PaymentMethodEnum`; `ClientResource` (+ acciones `RegisterPaymentAction`, `GrantCourtesyAction`,
`MembershipsRelationManager`), `PlanResource`; comandos `memberships:*`.

## 3. Ingreso con QR y asistencias

**Qué resuelve.** Control de acceso sin molinete ni hardware: un QR impreso y el celular del socio.

- **Puntos de acceso** (Recepción, otro salón…), cada uno con su QR imprimible con el logo.
- El socio **escanea con la cámara del celular**: abre `/ingreso/{código}`, confirma y queda
  registrado. Abrir el enlace no descuenta nada; solo el botón confirma.
- También desde **"Escanear QR"** del portal.
- Descuenta un crédito, muestra el resultado a pantalla completa (verde al día, ámbar si quedó
  debiendo o no pudo entrar).
- **Cambiar el código** de un punto invalida el QR impreso; límite de 10 confirmaciones por minuto.
- **Historial de asistencias** por socio y por punto; **termómetro de ocupación** en el portal (qué
  tan lleno está el gimnasio, sin mostrar cuántos hay).

Técnico: `AccessPoint`, `Attendance`, `MemberCheckIn`, `MemberScan`, `GymOccupancyMeter`,
`AccessPointResource`, página `CheckInQr`; librería `html5-qrcode`.

## 4. Portal del socio (PWA)

**Qué resuelve.** El socio ve su situación sin preguntar en recepción, y se instala como una app.

- **Instalable** en el celular (manifest + íconos), sin pasar por tiendas.
- **Inicio:** estado de la cuota, vencimiento, créditos, anuncios vigentes (que puede cerrar),
  aviso para completar el celular, ocupación del gimnasio.
- **Historial** de pagos y asistencias.
- **Mi cuenta:** cambiar contraseña (obligatorio el primer ingreso: la inicial es el DNI), celular,
  "mantener sesión iniciada", cierre de sesiones en otros dispositivos al cambiar la contraseña.
- **Escanear QR** de ingreso.

Técnico: `app/Livewire/Member*`, guard `client`, `resources/views/livewire`, layout `member`,
`public/manifest.json`.

## 5. Anuncios

**Qué resuelve.** Comunicar novedades a los socios sin depender de redes sociales.

- Texto con formato y/o imagen (banner), fechas de inicio y fin, encendido/apagado.
- Se muestran en el inicio del portal y en el botón **Novedades** de la página pública.
- Métrica de alcance (cuántos lo vieron o cerraron). Texto saneado (no admite código).

Técnico: `Announcement`, tabla `announcement_client`, `AnnouncementResource`.

## 6. Kiosco

**Qué resuelve.** Vender bebidas, suplementos y ropa en recepción con control de stock y de caja,
sin un sistema aparte.

- **Productos y categorías:** código de barras y SKU opcionales, precio de lista, precio en
  efectivo, costo, foto liviana, destacado, activo, baja lógica.
- **Stock con historial:** cargar mercadería (todo el personal), ajustar tras contar (solo
  administradores, con motivo), movimientos de solo lectura, lista "Para reponer".
- **Modo de stock configurable:** vender y avisar (de fábrica) o bloquear (`KIOSK_STOCK_MODE`).
- **Pantalla de venta** para PC y tablet: grilla de destacados y más vendidos, categorías, buscador
  que también recibe un **lector de códigos USB**, carrito, socio opcional, pago en efectivo (con
  vuelto), transferencia o mixto. Botón **Kiosco** fijo en la barra superior.
- **Descuento de socio** (15 % configurable) solo para socios al día; no se suma con el precio en
  efectivo (se cobra el más barato).
- **Historial de ventas** con filtros y total cobrado; **anulación** (solo administradores) que
  devuelve el stock y marca los cobros como reintegrados.
- **Preparado para pasarelas:** cada cobro guarda estado, proveedor y referencia externa (única).

Técnico: `Product`, `ProductCategory`, `StockMovement`, `Sale`, `SaleItem`, `SalePayment`,
`App\Services\Kiosk\SaleService` (único punto de venta y anulación), página `Kiosk`,
`ProductResource`, `SaleResource`, `StockMovementResource`.

## 7. Ingresos (rendición de caja)

**Qué resuelve.** Cuánto entró hoy, por qué medio y quién lo tiene que rendir, sin abrir y cerrar caja.

- Cuotas y kiosco juntos: total, efectivo, transferencia, separado por origen.
- Por persona (administradores) y por día; detalle de cada cobro.
- Atajos hoy / ayer / semana / mes. Un profe ve solo lo que cobró él.

Técnico: `App\Services\Income\IncomeReport`, página `Income`.

## 8. Página pública (landing)

**Qué resuelve.** Presencia web propia: presentación, planes con precio, horarios, comunidad,
opiniones y novedades, con acceso de socios.

- Portada con video o imagen, botones **Planes** (planes activos con precio) y **Novedades**.
- Secciones que se ocultan solas si no tienen datos (nunca muestra datos inventados).
- Enlace **Socios** al ingreso.

Técnico: `resources/views/welcome.blade.php`, ruta `/`. Página extra `curev-hue` (propia de este
gimnasio: escuela de windsurf "Próximamente").

## 9. Gestión de la página pública

**Qué resuelve.** El dueño cambia textos, horarios, fotos y opiniones sin pedirle nada al programador.

- Secciones editables: Portada, Horarios y ubicación, Comunidad, Opiniones, Videos e imágenes.
- Los planes y anuncios del panel se publican solos en la página.

Técnico: `SiteContent` (una fila por sección), `SiteSectionEnum`, páginas `Site*` / `SiteSectionPage`.

## Transversal

- **Imágenes:** el navegador achica antes de subir y el servidor recomprime a WebP liviano
  (≤ 300 KB) y descarta el original (`App\Support\ImageOptimizer`, GD, sin dependencias).
- **Auditoría:** cada cobro, venta, movimiento de stock y anulación guarda quién lo hizo.
- **Bajas lógicas** en lo que tiene historial; la base impide borrar lo que rompería registros.
- **Pruebas automatizadas:** ~400 tests de funcionalidad.

## Fuera del alcance actual

Pasarela de pago en línea, envío automático de WhatsApp o email, reservas de clases o turnos,
apertura y cierre de caja, fiado, facturación electrónica, multi-sede y rutinas de entrenamiento.
