# Backlog de ejecución: de Orions a plantilla vendible

Plan en tres fases, más los módulos comerciales que vienen después:

1. **Orions a producción** desde este repo.
2. **Plantilla** en un repo nuevo.
3. **Orions pasa a la plantilla** y se da de alta el primer gimnasio nuevo.
4. **Módulos comerciales**, ya sobre la plantilla.

Las decisiones de fondo están en el análisis previo y se resumen al final ("Decisiones tomadas").

## Convenciones

- **Prioridad.**
  - **P0:** sin esto no se avanza.
  - **P1:** importante, pero puede esperar a que esté el resto.
  - **P2:** mejora.
- **Estimación** en días de trabajo efectivo, orientativa.
- **Una rama por tarea**, commits en castellano y `merge --no-ff`, como hasta ahora.
- **Definición de terminado** (vale para todas las tareas):
  - tests en verde y Pint limpio;
  - si se tocaron estilos, `npm run build` y `public/build` commiteado;
  - README, manual y docs actualizados si cambia algo visible o de operación;
  - sin datos ni marca de un gimnasio en el código.

## Resumen y camino crítico

| Fase | Hito | Días P0 | Días con P1 y P2 |
| --- | --- | --- | --- |
| 1 | Orions en producción (repo actual) | 2 | 5 |
| 2 | Plantilla lista y gimnasio demo en línea | 14 | 20,5 |
| 3 | Orions sobre la plantilla y primer gimnasio nuevo | 2 | 2 |

Camino crítico: PRD-05 → TPL-01 → BRD-01 → BRD-02 → MOD-01 → MOD-02 → OPS-02 → DEM-01 → ADP-01.

En paralelo, lo que depende del dueño (PRD-01) no frena el desarrollo de la plantilla.

Antes del segundo gimnasio de clientes (después de ADP-03) tienen que estar OPS-06 (modo suspendido)
y OPS-07 (exportación de datos): son la base de la política de falta de pago y de baja que se firma
con cada cliente.

**Regla de convivencia.** Todo lo que se haga en este repo **antes** de TPL-01 viaja solo a la
plantilla. **Después** de TPL-01, este repo solo recibe arreglos urgentes de Orions, y cada uno se
replica a mano en la plantilla en el mismo día.

---

## Fase 1: Orions a producción (repo `orions-gym`)

### PRD-01 · Respuestas del dueño · P0 · sin desarrollo

- Enviar `docs/Orions - Preguntas para el arranque.pdf` y recibir las respuestas.
- **Aceptación:** respondidas las preguntas 1 a 6, 11, 15 y 16 (lo que bloquea el arranque); el resto puede llegar después.

### PRD-02 · `demo:remove` también borra el kiosco de ejemplo · P0 · 0,5 d

- **Archivos:** `app/Console/Commands/RemoveDemoData.php` y `database/seeders/DemoKioskSeeder.php` (para reusar `SKU_PREFIX`).
- **Subtareas:**
  - Borrar los productos `DEMO-…` **sin ventas** y sus movimientos.
  - Borrar las categorías del seeder que queden vacías.
  - Si un producto de ejemplo tiene ventas, no tocarlo y avisarlo en la salida del comando.
- **Aceptación:**
  - Después del comando no queda ningún producto `DEMO-…` sin ventas.
  - Las ventas reales no se tocan.
  - Hay tests para los dos casos.

### PRD-03 · Importar socios desde CSV · P1 · 1,5 d

Para Orions solo hace falta si el dueño tiene más de unos 40 socios; con menos, se cargan a mano.
Para la plantilla conviene igual: baja el costo de la etapa "carga inicial de datos" de cada setup
(ver `ESTRATEGIA_COMERCIAL_Y_PRECIOS.md`).

- **Archivos:** comando nuevo `clients:import {archivo} {--dry-run}`; usa `Client::normalizeCel` y la misma lógica de membresía que el alta del panel.
- **Columnas:** nombre, apellido, DNI, fecha de nacimiento, celular, email, plan y fecha de vencimiento.
- **Subtareas:**
  - `--dry-run` informa los errores fila por fila y no guarda nada.
  - El DNI repetido se saltea.
  - Toda la importación corre en una transacción.
- **Aceptación:**
  - Un CSV con filas válidas e inválidas importa solo las válidas.
  - Los estados y vencimientos quedan igual que si se cargaran a mano.

