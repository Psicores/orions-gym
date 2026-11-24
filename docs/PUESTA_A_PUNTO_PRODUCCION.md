# Puesta a punto para producción (Hostinger)

Guía paso a paso para dejar un gimnasio funcionando en Hostinger con este stack, y checklist para
cada despliegue. Complementa la sección "Despliegue en Hostinger" del README.

**Cómo despliega este proyecto.** Cada push a `master` dispara el Git de hPanel, que hace el
`git pull` en el servidor en aproximadamente un minuto. **El servidor no compila assets** (`public/build`
va commiteado) **ni corre migraciones solo**: después de un push con migraciones hay que correrlas
a mano por SSH.

---

## 0. Antes de empezar

- [ ] Plan con **SSH** y **MySQL** (Business, Cloud o superior). El plan más básico no alcanza.
- [ ] **PHP 8.3 o superior** para el dominio (hPanel → Avanzado → Configuración de PHP).
- [ ] Extensiones de PHP activas: `pdo_mysql`, `mbstring`, `openssl`, `intl` (formato de moneda),
      `gd` **con soporte WebP** (imágenes), `fileinfo`, `exif` (orientación de fotos), `zip`,
      `bcmath`, `ctype`, `tokenizer`, `xml`.
      Verificar por SSH: `php -m` y `php -r "var_dump(gd_info()['WebP Support'] ?? false);"`.
- [ ] Opciones de PHP: `memory_limit` 256M o más (procesamiento de fotos del celular),
      `upload_max_filesize` y `post_max_size` 12M o más (se aceptan originales de hasta 10 MB),
      `max_execution_time` 60.
- [ ] **OPcache** activado (hPanel → Configuración de PHP).
- [ ] Copia de seguridad del `.env` y de la base si el sitio ya existía.

## 1. Dominio, SSL y carpeta pública

- [ ] Dominio apuntado y **SSL activo** en hPanel (el sitio fuerza HTTPS).
- [ ] Lo ideal es que la raíz web sea `public/`. En hosting compartido no siempre se puede: en ese
      caso el `.htaccess` de la raíz del proyecto manda todo a `public/` y bloquea los archivos
      ocultos. **Verificar** que `https://dominio/.env` y `https://dominio/.git/config` devuelven
      403 o 404.
- [ ] `APP_URL` con el dominio definitivo y `https://`. **El QR de ingreso lleva esta dirección:**
      si cambia el dominio, hay que reimprimir los QR.

## 2. Base de datos

- [ ] Crear base y usuario MySQL en hPanel (Bases de datos → MySQL).
- [ ] Cargar `DB_CONNECTION=mysql`, `DB_HOST` (normalmente `localhost`), `DB_PORT=3306`,
      `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` en el `.env`.

## 3. Código y dependencias

- [ ] Conectar el repositorio en hPanel → Git (rama `master`, auto-deploy activado).
- [ ] Por SSH, en la carpeta del proyecto (donde está `artisan`):

```bash
composer install --no-dev --optimize-autoloader
```

Si `composer` no existe como comando, probar `composer2` o `php composer.phar`.

## 4. Archivo `.env` de producción

Partir de `.env.example` y dejar:

```dotenv
APP_NAME="Nombre del gimnasio"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dominio-definitivo.com
APP_KEY=                      # se genera en el paso 5

LOG_CHANNEL=stack
LOG_STACK=daily               # rota el log por día (evita un laravel.log gigante)
LOG_LEVEL=warning

DB_CONNECTION=mysql
# DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=sync         # el sistema no usa colas: sync evita trabajos que nunca se procesan
MAIL_MAILER=log               # el sistema no manda emails todavía

ADMIN_NAME="Nombre del dueño"
ADMIN_EMAIL=dueno@dominio.com
ADMIN_PASSWORD=una-clave-larga-y-unica

STAFF_TWO_FACTOR_REQUIRED=false   # true para obligar la verificación en dos pasos al personal
SEED_DEMO_DATA=false              # NUNCA true en producción (los DNI de ejemplo son públicos)

KIOSK_MEMBER_DISCOUNT_PERCENT=15  # descuento de socio al día en el kiosco
KIOSK_STOCK_MODE=warn             # warn = vende y avisa; strict = no deja vender sin stock
```

- [ ] `GYM_CHECKIN_TOKEN` solo hace falta si se migra desde una versión con QR viejo; en un
      gimnasio nuevo se deja vacío.
- [ ] Permisos: `chmod 600 .env`.

## 5. Primera instalación

```bash
php artisan key:generate --force        # SOLO la primera vez
php artisan migrate --force
php artisan db:seed --force             # administrador del .env y planes base
php artisan storage:link                # imágenes públicas (anuncios, productos, sitio)
```

- [ ] **Guardar una copia de `APP_KEY` fuera del servidor** (gestor de contraseñas). Si se pierde
      o cambia, las observaciones de salud cifradas no se recuperan y se cierran todas las sesiones.
- [ ] Si `storage:link` falla (Hostinger a veces deshabilita `symlink()`), crear el enlace por SSH:
      `ln -s ../storage/app/public public/storage`, o desde el Administrador de archivos de hPanel.
      Verificar subiendo una imagen de un anuncio y abriéndola en el navegador.
