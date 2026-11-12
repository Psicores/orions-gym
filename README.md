# Orions Gym

Sistema interno para administrar clientes, planes, membresías y cobros de un gimnasio. La
operación se realiza desde un panel privado; los clientes no tienen un portal de acceso propio.

## Stack y acceso

- PHP 8.3 o superior y Laravel 13.
- Filament 5 para el panel administrativo.
- SQLite como base de datos configurada por defecto en `.env.example`.
- Interfaz en español y fechas calculadas con la zona horaria `America/Argentina/Buenos_Aires`.
- Panel en `/admin`. La ruta `/` sigue mostrando la página de bienvenida de Laravel.

El personal inicia sesión con usuarios de `users`. Solo pueden entrar al panel `admin` los
usuarios con el email verificado. Actualmente no hay roles ni permisos diferenciados: cualquier
usuario verificado habilitado para el panel accede a las mismas pantallas.

## Puesta en marcha

1. Instalar PHP 8.3+, Composer, Node.js/npm y las extensiones PHP requeridas por Laravel.
2. Desde la raíz del proyecto:

   ```bash
   composer setup
   ```

   El script instala dependencias, crea `.env` si todavía no existe, genera la clave, ejecuta las
   migraciones e instala y compila los recursos frontend.
3. Completar en `.env` `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` con credenciales propias.
   Son necesarias para crear el administrador inicial.
4. Iniciar el entorno local:

   ```bash
   composer run dev
   ```

5. Abrir `http://localhost:8000/admin` e iniciar sesión con el administrador configurado.

Para correr las pruebas:

```bash
php artisan test
```

### Datos iniciales y de ejemplo

`php artisan db:seed` ejecuta `DatabaseSeeder`: crea o actualiza el administrador indicado en
`.env`, crea una cuenta de prueba compartida y carga los planes base. Fuera de producción también
intenta cargar clientes de ejemplo. El seeder es repetible: no duplica los usuarios, planes ni
clientes que ya existen.

**Limitación actual:** `DemoClientSeeder` usa un plan llamado `4 días`, pero `PlanSeeder` crea
`2 días`, `3 días` y `Pase libre`. Por esa diferencia, el seeder de ejemplo puede fallar en un
entorno no productivo. La cuenta de prueba también se crea con una contraseña fija en el código,
incluso en producción; no usarla para operar una instalación desplegada y revisar ese seeder antes
de publicar el sistema.

Los planes base solo se crean si todavía no existe un plan con ese nombre. Volver a ejecutar el
seeder no sobrescribe precios o créditos modificados desde el panel.

## Uso del sistema

### Inicio

El dashboard muestra tres contadores:

- **Entrenando:** clientes cuyo estado es “Entrenando”.
- **Deben la cuota:** clientes cuyo estado es “En pausa”.
- **Vencen en 7 días:** clientes “Entrenando” o “Activo temporal” con vencimiento entre hoy y los
  próximos siete días, inclusive.

Debajo aparece **Para cobrar**, con clientes “Entrenando”, “En pausa” o “Activo temporal” cuyo
vencimiento ya pasó o cae dentro de los próximos siete días. No muestra clientes inactivos ni
clientes sin fecha de vencimiento. Desde cada fila se puede abrir la ficha o iniciar un cobro.

### Clientes

En **Clientes** se pueden crear, buscar, ver y editar socios. El listado permite buscar por
nombre, apellido o DNI, filtrar por estado con pestañas y consultar los registros dados de baja.
DNI y email deben ser únicos, incluso respecto de clientes dados de baja.

La ficha reúne los datos personales y de contacto, el estado, el vencimiento calculado, la
membresía vigente, los créditos disponibles, el último pago y la información de salud. Incluye
pestañas para administrar las membresías y consultar el historial de pagos. La foto y el
certificado de salud son opcionales; se guardan en almacenamiento privado y se muestran mediante
enlaces temporales.

Dar de baja a un cliente es una baja lógica: se conserva su historial. La base restringe el
borrado definitivo cuando hay membresías o pagos relacionados.

### Planes

En **Planes** se administra el catálogo: nombre, precio en pesos y créditos otorgados. El valor
`999` se usa como convención para representar un plan libre. Los planes se pueden editar, dar de
baja y restaurar; la baja lógica los oculta del catálogo activo, pero conserva su referencia en
las membresías históricas.