### PRD-04 · Importar productos desde CSV · P2 · 0,5 d

- **Archivos:** comando `products:import {archivo} {--dry-run}`.
- **Columnas:** nombre, categoría, precio, precio en efectivo, costo, código de barras y stock inicial (el stock entra como movimiento de entrada).
- **Aceptación:** el stock inicial queda registrado en Movimientos de stock.

### PRD-05 · Kiosco a master y deploy · P0 · 0,5 d

- **Subtareas:**
  - Merge de `kiosco-dev` a master y push.
  - En el servidor: backup de la base y de `APP_KEY`, después `php artisan migrate --force`, `optimize:clear`, `optimize` y `filament:optimize`.
  - Tag `v1.0-orions` en master.
- **Aceptación:** la verificación del paso 10 de `PUESTA_A_PUNTO_PRODUCCION.md` da todo bien.

### PRD-06 · Arranque limpio en producción · P0 · 1 d (con los datos del dueño)

- **Subtareas:** seguir el paso 9 de `PUESTA_A_PUNTO_PRODUCCION.md`.
  - `SEED_DEMO_DATA=false` y `demo:remove`.
  - Cargar personal, planes, socios (o PRD-03), productos y stock.
  - Crear los puntos de acceso, imprimir los QR y completar las secciones del sitio.
- **Aceptación:** no queda ningún dato de ejemplo y el primer día se puede atender, cobrar y vender.

### PRD-07 · Dominio definitivo · P1 · 0,25 d · bloqueada por el dueño

- **Subtareas:** cambiar `APP_URL`, `config:cache`, revisar el SSL y reimprimir los QR.
- **Aceptación:** el QR impreso abre `https://dominio-definitivo/ingreso/…`.

### PRD-08 · Botón de WhatsApp en el panel · P1 · 1 d · solo si el dueño lo aprueba (pregunta 31)

- **Archivos:**
  - acción en `ClientResource` (ficha) y en el widget `ClientsToCollect`;
  - textos en `config` o editables en el panel, nunca escritos en las vistas.
- **Subtareas:**
  - Generar el enlace `https://wa.me/<cel E.164 sin +>?text=…` con el mensaje de recordatorio o de cuota vencida.
  - Mostrar el botón solo si el socio aceptó avisos (`acepta_avisos_whatsapp`) y tiene un celular válido (`hasValidCel()`).
- **Aceptación:** desde "Para cobrar", un clic abre WhatsApp con el mensaje correcto para ese socio.

---

## Fase 2: Plantilla (repo nuevo)

### TPL-01 · Crear el repo de la plantilla · P0 · 0,5 d

- **Subtareas:**
  - `git archive v1.0-orions` (o la copia de master, si se suman tareas antes) a un repo privado nuevo, sin historial.
  - Primer commit "Base desde Orions v1.0".
  - Sacar `docs/Orions - *` y copiar este backlog.
  - Ramas: `master` para integración y `stable` para lo que despliegan los gimnasios (ver OPS-05).
- **Aceptación:** el repo nuevo pasa todos los tests y no tiene historial previo.

### Bloque marca

#### BRD-01 · Marca por configuración · P0 · 1 d

- **Archivos:**
  - `config/brand.php`: nombre, nombre corto, colores principal, acento y fondo, y rutas del logo, del ícono y del logo del QR;
  - helper `App\Support\Brand`.
- **Subtareas:**
  - Valores desde `.env` (`BRAND_NAME`, `BRAND_PRIMARY`…), con los de Orions como ejemplo en `.env.example`.
  - Los archivos de marca se leen de `storage/app/public/brand/` y, si faltan, de genéricos neutros que vienen en el repo.
- **Aceptación:** con un `.env` distinto, el nombre y los archivos de marca cambian sin tocar código.

#### BRD-02 · Colores por variables CSS · P0 · 1,5 d

- **Archivos:**
  - `resources/css/app.css` (`@theme`: los tokens `salvia`, `oxido`, `carbon`… pasan a apuntar a `var(--brand-…)`);
  - los layouts públicos y del portal (`welcome`, `layouts/member`, `partials/member-head`, `auth/*`), que inyectan `:root { --brand-… }` desde `Brand`;
  - `AdminPanelProvider::colors()` y `filament/admin/styles.blade.php`, que pasan a usar `Brand`;
  - las metas `theme-color`.