- [ ] Permisos de escritura: `chmod -R 775 storage bootstrap/cache`.
- [ ] **No** correr `DemoKioskSeeder` ni nada con `SEED_DEMO_DATA=true`.

## 6. Tareas automáticas (cron)

El sistema necesita el programador de Laravel para el cierre de ciclos (01:00) y la recarga de
créditos (01:05). hPanel → Avanzado → **Tareas cron** → Personalizado:

```bash
* * * * * /usr/bin/php /home/uXXXXXXXX/domains/dominio.com/public_html/artisan schedule:run >> /dev/null 2>&1
```

- [ ] Reemplazar usuario y dominio. Si el plan no permite cada minuto, usar `*/5 * * * *` (las
      tareas son diarias y no se pisan).
- [ ] Verificar: `php artisan schedule:list`. Al día siguiente, revisar en el panel que las
      membresías vencidas pasaron a En pausa.
- [ ] No hace falta worker de colas (`QUEUE_CONNECTION=sync`). Si en el futuro un módulo usa colas
      (por ejemplo, envíos de WhatsApp), agregar un cron con
      `php artisan queue:work --stop-when-empty --max-time=50`, ya que en Hostinger compartido no
      hay procesos permanentes.

## 7. Optimización

Después de cada despliegue (y después de tocar el `.env`):

```bash
php artisan optimize:clear
php artisan optimize          # config, rutas, eventos y vistas en caché
php artisan filament:optimize # componentes e íconos de Filament en caché
```

- [ ] `composer install --no-dev --optimize-autoloader` (sin paquetes de desarrollo).
- [ ] Assets: vienen compilados en `public/build`; **no** correr `npm` en el servidor. Antes de
      pushear, en la PC: `npm run build` y commitear `public/build`.
- [ ] Imágenes: ya se guardan en WebP livianas; no hace falta optimizarlas aparte.

## 8. Seguridad

- [ ] `APP_DEBUG=false` (con `true` cualquier error muestra contraseñas y datos internos).
- [ ] `.env`, `.git` y `storage/logs` no accesibles desde el navegador (ver paso 1).
- [ ] Contraseña del administrador larga y única; activar la verificación en dos pasos desde el
      Perfil (o `STAFF_TWO_FACTOR_REQUIRED=true`).
- [ ] `composer audit` antes de desplegar; sin vulnerabilidades altas.
- [ ] Backups: activar los automáticos de hPanel **y** descargar un dump propio periódicamente
      (`mysqldump`), junto con `storage/app` (fotos y certificados) y la copia de `APP_KEY`.
- [ ] Revisar avisos de CSP en el log tras unas semanas: `grep "política de contenido" storage/logs/*.log`.

## 9. Arranque limpio con datos reales

- [ ] `SEED_DEMO_DATA=false` y `php artisan demo:remove` (borra clientes y anuncios de ejemplo).
- [ ] Verificar que no quedan productos `DEMO-…` en Kiosco → Productos.
- [ ] Crear las cuentas del personal (Personal) con su rol.
- [ ] Cargar planes reales, socios, productos y stock inicial (conteo del día anterior).
- [ ] Crear los puntos de acceso e **imprimir los QR con el dominio definitivo**.
- [ ] Completar las secciones del sitio (Admin del sitio).

## 10. Verificación después de cada despliegue

```bash
php artisan migrate:status          # nada pendiente
php artisan about --only=environment # production, debug apagado, caches activas
tail -n 50 storage/logs/laravel.log  # sin errores nuevos
```

- [ ] `https://dominio/` carga la página pública con planes y novedades.
- [ ] `http://dominio/` redirige a HTTPS.
- [ ] `/ingresar` → entra un administrador al panel; entra un socio al portal (`/mi-perfil`).
- [ ] Escaneo de un QR de ingreso con el celular → registra el ingreso.
- [ ] Subir una imagen (anuncio o producto) → se ve en el sitio o el panel.
- [ ] Kiosco: venta de prueba y anulación (si es un gimnasio en marcha, anular la venta de prueba).
- [ ] Ingresos de hoy muestran lo cobrado.

## 11. Volver atrás si algo sale mal

- Código: `git revert` del merge en la PC y push (hPanel vuelve a desplegar).
- Base: las migraciones nuevas se revierten con `php artisan migrate:rollback --step=N` **solo si no
  cargaron datos reales**; si no, restaurar el backup tomado antes del despliegue.
- Modo mantenimiento mientras se arregla: `php artisan down` / `php artisan up`.

## Checklist corto por despliegue

1. En la PC: tests en verde, `npm run build` si hubo cambios de estilos, merge a `master`.
2. Backup de la base si hay migraciones.
3. Push (despliega solo).
4. Por SSH: `composer install --no-dev --optimize-autoloader` (si cambió `composer.lock`),
   `php artisan migrate --force` (si hay migraciones), `php artisan optimize:clear && php artisan optimize && php artisan filament:optimize`.
5. Verificación del paso 10.
