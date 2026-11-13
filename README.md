# Orions Gym

Sistema de gestión del gimnasio Orions. Tiene tres partes:

| Parte | Dirección | Quién la usa |
| --- | --- | --- |
| Sitio público (landing) | `/` | Cualquiera: planes y novedades |
| Portal del socio (app del celular) | `/mi-perfil` | Los clientes del gimnasio |
| Panel de administración | `/admin` | El personal: dueños y profes |

Todos entran por la misma pantalla, `/ingresar`: el socio va a su portal y el personal al panel.

Hoy hay dos tipos de cuenta: **personal** (dueños y profes, todos con los mismos permisos de
administrador) y **socios**. No hay roles diferenciados dentro del personal.

## Índice

1. [Guía de uso para el personal](#guía-de-uso-para-el-personal)
2. [Guía de uso para el socio](#guía-de-uso-para-el-socio)
3. [Sitio público](#sitio-público)
4. [Tareas automáticas](#tareas-automáticas)
5. [Puesta en marcha en desarrollo](#puesta-en-marcha-en-desarrollo)
6. [Despliegue en Hostinger](#despliegue-en-hostinger)
7. [Seguridad en producción](#seguridad-en-producción)
8. [Referencia técnica](#referencia-técnica)

## Guía de uso para el personal

Se entra en `/ingresar` (o `/admin/login`) con el email y la contraseña de la cuenta. El menú
tiene **Escritorio**, **Clientes**, **QR de ingreso** y el grupo **Admin del sitio**.

### Escritorio

Muestra tres contadores:

- **Entrenando:** clientes con la cuota al día.
- **Deben la cuota:** clientes “En pausa” (terminaron su mes y les toca renovar).
- **Vencen en 7 días:** clientes “Entrenando” o “Activo temporal” que vencen entre hoy y los
  próximos siete días.

Debajo está **Para cobrar**: los clientes cuyo vencimiento ya pasó o cae en los próximos siete
días. Desde cada fila se abre la ficha o se inicia el cobro.

### Clientes

**Dar de alta.** Botón **Crear cliente**. La primera pestaña pide los datos (nombre, apellido,
DNI, fecha de nacimiento, celular, email) y, al final, las observaciones y dolencias. La segunda
permite subir la foto (JPG, PNG o WebP) y el certificado de salud (PDF o foto), hasta 2 MB cada
uno. DNI y email no se pueden repetir, ni siquiera con clientes dados de baja.

**Buscar.** Por nombre, apellido o DNI. Las pestañas filtran por estado y hay un filtro para ver
los dados de baja.

**Ficha.** Reúne datos personales, estado, vencimiento, membresía vigente, créditos, último pago
e información de salud, con pestañas para las membresías y el historial de pagos. La foto y el
certificado se guardan en un almacenamiento privado y se abren con enlaces que vencen a los 30
minutos.

**Modificar.** Botón **Editar** de la ficha o del listado.

**Dar de baja.** Es una baja lógica: el cliente desaparece del listado y ya no puede entrar al
portal, pero se conserva su historial. Se puede restaurar desde el filtro de dados de baja.

**Acceso del socio al portal.** No hay que crearle nada: todo cliente cargado entra al portal con
su DNI como usuario y contraseña, y en el primer ingreso el sistema lo obliga a elegir una
contraseña propia (ver [Guía de uso para el socio](#guía-de-uso-para-el-socio)).

### Membresías

Una membresía une a un cliente con un plan: fecha de inicio, vencimiento, estado y créditos. Se
crean desde la ficha del cliente, pestaña **Membresías**. Al crearla, los créditos se copian del
plan y vence un mes después del inicio.

| Estado del cliente | Significado |
| --- | --- |
| Entrenando | Cuota al día |
| En pausa | Terminó su mes: le toca renovar |
| Activo temporal | Entra por cortesía hasta renovar |
| Inactivo | Sin actividad |

| Estado de la membresía | Puede entrar al gimnasio | Admite pagos |
| --- | --- | --- |
| Entrenando | Sí | Sí |
| En pausa | No | Sí |
| Activo flex | Sí, por cortesía | Sí |
| Vencida | No | No |

### Cobrar

**Registrar pago**, desde el Escritorio, el listado o la ficha del cliente. Se elige la membresía
(si tiene más de una), el monto (se sugiere el precio del plan, se puede cambiar) y el medio:
efectivo o transferencia. Queda guardado quién cobró y cuándo.

- El cobro deja al cliente en **Entrenando**.
- Si la membresía ya había terminado su ciclo (En pausa, Activo flex o con la fecha pasada), el
  pago **inicia el mes nuevo**: un mes de vigencia desde el día del pago y los créditos del plan.
  No se arrastran los créditos que sobraron ni los que quedó debiendo.
- Si la membresía todavía está en su ciclo, el pago no cambia fechas ni créditos.
- Los pagos no se pueden editar ni borrar desde el panel.

### Dar cortesía

Acción **Dar cortesía** en la ficha del cliente o en su membresía. Pasa una membresía
**Entrenando** o **En pausa** a **Activo flex** y al cliente a **Activo temporal**: puede escanear
y entrar aunque su fecha haya terminado. No cambia la fecha ni los créditos, y no termina sola:
dura hasta que el socio paga o hasta que se le cambia el estado a mano.

### QR de ingreso

**QR de ingreso** muestra el cartel con el código QR del gimnasio (con la calavera en el centro)
y un botón para imprimirlo. Se imprime una vez y se pega en recepción: los socios lo escanean
desde su portal para registrar el ingreso.

El QR es el mismo para todos y no cambia solo. Si hubiera que invalidarlo (por ejemplo, porque
alguien registra ingresos desde su casa con una foto), se cambia `GYM_CHECKIN_TOKEN` en el `.env`
del servidor, se corre `php artisan config:cache` y se imprime el cartel de nuevo.

### Admin del sitio

**Planes.** El catálogo que se ofrece: nombre, precio y créditos (ingresos por mes). `999`
créditos significa pase libre. Se pueden editar, dar de baja y restaurar; un plan dado de baja
deja de ofrecerse pero se conserva en las membresías viejas. Los planes activos se muestran en el
sitio público.

**Anuncios.** Avisos que ven los socios en el inicio de su portal y el público en el botón
“Novedades” del sitio. Cada anuncio lleva título (obligatorio), texto con formato, imagen (JPG,
PNG o WebP, hasta 2 MB) o las dos cosas. Opciones:

- **Activo:** el interruptor lo apaga o lo enciende sin borrarlo, también desde el listado.
- **Mostrar desde / hasta:** fechas opcionales para programarlo y para que se dé de baja solo.
- La columna **Situación** resume el resultado: Visible, Programado, Finalizado o Apagado.

Cada socio puede cerrar un anuncio en su portal y no le vuelve a aparecer; a los demás sí.

**Portada, Horarios, Comunidad y Opiniones.** Todavía no están: muestran “Próximamente”.

## Guía de uso para el socio

### Entrar

En `/ingresar`, con el **DNI** (o el email) como usuario. La primera vez la contraseña también es
el DNI, y el portal **obliga a elegir una contraseña propia** antes de mostrar nada más: al
menos 8 caracteres, con letras y números, y distinta del DNI. Marcando “Mantener la sesión iniciada”, queda recordada en el
celular.

Si un socio olvida su contraseña, el personal la restablece desde su ficha con **Restablecer
contraseña**: vuelve a entrar con el DNI y el portal le pide que elija una nueva.

### Instalarlo como app

El portal es una PWA. En el celular, desde el navegador: **Agregar a la pantalla de inicio**
(Chrome en Android) o **Compartir → Agregar a inicio** (Safari en iPhone). Se abre a pantalla
completa, directo en el portal.

### Pantallas

- **Inicio.** Arriba, el **termómetro de ocupación**: “Tranqui”, “Movido” o “A pleno” según
  cuánta gente ingresó en los últimos 90 minutos (hasta 15, hasta 25 o más). No muestra el número
  y se actualiza solo cada 5 minutos. Después, los anuncios vigentes y la tarjeta de la membresía:
  estado, créditos disponibles (o “Pase libre”), plan, vencimiento y días que faltan.
- **Escanear QR.** Abre la cámara para leer el QR de recepción. Si el socio puede entrar, la
  pantalla se pone verde, suena, y se le descuenta un crédito (el saldo puede quedar en
  negativo). Si vuelve a escanear dentro de los 10 minutos, no se le descuenta otro. Si la
  membresía está en pausa o vencida, avisa que pase por recepción y no registra el ingreso. La
  cámara solo funciona con el sitio en HTTPS.
- **Historial.** Dos pestañas: los últimos pagos y las últimas asistencias.
- **Mi cuenta.** Sus datos (solo lectura: los corrige el personal), el cambio de contraseña y
  cerrar sesión.

Un socio nunca ve datos de otro, y el portal no muestra sus dolencias, certificado, teléfono ni
fecha de nacimiento.

## Sitio público

La página de inicio muestra la presentación del gimnasio, el botón **Planes** (los planes activos
con su precio) y, si hay anuncios vigentes, el botón **Novedades**. El enlace **Socios** lleva a
`/ingresar`.

## Tareas automáticas

Dos comandos corren solos todas las madrugadas (hora argentina):

| Hora | Comando | Qué hace |
| --- | --- | --- |
| 01:00 | `memberships:update-completed-cycles` | Pasa a **En pausa** las membresías **Entrenando** cuya fecha de vencimiento es anterior a hoy, y al cliente si ninguna otra membresía le permite entrar. No toca las que están en **Activo flex**. |
| 01:05 | `memberships:renew-credits` | Red de seguridad: inicia el mes nuevo en las membresías con el ciclo terminado que tienen un pago posterior a su vencimiento y quedaron sin renovar. |

Se pueden correr a mano y correrlos de más no cambia nada. Para que corran solos hay que activar
el cron en el servidor (ver [Despliegue en Hostinger](#despliegue-en-hostinger)).

## Puesta en marcha en desarrollo

Requiere PHP 8.3 o superior, Composer y Node.js.

```bash
composer setup        # dependencias, .env, clave, migraciones y assets
```

Completar en `.env` `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD`, y después:

```bash
php artisan db:seed   # administrador, planes y (con SEED_DEMO_DATA=true) datos de ejemplo
composer run dev      # servidor en http://localhost:8000
php artisan test      # pruebas
```

Los assets compilados (`public/build`) se guardan en el repositorio: después de tocar CSS, JS o
clases de Tailwind hay que correr `npm run build` y commitear el resultado.

### Datos iniciales y de ejemplo

`php artisan db:seed` es el único comando de carga. Crea o actualiza el administrador del `.env`
y crea los planes base que falten. Se puede correr las veces que haga falta: no duplica nada y no
pisa planes, clientes ni anuncios existentes. Lo único que vuelve a escribir es el nombre y la
contraseña del administrador, con los valores del `.env`.

Con `SEED_DEMO_DATA=true` carga además 11 clientes de ejemplo (DNI `30100001` a `30100011`) con
membresías y pagos, y 6 anuncios de ejemplo. **Es solo para desarrollo**: esos DNI están en el
código y cualquiera podría entrar al portal con ellos. Para borrarlos:

```bash
php artisan demo:remove
```

Borra definitivamente los clientes de ejemplo (con sus membresías, pagos y asistencias) y los
anuncios de ejemplo. No toca nada más. Un cliente de ejemplo al que se le cambió el email desde
el panel se considera real y no se borra.

## Despliegue en Hostinger

Los comandos se corren por SSH (hPanel → Avanzado → Acceso SSH) o en la Terminal del navegador,
parado en la carpeta del proyecto, donde está el archivo `artisan`:

```bash
cd ~/domains/tudominio.com/public_html
```

### Cada vez que se sube una versión nueva

```bash
git pull origin master                 # trae el código (los assets ya vienen compilados)
composer install --no-dev --optimize-autoloader
php artisan migrate --force            # aplica los cambios de la base
php artisan optimize:clear             # limpia cachés viejas
php artisan config:cache               # vuelve a cachear la configuración
php artisan route:cache
php artisan view:cache
```

Si el despliegue se hace con el Git de hPanel, el `git pull` lo hace hPanel; el resto se corre
igual. Si `composer` no está disponible como comando, probar con `composer2` o
`php composer.phar`.

### Solo la primera vez

1. Crear el `.env` a partir de `.env.example`, con los valores de
   [Seguridad en producción](#seguridad-en-producción), los datos de la base y `ADMIN_*`.
2. Correr:

   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force          # administrador y planes
   php artisan storage:link             # para que se vean las imágenes de los anuncios
   ```

   Si `storage:link` falla, crear el enlace `public/storage` → `storage/app/public` desde el
   Administrador de archivos de hPanel.
3. Confirmar en hPanel que el certificado SSL del dominio está activo: el sitio redirige todo a
   HTTPS.
4. Activar el cron de las tareas automáticas: hPanel → Avanzado → **Tareas cron** → tipo
   **Personalizado**, cada minuto (`* * * * *`; si el plan no lo permite, `*/5 * * * *`):

   ```bash
   /usr/bin/php /home/u123456789/domains/tudominio.com/public_html/artisan schedule:run >> /dev/null 2>&1
   ```

   Reemplazar `u123456789` y `tudominio.com`. Para comprobarlo: `php artisan schedule:list`.

### Comandos útiles en el servidor

| Comando | Para qué |
| --- | --- |
| `php artisan migrate:status` | Ver qué migraciones faltan aplicar |
| `php artisan demo:remove` | Borrar los clientes y anuncios de ejemplo |
| `php artisan db:seed --force` | Recrear el administrador del `.env` y los planes base que falten |
| `php artisan schedule:list` | Ver las tareas automáticas y cuándo corren |
| `php artisan memberships:update-completed-cycles` | Correr a mano el cierre de ciclos |
| `php artisan memberships:renew-credits` | Correr a mano la recarga de créditos |
| `php artisan config:cache` | Aplicar un cambio hecho en el `.env` |
| `php artisan optimize:clear` | Limpiar todas las cachés si algo quedó viejo |
| `php artisan down` / `php artisan up` | Poner y sacar el sitio de mantenimiento |
| `tail -n 50 storage/logs/laravel.log` | Ver los últimos errores |

## Seguridad en producción

### Valores del `.env`

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SEED_DEMO_DATA=false
GYM_CHECKIN_TOKEN=una-cadena-larga-al-azar
```

`APP_DEBUG=true` en producción muestra contraseñas y datos internos ante cualquier error: tiene
que estar en `false`. Después de cambiar el `.env`, correr `php artisan config:cache`. Cambiar
`GYM_CHECKIN_TOKEN` obliga a reimprimir el QR de recepción.

### Qué protege el sistema

- **Ingreso.** Tras 5 intentos fallidos con el mismo usuario desde la misma IP, bloquea un
  minuto. Además, una misma IP puede intentar hasta 10 ingresos por minuto y 60 por hora, para
  que nadie pruebe un DNI tras otro.
- **Contraseña del socio.** Mientras sea el DNI, solo puede entrar a “Mi cuenta” a cambiarla.
  Si la olvida, el personal la restablece desde la ficha: nadie más que el socio conoce la nueva.
- **Sesiones del socio.** “Mantener la sesión iniciada” es opcional al ingresar. Al cambiar o
  restablecer la contraseña, se cierran las sesiones abiertas en los demás dispositivos.
- **Verificación en dos pasos del personal.** Cada cuenta la activa desde su **Perfil** (menú de
  usuario del panel) con una app de autenticación, y guarda los códigos de recuperación. Con
  `STAFF_TWO_FACTOR_REQUIRED=true` en el `.env`, el panel obliga a activarla. Quien la tiene
  activa ingresa igual por `/ingresar`: después de la contraseña se le pide el código de la app (o
  un código de recuperación). `/admin/login` también lo pide. Si alguien pierde el celular y los
  códigos: `php artisan staff:disable-two-factor su@email.com` desde el servidor.
- **Datos entre socios.** Las pantallas del portal solo leen los datos del socio con sesión.
- **Archivos.** Foto y certificado van a un disco privado. No se aceptan SVG en ninguna subida.
- **Texto de los anuncios.** Se limpia antes de mostrarse: no puede traer código.
- **Cabeceras y HTTPS.** Todas las respuestas llevan cabeceras de seguridad, y en producción el
  sitio redirige a HTTPS y le pide al navegador que lo recuerde.
- **Archivos del proyecto.** El `.htaccess` de la raíz manda todo a `public/` y bloquea los
  archivos ocultos (`.env`, `.git`).

### Pendientes de seguridad

- **Roles dentro del personal** (hoy todos son administradores) y alta de profes desde el panel.
- **Segunda barrera en el servidor**: apuntar el dominio a la carpeta `public/` en vez de
  depender de la regla del `.htaccess`.
- **Cifrar las observaciones de salud** en la base.
- **Revisar dependencias** con `composer audit` y `npm audit` antes de cada despliegue.

## Referencia técnica

**Stack.** PHP 8.3+, Laravel 13, Filament 5 (panel), Livewire 4 y Alpine (portal), Tailwind 4 y
Vite. SQLite por defecto. Interfaz en español y hora de `America/Argentina/Buenos_Aires`.

**Cuentas.** El personal son los `users` (guard `web`); entran al panel los que tienen el email
verificado. Los socios son los `clients` (guard `client`) e inician sesión ellos mismos: no hay
una cuenta de usuario aparte por cliente.

**Modelo de datos.**

- `clients`: datos del socio, estado, vencimiento derivado y contraseña del portal; baja lógica.
- `plans`: catálogo con precio y créditos; baja lógica.
- `memberships`: contratos con un plan, fechas, estado de acceso y créditos restantes.
- `payments`: cobros de una membresía, con el usuario que los registró.
- `attendances`: ingresos al gimnasio, con el cliente, la membresía usada y la fecha y hora.
- `announcements`: anuncios, y `announcement_client`: qué socio cerró cuál.
- `users`: cuentas del personal.

La base impide borrar clientes, planes o membresías que tengan historial asociado.

**Estructura.**

- `app/Filament`: recursos, páginas y widgets del panel.
- `app/Livewire` y `resources/views/livewire`: pantallas del portal del socio.
- `app/Http`: ingreso y salida, y los middlewares de seguridad.
- `app/Models` y `app/Enums`: reglas del negocio, relaciones y estados.
- `app/Console/Commands`: tareas automáticas y `demo:remove`.
- `config/gym.php`: código del QR y datos de ejemplo.
- `routes/web.php` y `routes/console.php`: rutas y programación de tareas.
- `database/seeders`: datos iniciales y de ejemplo.

## Fuera del alcance actual

No hay pasarela de pago en línea, reserva de turnos o clases, registro de salida del gimnasio, ni
gestión desde el panel de la portada, los horarios, la comunidad y las opiniones del sitio.