- **Subtareas:**
  - Generar la escala del panel (50 a 950) a partir del color principal, con `Color::hex()`.
  - Buscar colores sueltos (`#598F8B`, `#161817`, `#d9772f`…) y reemplazarlos.
- **Aceptación:**
  - Un mismo `public/build` sirve dos paletas distintas solo cambiando el `.env`.
  - Un test verifica que el layout imprime las variables.

#### BRD-03 · Sacar la marca fija del código · P0 · 1 d

- **Archivos:**
  - `AdminPanelProvider` (`brandName` del panel y de la verificación en dos pasos);
  - `auth/login`, `auth/two-factor`, `layouts/member` y `welcome` (los `alt` y el logo);
  - `CheckInQr` (el logo del QR desde `Brand`);
  - el manual (2 menciones), el README y los comentarios de `AppServiceProvider`.
- **Aceptación:** `grep -ri orions` sobre `app`, `config`, `routes`, `resources` y `database` solo devuelve clases CSS (o nada, si ya se hizo CLN-04).

#### BRD-04 · Manifest de la app generado al momento · P0 · 0,5 d

- **Archivos:**
  - ruta `/manifest.webmanifest` armada desde `Brand`;
  - se borra `public/manifest.json`;
  - se actualiza `partials/member-head`.
- **Aceptación:**
  - La PWA se instala con el nombre y el ícono del gimnasio.
  - Un test valida el JSON.

#### BRD-05 · Pantalla "Identidad del gimnasio" en el panel · P1 · 1,5 d

- **Archivos:** tabla `settings` (clave → valor, con caché), página Filament solo para administradores y `Brand` leyendo primero `settings` y después `config`.
- **Subtareas:**
  - Al subir el ícono de 512 px, generar con GD las versiones de 192, 180 y maskable.
  - Subir logo y logo del QR (PNG, que ya pasa por `ImageOptimizer`).
  - Elegir los colores con un selector.
- **Aceptación:** el dueño cambia el logo y los colores desde el panel, y se ven en el panel, el portal y la landing.

### Bloque módulos

Hay dos tipos de módulos:

- **Siempre prendidos (base):** núcleo, socios y cobros, e ingresos.
- **Se prenden por `.env`:** `portal` (portal, QR de ingreso y ocupación), `announcements`, `kiosk` y `site` (landing y su gestión).
- **Dependencias:** `announcements` necesita `portal` o `site`.

#### MOD-01 · Interruptores de módulos · P0 · 0,5 d

- **Archivos:**
  - `config/modules.php` (`MODULE_PORTAL`, `MODULE_ANNOUNCEMENTS`, `MODULE_KIOSK`, `MODULE_SITE`, todos en `true` por defecto);
  - enum `App\Enums\Module`;
  - `App\Support\Modules::enabled()`.
- **Subtareas:** un helper de tests para prender y apagar módulos.
- **Aceptación:** `Modules::enabled(Module::Kiosk)` responde según el `.env`, con un test para cada caso.

#### MOD-02 · Panel organizado por módulo · P0 · 1,5 d

- **Archivos:**
  - mover los recursos, páginas y widgets a `app/Filament/{Core,Members,Portal,Announcements,Kiosk,Site}`;
  - `AdminPanelProvider` descubre solo las carpetas de los módulos prendidos;
  - actualizar los `use` de los tests.
- **Aceptación:** con `MODULE_KIOSK=false` no aparece nada del kiosco en el menú y sus URLs devuelven 404.

#### MOD-03 · Rutas, botones, widgets y cron por módulo · P0 · 1 d

- **Archivos:**
  - `routes/modules/portal.php` y `routes/modules/site.php`, que se cargan solo con el módulo prendido;
  - el botón "Kiosco" de la barra superior;
  - los widgets "Para reponer" y "Para cobrar";
  - el enlace a Novedades.
- **Subtareas:** sin `site`, la ruta `/` redirige a `/ingresar`.
- **Aceptación:** apagando cada módulo, no queda ningún enlace roto ni ninguna ruta suya.

#### MOD-04 · Degradación entre módulos · P0 · 1 d

- **Subtareas:**
  - La venta del kiosco sigue funcionando sin portal.
  - Ingresos muestra el origen "Kiosco" solo si el módulo está prendido.
  - Los anuncios se ofrecen solo si hay dónde mostrarlos.
  - El manual oculta las secciones de los módulos apagados.