### Membresías

Una membresía vincula un cliente con un plan, fechas de inicio y vencimiento, estado de acceso y
créditos disponibles. Al crearla, los créditos disponibles se copian del plan; el vencimiento del
cliente se recalcula automáticamente como el más lejano de sus membresías no vencidas. Si no
tiene membresías abiertas, se usa el vencimiento más lejano de su historial.

Los estados de cliente y de membresía representan cosas distintas:

| Estado del cliente | Significado |
| --- | --- |
| Entrenando | Cuota al día |
| En pausa | Debe abonar |
| Activo temporal | Tiene un acuerdo de pago atrasado |
| Inactivo | Sin actividad |

| Estado de la membresía | Acceso al gimnasio | Admite pagos |
| --- | --- | --- |
| Entrenando | Sí | Sí |
| En pausa | No | Sí |
| Activo flex | Sí, por excepción | Sí |
| Vencida | No | No |

El pago y el acceso no son lo mismo: por ejemplo, una membresía puede estar en pausa y permitir
que se registre un pago, pero no permite entrar al gimnasio.

### Cobros y días de gracia

**Registrar pago** permite imputar un monto a una membresía no vencida. Si hay varias, se elige
una; se sugiere el precio del plan, pero el monto se puede ajustar. Los medios disponibles son
efectivo y transferencia. El sistema guarda fecha y hora, cliente, membresía, monto, medio y
usuario del personal que cobró.

El cobro actualiza el estado del cliente a **Entrenando**, pero no extiende la membresía ni cambia
su vencimiento. Para renovar, se debe crear o editar una membresía. El historial de pagos se puede
consultar y filtrar por medio, pero no editar ni borrar desde el panel.

**Dar días de gracia** permite extender entre 1 y 60 días la membresía vigente no vencida (7 días
por defecto). La membresía pasa a **Activo flex** y el cliente a **Activo temporal**. Los días se
suman desde la fecha de vencimiento vigente, o desde hoy si esa fecha ya pasó.

### Vencimientos automáticos

El comando `memberships:check-expired` se ejecuta diariamente a las 00:05, según la zona horaria
de la aplicación. Pausa las membresías **Entrenando** o **Activo flex** cuya fecha de vencimiento
sea anterior a hoy; las que vencen hoy siguen vigentes durante ese día. También pasa el cliente a
**En pausa** si ninguna otra membresía todavía le permite entrar.

El comando puede ejecutarse manualmente:

```bash
php artisan memberships:check-expired
```

En un servidor de producción hay que mantener activo el scheduler de Laravel (por ejemplo,
invocando `php artisan schedule:run` cada minuto desde cron); definir la tarea en el proyecto no
la ejecuta por sí sola.

## Modelo de datos

- `clients`: datos del socio, estado y vencimiento derivado; usa baja lógica.
- `plans`: catálogo con precio decimal y créditos; usa baja lógica.
- `memberships`: historial de contratos, fechas, estado de acceso y créditos restantes.
- `payments`: cobros asociados al cliente y a una membresía, con el usuario que los registró.
- `users`: cuentas del personal que accede al panel.

Las relaciones se protegen contra borrados que dejarían el historial inconsistente. El precio y
los montos se almacenan con dos decimales. Los archivos de clientes se guardan en el disco
privado `local`, bajo `storage/app/private`; no se exponen como archivos públicos.

Los créditos se inicializan y muestran en la membresía, pero el sistema actual no registra
asistencias ni descuenta créditos automáticamente.

## Estructura principal

- `app/Filament/Resources/Clients`: pantallas y acciones de clientes, membresías y pagos.
- `app/Filament/Resources/Plans`: administración del catálogo de planes.
- `app/Filament/Widgets`: widgets del dashboard.
- `app/Models` y `app/Enums`: reglas del dominio, relaciones y estados.
- `app/Console/Commands`: tareas de mantenimiento, incluido el chequeo de vencimientos.
- `database/migrations`: esquema y evoluciones de la base de datos.
- `database/seeders`: datos iniciales y datos de ejemplo.
- `routes/console.php`: programación de tareas automáticas.

## Alcance actual

El sistema cubre la administración interna de socios, planes, membresías, cobros y vencimientos.
No incluye portal para clientes, pasarela de pago, registro de asistencia ni descuento automático
de créditos por clase.