- **Aceptación:** ninguna pantalla falla con cualquier combinación de módulos.

#### MOD-05 · Tests con módulos apagados · P0 · 1 d

- **Subtareas:**
  - Un data provider que recorra las combinaciones de módulos.
  - En cada una: el panel, el escritorio, el portal y `/` cargan; el menú oculta lo apagado; las URLs de los módulos apagados dan 404.
- **Aceptación:** la suite pasa con todos los módulos prendidos, con todos apagados y con cada uno apagado por separado.

### Bloque limpieza

**Decisión:** las migraciones no se consolidan. En una instalación nueva corren sin efecto, y consolidarlas complicaría ADP-01.

#### CLN-01 · Sacar Curev Hue y los videos del repo · P0 · 0,5 d

- **Archivos:** `resources/views/curev-hue.blade.php`, la ruta `curev-hue`, los enlaces en `welcome` y `public/images/video-windsurf.*` y `logo.webp`.
- **Subtareas:** el video y las imágenes de la portada salen siempre de lo que se sube en "Videos e imágenes".
- **Aceptación:** el repo no tiene archivos multimedia de Orions y la landing se ve bien sin ellos.

#### CLN-02 · Sacar la configuración heredada · P0 · 0,5 d

- **Archivos:**
  - `config/gym.php`: se va `checkin_token`, y el bloque del kiosco pasa a `config/modules.php` o a `config/kiosk.php`;
  - `.env.example`;
  - los tests de `SecureDefaultsTest` que dependen del token.
- **Aceptación:** la migración de puntos de acceso sigue funcionando (sin token genera uno al azar) y los tests pasan.

#### CLN-03 · Datos de ejemplo unificados · P1 · 1 d

- **Archivos:**
  - comando `demo:seed` (clientes, anuncios y kiosco) y `demo:remove` (todo lo anterior);
  - `DatabaseSeeder`, que queda solo con el administrador y los planes base.
- **Aceptación:** `demo:seed` seguido de `demo:remove` deja la base igual que antes.

#### CLN-04 · Renombrar las clases `orions-*` · P2 · 0,5 d

- Reemplazo mecánico por `gym-*` en las vistas y en `styles.blade.php`, seguido de `npm run build`.
- **Aceptación:** `grep -r "orions-"` no devuelve nada y los tests de vistas pasan.

#### CLN-05 · Documentación neutra · P1 · 0,5 d

- **Archivos:**
  - README con título genérico;
  - el manual dice "el gimnasio";
  - `docs/MODULOS_Y_FUNCIONALIDADES.md` aclara qué módulos son opcionales.
- **Aceptación:** ningún documento nombra a Orions.

### Bloque operación

#### OPS-01 · `gym:doctor` · P0 · 1 d

- **Qué revisa:**
  - versión de PHP y extensiones (GD con WebP, intl, exif, pdo_mysql);
  - `memory_limit` y tamaño máximo de subida;
  - `APP_DEBUG=false`, `APP_URL` con https y `SEED_DEMO_DATA=false`;
  - enlace de storage y carpetas con permiso de escritura;
  - migraciones pendientes;
  - última corrida del cron (el scheduler guarda la hora en la caché cada minuto).
- **Salida:** una tabla con OK o Atención en cada punto y el código de salida que corresponda.
- **Aceptación:** con un servidor mal configurado, señala exactamente qué falta.

#### OPS-02 · `gym:install` · P0 · 1 d

- **Pasos que hace:**
  1. Corre `gym:doctor`.
  2. `key:generate` si no hay clave.
  3. `migrate --force`.
  4. Crea el administrador (pregunta los datos o los toma de parámetros).
  5. Carga los planes base de forma opcional.
  6. Crea el enlace de storage; si `symlink` está deshabilitado, indica cómo crearlo a mano.
  7. Imprime la línea del cron con las rutas reales.
  8. Recuerda guardar la `APP_KEY`.
- Se puede correr más de una vez sin romper nada.
- **Aceptación:** un sitio nuevo de Hostinger queda funcionando con `.env` y `gym:install`.

#### OPS-03 · `gym:deploy` · P0 · 0,5 d

- `migrate --force`, `optimize:clear`, `optimize` y `filament:optimize`, con un resumen al final.
- **Aceptación:** es el único comando que hay que correr por SSH después de cada push.

#### OPS-04 · Guía de alta de un gimnasio nuevo · P1 · 0,5 d

- `docs/ALTA_GIMNASIO.md`: crear el sitio en hPanel, PHP, MySQL, Git (rama `stable`), `.env` (marca y módulos), `gym:install`, cron, SSL, marca en el panel y QR.
- **Aceptación:** otra persona da de alta un gimnasio siguiendo solo la guía.

#### OPS-05 · Ramas y versiones · P0 · 0,25 d

- Se trabaja en `master`; cuando el gimnasio demo lo valida, se pasa a `stable` con un tag `vX.Y`.
- Todos los gimnasios despliegan desde `stable`.
- `CHANGELOG.md` corto con qué cambió en cada versión.
- **Aceptación:** un push a `master` no llega a ningún gimnasio de clientes.

### Bloque comercial

Tareas que salen de `ESTRATEGIA_COMERCIAL_Y_PRECIOS.md`. No hacen falta para Orions, pero sí
**antes del segundo gimnasio de clientes**: sostienen la política de falta de pago y la promesa de
que los datos son del gimnasio. Los importadores de socios y productos ya están como PRD-03 y
PRD-04; si se hacen antes de TPL-01, viajan solos a la plantilla y bajan el costo de cada setup.

#### OPS-06 · Modo suspendido por falta de pago · P1 · 1 d

- **Archivos:**
  - `config/gym.php` (o `config/modules.php` tras CLN-02): `GYM_SUSPENDED=false`;
  - un middleware del panel y un aviso fijo en ámbar (render hook) con el texto en `config`;
  - un `Gate::before` que niega crear, editar y borrar mientras está suspendido (hoy solo hay
    `ProductPolicy` y `SalePolicy`; el resto de los recursos no tiene política propia);
  - las acciones que no pasan por políticas (cobrar, cortesía, venta del kiosco, carga y ajuste de
    stock, páginas del sitio) chequean el modo suspendido en un solo helper.
- **Subtareas:**
  - El panel queda en **solo lectura**: se puede consultar, pero no cobrar, vender, cargar stock ni
    editar.
  - El portal del socio y el ingreso con QR **siguen andando** (no se castiga a los socios).
  - Las tareas nocturnas siguen corriendo, para que los estados no queden desfasados al reactivar.
  - `gym:doctor` muestra "Suspendido" como Atención.
- **Aceptación:**
  - Con `GYM_SUSPENDED=true`, ninguna acción que guarde datos aparece ni funciona en el panel, y un
    socio entra con QR normalmente.
  - Al volver a `false` (y `config:cache`), todo funciona igual que antes, sin perder datos.
  - Tests para los dos estados.

#### OPS-07 · Exportación de datos del gimnasio · P1 · 1 d

- **Archivos:** comando `gym:export {--desde=} {--hasta=}` que arma un ZIP en
  `storage/app/exports/` (privado, nunca en `public`).
- **Contenido (CSV en UTF-8 con BOM, para que Excel muestre bien los acentos):** socios, membresías,
  pagos de cuotas, asistencias, productos, ventas con sus ítems y cobros, y movimientos de stock.
- **Subtareas:**
  - Las observaciones de salud salen **descifradas** solo con `--incluir-salud`, y el comando avisa
    que el archivo contiene datos sensibles.
  - Sin fotos ni certificados por defecto; `--con-archivos` los suma al ZIP.
  - Lectura por bloques (`chunk`) para no pasarse de memoria en Hostinger.
- **Aceptación:**
  - Los CSV abren bien en Excel y cada total coincide con lo que muestra el panel.
  - Un test arma la exportación con datos de ejemplo y verifica columnas y cantidades.

#### OPS-08 · Endpoint de salud para el monitoreo · P1 · 0,5 d

- **Archivos:** la ruta `/up` que ya trae Laravel (`bootstrap/app.php`) se complementa con
  `/up/full`: base de datos, escritura en `storage` y **última corrida del cron** (el mismo dato que
  guarda OPS-01).
- **Subtareas:**
  - Responde 200 si todo está bien y 503 si algo falla, sin mostrar detalles internos ni versiones.
  - Límite de pedidos por minuto.
  - Agregar el alta en UptimeRobot (plan gratuito) a `ALTA_GIMNASIO.md` (OPS-04).
- **Aceptación:**
  - Con el cron parado más de 2 horas, `/up/full` devuelve 503 y UptimeRobot avisa.
  - Tests para cada falla.

### Bloque demo

#### DEM-01 · Gimnasio demo en línea · P0 · 1 d

- Un subdominio en Hostinger, desplegado desde `master`, con todos los módulos y `demo:seed`.
- **Uso doble:** prueba final antes de pasar a `stable` y demostración comercial.
- **Opcional (P2):** cron nocturno que hace `demo:remove` y `demo:seed` para dejarlo limpio cada día.
- **Aceptación:** instalado solo con `ALTA_GIMNASIO.md` y `gym:install`, en menos de una hora.

---

## Fase 3: adopción

### ADP-01 · Orions sobre la plantilla · P0 · 1 d

- **Subtareas:**
  1. Backup de la base, de `storage` y de `APP_KEY`.
  2. En hPanel, cambiar el repo de Git al de la plantilla (rama `stable`).
  3. Completar el `.env` con la marca y los módulos.
  4. `gym:deploy` (la única migración nueva es la de `settings`, si se hizo BRD-05).
  5. Subir el logo, los íconos y el video desde el panel.
- **Curev Hue:** preguntarle al dueño si la sigue necesitando. Si la necesita, se resuelve como función genérica ("página extra" en la gestión de la landing), nunca con código propio de Orions.
- **Aceptación:**
  - Orions funciona igual que antes, con la misma base de datos.
  - Los QR impresos siguen sirviendo.
  - Ningún socio nota el cambio.

### ADP-02 · Archivar el repo `orions-gym` · P1 · 0,1 d

- Dos semanas después de ADP-01 sin incidentes, archivarlo en GitHub (solo lectura).

### ADP-03 · Primer gimnasio nuevo · P0 · 1 d

- Alta siguiendo `ALTA_GIMNASIO.md`; medir cuánto tarda y corregir la guía.
- **Aceptación:** el alta técnica lleva menos de 2 horas, sin contar la carga de datos del gimnasio.

---

## Fase 4: módulos comerciales (sobre la plantilla)

Cada uno se baja a su propio backlog cuando se decida. Están ordenados por valor para vender y estimados de forma gruesa.

| ID | Módulo | Prioridad | Estimación | Depende de |
| --- | --- | --- | --- | --- |
| COM-01 | Botón de WhatsApp (si no se hizo PRD-08) | P1 | 1 d | — |
| COM-02 | Cobros unificados: cuotas y kiosco en una tabla genérica (ex KIO-10) | P0 para cobrar online | 3–4 d | — |
| COM-03 | Mercado Pago: QR y link de pago, webhook firmado, pendiente → aprobado | P1 | 4–6 d | COM-02 |
| COM-04 | Débito automático de la cuota (suscripciones de Mercado Pago) | P1 | 3–5 d | COM-03 |
| COM-05 | Reservas de clases con cupo (actividades, horarios, profe y lista de espera) | P1 | 6–10 d | — |
| COM-06 | Retención y prospectos (socios en riesgo, formulario de clase de prueba, seguimiento) | P2 | 3–5 d | — |
| COM-07 | WhatsApp automático (API oficial, plantillas, cola procesada desde el cron) | P2 | 4–6 d | COM-01 |
| COM-08 | Caja con apertura y cierre, y fiado (ex KIO-07 y KIO-08) | P2 | 4–6 d | — |
| COM-09 | Facturación electrónica ARCA | P2 | 6–10 d | COM-02 |
| COM-10 | Rutinas y progreso en el portal | P2 | 6–10 d | — |

Cada módulo nuevo nace con su interruptor `MODULE_*`, sus tests con el módulo apagado y su sección del manual.

---

## Decisiones tomadas

- **Base de la plantilla:** este proyecto. Desde cero no.
- **Repo:** nuevo y sin historial. Fork no, y nunca una rama o un fork por gimnasio.
- **Instalación:** una por gimnasio (single-tenant) en Hostinger. Multi-tenant recién si la cantidad de clientes lo justifica.
- **Módulos:** se prenden por `.env`, con carpetas por módulo dentro de la misma app. Sin paquetes Composer.
- **Migraciones:** corren todas siempre y no se consolidan.
- **Colores:** variables CSS, porque el CSS se compila una sola vez y se commitea.
- **Colas:** sin worker permanente. `QUEUE_CONNECTION=sync`, y cuando haga falta una cola, se procesa desde el cron.
- **Orions:** se pasa a la plantilla (ADP-01). No se mantienen dos códigos.
