# Manual de uso de Orions

Esta guía explica, paso a paso, todo lo que se puede hacer con el sistema del gimnasio: qué hace cada pantalla, qué pasa cuando tocás cada botón y cómo resolver las situaciones de todos los días en recepción.

Si buscás algo puntual, usá el índice o la búsqueda del navegador (`Ctrl + F`).

[TOC]

## Cómo está organizado el sistema

Orions tiene tres partes. Todas comparten la misma pantalla de ingreso.

| Parte | Dirección | Quién la usa | Para qué |
| --- | --- | --- | --- |
| Sitio público | `/` | Cualquier persona | Ver la presentación del gimnasio, los planes y las novedades |
| Portal del socio | `/mi-perfil` | Los clientes | Ver su membresía, escanear el QR de ingreso y consultar su historial |
| Panel de administración | `/admin` | El personal | Cargar clientes, cobrar, administrar planes y anuncios |

El menú del panel tiene estas secciones:

| Sección | Qué encontrás |
| --- | --- |
| **Escritorio** | El resumen del día: cuántos entrenan, cuántos deben y a quién hay que cobrarle |
| **Clientes** | El listado de socios y la ficha de cada uno, con sus membresías y pagos |
| **QR de ingreso** | El cartel con el código que los socios escanean al llegar |
| **Manual de uso** | Esta guía (solo la ve la cuenta del administrador) |
| **Admin del sitio → Planes** | El catálogo de planes y sus precios |
| **Admin del sitio → Anuncios** | Las novedades que ven los socios y el público |
| **Admin del sitio → Portada, Horarios y ubicación, Comunidad, Opiniones** | Lo que muestra cada parte de la página pública |

## Conceptos que conviene tener claros

### Cliente, plan, membresía y pago

- **Cliente:** la persona. Tiene sus datos, su foto, su certificado de salud y un estado.
- **Plan:** lo que el gimnasio ofrece (por ejemplo "3 días"). Tiene un precio y una cantidad de créditos.
- **Membresía:** el contrato de un cliente con un plan. Tiene fecha de inicio, fecha de vencimiento, un estado y los créditos que le quedan.
- **Pago:** un cobro registrado. Siempre corresponde a una membresía.

### Estados del cliente

El estado del cliente cuenta **cómo está su cuota**.

| Estado | Qué significa | Color |
| --- | --- | --- |
| **Entrenando** | Tiene la cuota al día | Verde |
| **En pausa** | Terminó su mes y le toca renovar | Naranja |
| **Activo temporal** | Entra por cortesía hasta que renueve | Celeste |
| **Inactivo** | Sin actividad. Es el estado con el que nace todo cliente nuevo | Gris |

### Estados de la membresía

El estado de la membresía decide **si el socio puede entrar** cuando escanea el QR.

| Estado | ¿Puede entrar? | ¿Se le puede cobrar? | Qué significa |
| --- | --- | --- | --- |
| **Entrenando** | Sí | Sí | Membresía vigente |
| **En pausa** | No | Sí | Terminó su ciclo: entra cuando renueva |
| **Activo flex** | Sí | Sí | Entra por cortesía del dueño |
| **Vencida** | No | No | Membresía cerrada. Si el socio vuelve, se le crea una nueva |

### Créditos

Cada plan otorga una cantidad de créditos por mes. Un crédito equivale a un ingreso al gimnasio.

- Cada vez que el socio escanea el QR se le descuenta **un crédito**.
- Si se queda sin créditos **puede entrar igual**: el saldo pasa a negativo y queda a la vista en su membresía.
- Un plan con **999 créditos** es un **pase libre**: en la ficha se muestra "Libre".
- Al renovar, los créditos vuelven a ser los del plan. No se suman los que sobraron ni se descuentan los que quedó debiendo.

### Fecha de vencimiento del cliente

No se carga a mano: el sistema la calcula sola. Es el vencimiento más lejano de las membresías abiertas del cliente (todas menos las Vencidas). Cambia cuando creás, editás, renovás o borrás una membresía.

Cuando el vencimiento está cerca o ya pasó, debajo de la fecha aparece una aclaración: "Vence en 3 días", "Vence hoy", "Venció hace 5 días". Las fechas ya vencidas se pintan en naranja.

## Entrar al panel y manejar tu cuenta

### Ingresar

1. Abrí `/ingresar`.
2. Escribí tu **email** y tu **contraseña**.
3. Si tenés activada la verificación en dos pasos, escribí el código de 6 dígitos de tu app de autenticación (o un código de recuperación).
4. El sistema te lleva directo al panel.

> Después de 5 intentos fallidos seguidos, el ingreso se bloquea durante un minuto.

### Buscar un cliente desde cualquier pantalla

1. Hacé clic en la **barra de búsqueda** de arriba.
2. Escribí el nombre, el apellido o el DNI.
3. Elegí el resultado: cada uno muestra el DNI, el estado y el vencimiento.
4. Se abre la ficha del cliente.

### Cambiar tu nombre, email o contraseña

1. Abrí el **menú de usuario** (el círculo con tu inicial).
2. Elegí **Perfil**.
3. Cambiá lo que necesites y guardá.

### Activar la verificación en dos pasos

Es opcional: la activás si querés, desde tu perfil. Agrega una segunda barrera: además de la contraseña, el panel pide un código que cambia cada 30 segundos.

1. Instalá en el celular una app de autenticación (Google Authenticator, Authy o similar).
2. En el panel, abrí el **menú de usuario → Perfil**.
3. En la sección de verificación en dos pasos, iniciá la configuración.
4. Escaneá con la app el código QR que aparece.
5. Escribí el código de 6 dígitos que te muestra la app para confirmar.
6. **Guardá los códigos de recuperación** en un lugar seguro: sirven para entrar si perdés el celular.

### Cambiar entre modo claro y oscuro

1. Abrí el **menú de usuario**.
2. En **Tema**, elegí el sol (modo claro), la luna (modo oscuro) o la pantalla (el modo que use tu dispositivo).

### Salir

1. Abrí el **menú de usuario**.
2. Elegí **Salir**.

## Escritorio

Es la primera pantalla al entrar. Sirve para saber de un vistazo cómo viene el día.

### Contadores

| Contador | Qué cuenta | Al hacer clic |
| --- | --- | --- |
| **Entrenando** | Clientes con la cuota al día | Abre el listado filtrado por Entrenando |
| **Deben la cuota** | Clientes En pausa | Abre el listado filtrado por En pausa |
| **Vencen en 7 días** | Clientes Entrenando o Activo temporal que vencen entre hoy y los próximos 7 días | No abre nada: son los de la lista de abajo |

Se actualizan solos cada minuto y cada vez que registrás un pago.

### Lista "Para cobrar"

Muestra a los clientes Entrenando, En pausa o Activo temporal cuyo vencimiento **ya pasó o cae en los próximos 7 días**, ordenados del más urgente al menos urgente.

- **Buscar:** por nombre, apellido o DNI.
- **Clic en la fila:** abre la ficha del cliente.
- **Cobrar:** abre el cobro sin salir del Escritorio.

Los clientes Inactivos y los que no tienen vencimiento no aparecen.

## Clientes

### Dar de alta un cliente nuevo (con su membresía y su primer pago)

Es el caso más común en recepción. Se hace en dos pasos seguidos.

**Paso 1: los datos**

1. Entrá a **Clientes**.
2. Hacé clic en **Crear cliente**.
3. En la pestaña **Datos del cliente**, completá nombre, apellido, DNI, fecha de nacimiento, celular y email. Todos son obligatorios.
4. Dejá el **Estado** como viene (Inactivo): cambia solo cuando registres el pago.
5. Si corresponde, anotá lesiones o dolencias en **Observaciones / dolencias** (opcional).
6. Si ya tenés la foto o el certificado, cargalos en la pestaña **Foto y certificado** (opcional: se pueden subir después).
7. Hacé clic en **Crear**.

**Paso 2: la membresía y el cobro**

Apenas guardás, se abre sola la ventana **Membresía de…**

1. Elegí el **Plan**. El monto se completa solo con el precio del plan.
2. Revisá las fechas: vienen cargadas de hoy a dentro de un mes.
3. Elegí el **Medio de pago**: Efectivo o Transferencia. Si el plan tiene precio en efectivo, el monto se ajusta al cambiar el medio.
4. Corregí el **Monto** si cobraste otro importe.
5. Hacé clic en **Crear membresía**.

Resultado: la membresía queda **Entrenando** con los créditos del plan, el pago queda registrado a tu nombre, el cliente pasa a **Entrenando** y se abre su ficha.

> Si todavía no va a pagar, tocá **Ahora no**. El cliente queda creado, sin membresía y sin pago. Después podés crearle la membresía desde su ficha.

**A tener en cuenta**

- El DNI y el email no se pueden repetir, ni siquiera con un cliente dado de baja.
- La foto acepta JPG, PNG o WebP. El certificado acepta PDF, JPG, PNG o WebP. Cada archivo puede pesar hasta 2 MB.
- No hace falta crearle un usuario al socio: desde este momento puede entrar al portal con su DNI (ver [Portal del socio](#portal-del-socio)).

### Buscar y filtrar clientes

1. Entrá a **Clientes**.
2. Usá las **pestañas** para filtrar por estado: Todos, Entrenando, En pausa, Activo temporal, Inactivo. Cada una muestra cuántos hay.
3. Escribí en el **buscador** el nombre, el apellido o el DNI. Podés combinar, por ejemplo "juan perez".
4. Hacé clic en el encabezado de una columna para ordenar por nombre, apellido o vencimiento.

### Ver la ficha de un cliente

Hacé clic en la fila del cliente. La ficha es la pantalla de trabajo de recepción y tiene tres pestañas.

| Pestaña | Qué muestra |
| --- | --- |
| **Ficha** | Foto, nombre, DNI, estado y vencimiento; la membresía vigente con sus créditos; el último pago; contacto, certificado y observaciones de salud |
| **Membresías** | Todas las membresías del cliente, la más nueva primero |
| **Pagos** | El historial de pagos, con el total al pie y un filtro por medio de pago |

Arriba a la derecha están los botones de la ficha:

| Botón | Qué hace | Cuándo aparece |
| --- | --- | --- |
| **Registrar pago** | Abre el cobro | Siempre. Queda deshabilitado si no hay una membresía a la que cobrarle |
| **Dar cortesía** | Deja entrar al socio aunque su fecha haya terminado | Solo si la membresía vigente está Entrenando o En pausa |
| **Restablecer contraseña** | Devuelve la contraseña del portal al DNI | Solo si el socio ya eligió una contraseña propia |
| **Editar** | Abre el formulario de datos | Siempre |

Desde la ficha también podés tocar el **celular** para llamar, el **email** para escribirle y **Ver certificado** para abrir el certificado de salud.

### Modificar los datos de un cliente

1. Abrí la ficha del cliente y hacé clic en **Editar** (o tocá **Editar** en su fila del listado).
2. Cambiá lo que necesites en cualquiera de las dos pestañas.
3. Hacé clic en **Guardar cambios**. Volvés a la ficha.

La **Fecha de vencimiento** aparece en gris porque no se edita: la calcula el sistema a partir de las membresías.

### Cargar o cambiar la foto y el certificado de salud

1. Abrí la ficha y hacé clic en **Editar**.
2. Pasá a la pestaña **Foto y certificado**.
3. Arrastrá el archivo o hacé clic para elegirlo. Para reemplazar uno, quitá el anterior con la cruz y subí el nuevo.
4. Hacé clic en **Guardar cambios**.

Estos archivos se guardan en un espacio privado: solo se ven desde el panel, con enlaces que dejan de funcionar a los 30 minutos.

### Cambiar el estado de un cliente a mano

Normalmente no hace falta: el estado cambia solo al cobrar, al dar cortesía y cuando termina el mes. Usalo para casos puntuales, por ejemplo marcar como **Inactivo** a alguien que avisó que deja de venir.

1. Abrí la ficha y hacé clic en **Editar**.
2. Elegí el nuevo valor en **Estado**.
3. Hacé clic en **Guardar cambios**.

> Cambiar el estado del cliente **no** cambia el estado de su membresía. Si querés que deje de poder entrar, editá también la membresía (ver [Modificar una membresía](#modificar-una-membresía)).

### Dar de baja un cliente

Es una baja lógica: el cliente desaparece del listado y **ya no puede entrar al portal**, pero se conservan sus membresías, pagos y asistencias.

1. Abrí la ficha y hacé clic en **Editar**.
2. Hacé clic en **Borrar** y confirmá.

Para dar de baja a varios juntos: en el listado, tildá las casillas de cada fila, abrí las acciones masivas y elegí **Borrar seleccionados**.

### Restaurar un cliente dado de baja

1. Entrá a **Clientes** y abrí el **filtro** (el ícono del embudo).
2. Elegí ver solo los registros eliminados.
3. Abrí el cliente, hacé clic en **Editar** y después en **Restaurar**.

Vuelve al listado con todo su historial y puede entrar otra vez al portal.

### Restablecer la contraseña del portal de un socio

Para cuando el socio se olvidó su contraseña.

1. Abrí la ficha del socio.
2. Hacé clic en **Restablecer contraseña** y confirmá.
3. Avisale que entre con su **DNI como usuario y como contraseña**. El portal le va a pedir que elija una nueva.

La contraseña anterior deja de servir y se cierran las sesiones que tenía abiertas en otros dispositivos. Nadie del personal ve ni elige la contraseña nueva.

> Si el botón no aparece, es porque el socio nunca cambió su contraseña: sigue entrando con el DNI.

## Membresías

Se administran desde la ficha del cliente, pestaña **Membresías**.

### Crear una membresía a un cliente que ya existe

Para quien se dio de alta sin membresía, o para quien vuelve después de tener la anterior Vencida.

1. Abrí la ficha del cliente y pasá a la pestaña **Membresías**.
2. Hacé clic en **Crear membresía**.
3. Elegí el **Plan**. Solo se ofrecen los planes activos.
4. Revisá la **Fecha de inicio** y la **Fecha de vencimiento** (vienen de hoy a dentro de un mes).
5. Dejá el **Estado** en Entrenando, salvo que quieras otra cosa.
6. Hacé clic en **Crear**.

La membresía toma los créditos del plan y el vencimiento del cliente se recalcula.

> Esta ventana **no registra el pago ni cambia el estado del cliente**. Para dejarlo al día, tocá **Cobrar** en la fila de la membresía recién creada.

### Modificar una membresía

Sirve para corregir una fecha, ajustar créditos o cambiar el estado a mano.

1. En la pestaña **Membresías**, abrí el menú de los **tres puntos** de la fila.
2. Elegí **Editar**.
3. Cambiá el plan, las fechas, el estado o los **Créditos disponibles**.
4. Hacé clic en **Guardar cambios**.

| Quiero… | Qué cambiar |
| --- | --- |
| Regalar o devolver ingresos | Subí **Créditos disponibles** |
| Extender el mes unos días | Corré la **Fecha de vencimiento** |
| Que deje de poder entrar ya | Pasá el **Estado** a En pausa |
| Cerrar la membresía de alguien que se fue | Pasá el **Estado** a Vencida |

Cambiar el plan de una membresía existente **no** vuelve a cargar los créditos: ajustalos a mano si hace falta.

### Cambiar a un socio de plan

Hay dos formas. Elegí según el caso.

- **En la renovación (recomendado):** editá la membresía, elegí el plan nuevo y guardá. Cuando cobres la renovación, el mes nuevo arranca con los créditos del plan nuevo.
- **A mitad de mes:** editá la membresía, elegí el plan nuevo y corregí a mano los **Créditos disponibles**.

### Borrar una membresía

Solo para membresías cargadas por error.

1. En la pestaña **Membresías**, abrí el menú de los **tres puntos** de la fila.
2. Elegí **Borrar** y confirmá.

Una membresía que **ya tiene pagos no se puede borrar** (la opción no aparece), para no perder el historial. En ese caso, pasala a **Vencida**.

## Cobros

### Registrar un pago

Podés cobrar desde cuatro lugares; la ventana es la misma.

| Desde | Botón |
| --- | --- |
| Escritorio, lista "Para cobrar" | **Cobrar** en la fila |
| Listado de clientes | **Cobrar** en la fila |
| Ficha del cliente | **Registrar pago** |
| Ficha → pestaña Membresías | **Cobrar** en la fila de la membresía |

1. Hacé clic en **Cobrar** o **Registrar pago**.
2. Revisá la **Membresía**. Si el cliente tiene una sola, se muestra como dato. Si tiene varias, elegí a cuál corresponde el pago.
3. Elegí el **Medio de pago**: Efectivo o Transferencia.
4. Revisá el **Monto**. Viene sugerido y lo podés cambiar.
5. Hacé clic en **Registrar**.

Queda guardado cuánto se cobró, con qué medio, la fecha y hora, y **quién lo cobró** (la cuenta con la que estás en el panel).

### Cómo se sugiere el monto

| Medio de pago | Monto sugerido |
| --- | --- |
| Efectivo | El **precio en efectivo** del plan. Si el plan no lo tiene cargado, el precio normal |
| Transferencia | El **precio** normal del plan |

Al cambiar el medio de pago, el monto se vuelve a calcular. Si habías escrito un monto a mano, revisalo después de cambiar el medio.

### Qué pasa cuando registrás un pago

El cliente pasa siempre a **Entrenando**. Lo que pasa con la membresía depende de cómo estaba.

| Situación de la membresía al cobrar | Qué hace el pago |
| --- | --- |
| **Terminó su ciclo:** está En pausa, en Activo flex o su fecha ya pasó | **Inicia el mes nuevo:** queda Entrenando, con un mes de vigencia desde hoy y los créditos completos del plan |
| **Sigue en su ciclo:** está Entrenando y todavía no venció | Solo registra el pago. **No cambia fechas ni créditos** |

### Cobrar por adelantado

Si el socio paga **antes de que termine su mes** (el día del vencimiento inclusive), el pago queda registrado pero la membresía no se renueva sola. Cuando su fecha pase, va a quedar En pausa aunque haya pagado.

Para evitarlo, después de cobrar:

1. Abrí la ficha del cliente, pestaña **Membresías**.
2. Abrí los **tres puntos → Editar**.
3. Corré la **Fecha de vencimiento** un mes y, si corresponde, cargá los **Créditos disponibles** del mes nuevo.
4. Hacé clic en **Guardar cambios**.

### Consultar los pagos de un cliente

1. Abrí la ficha del cliente.
2. Pasá a la pestaña **Pagos**.
3. Para ver solo efectivo o solo transferencias, usá el **filtro** de medio de pago. El **Total** al pie suma lo que estás viendo.

### Si un pago se cargó mal

Los pagos **no se pueden editar ni borrar** desde el panel: es intencional, para que el historial de cobros sea confiable. Si te equivocaste de monto o de cliente, anotalo y avisale al administrador del sistema para que lo corrija.

## Cortesía

### Dar cortesía a un socio

Usala cuando el socio todavía no renovó pero querés dejarlo entrar igual.

1. Abrí la ficha del cliente.
2. Hacé clic en **Dar cortesía**. También está en la pestaña **Membresías → tres puntos → Dar cortesía**.
3. Leé la confirmación y hacé clic en **Dar cortesía**.

Resultado: la membresía pasa a **Activo flex** y el cliente a **Activo temporal**. Puede escanear el QR y entrar.

- No cambia la fecha de vencimiento ni los créditos.
- **No termina sola.** Dura hasta que el socio paga (ahí arranca su mes nuevo) o hasta que le cambies el estado a mano.
- El socio sigue apareciendo en "Para cobrar", para que no te olvides.

### Quitar una cortesía

1. En la pestaña **Membresías**, abrí los **tres puntos → Editar**.
2. Pasá el **Estado** a En pausa y guardá.
3. Si querés, editá también el cliente y pasá su estado a En pausa.

## QR de ingreso

### Imprimir el cartel de recepción

1. Entrá a **QR de ingreso**.
2. Hacé clic en **Imprimir**. Sale solo el cartel, sin el menú ni los botones.
3. Pegalo en el mostrador, a la altura de la vista.

El código es **el mismo para todos los socios** y no cambia: se imprime una sola vez.

### Qué pasa cuando un socio lo escanea

El socio lo escanea desde su portal, con la opción **Escanear QR**.

| Situación del socio | Resultado en su celular |
| --- | --- |
| Membresía Entrenando o Activo flex | Pantalla verde y sonido. Se registra el ingreso y se descuenta un crédito |
| Volvió a escanear dentro de los 10 minutos | Avisa que ya estaba registrado. No descuenta otro crédito |
| Membresía En pausa | "Cuota vencida. Pasá por recepción." No registra el ingreso |
| Sin membresía vigente | Avisa el motivo. No registra el ingreso |
| Escaneó otro código QR | Error. No registra nada |

### Invalidar el código

Si sospechás que alguien registra ingresos desde su casa con una foto del cartel, hay que cambiar el código. Es una tarea del administrador del sistema (ver [Mantenimiento](#mantenimiento)). Después hay que imprimir el cartel de nuevo.

## Planes

Están en **Admin del sitio → Planes**. Los planes activos son los que se ofrecen al crear una membresía y los que se muestran en el sitio público.

### Crear un plan

1. Entrá a **Planes** y hacé clic en **Crear plan**.
2. Escribí el **Nombre**, por ejemplo "3 veces por semana".
3. Cargá el **Precio**.
4. Si hacés descuento por pago en efectivo, cargá el **Precio en efectivo**. Si lo dejás vacío, en efectivo se cobra el precio normal.
5. Cargá los **Créditos**: cuántos ingresos por mes habilita. Usá **999** para un pase libre.
6. Hacé clic en **Crear**.

### Cambiar el precio u otros datos de un plan

1. En el listado, hacé clic en **Editar** en la fila del plan.
2. Cambiá lo que necesites y guardá.

El precio nuevo se sugiere en los próximos cobros. **Las membresías ya vendidas no cambian:** conservan sus fechas y sus créditos hasta la próxima renovación.

### Dar de baja un plan

1. En el listado, hacé clic en **Borrar** en la fila del plan y confirmá.

El plan deja de ofrecerse y desaparece del sitio público. Los socios que ya lo tienen lo conservan y se les sigue pudiendo cobrar.

### Restaurar un plan

1. Abrí el **filtro** del listado y elegí ver los registros eliminados.
2. Hacé clic en **Restaurar** en la fila del plan.

## Anuncios

Están en **Admin del sitio → Anuncios**. Un anuncio se muestra en dos lugares: en el inicio del portal de cada socio y en el botón **Novedades** del sitio público.

### Publicar un anuncio

1. Entrá a **Anuncios** y hacé clic en **Crear anuncio**.
2. Escribí el **Título** (obligatorio).
3. Escribí el **Contenido**, cargá una **Imagen**, o las dos cosas. La imagen acepta JPG, PNG o WebP de hasta 2 MB.
4. Dejá **Activo** encendido.
5. Hacé clic en **Crear**. Se empieza a mostrar en el momento.

### Programar un anuncio

1. Creá el anuncio como en el caso anterior.
2. En **Mostrar desde**, elegí cuándo tiene que empezar a verse. Vacío: desde ahora.
3. En **Mostrar hasta**, elegí cuándo tiene que dejar de verse. Vacío: hasta que lo apagues.
4. Hacé clic en **Crear**.

Ejemplo: para un feriado, cargá el aviso una semana antes con "Mostrar hasta" el día del feriado a la noche. Se da de baja solo.

### Apagar un anuncio sin borrarlo

1. En el listado, apagá el interruptor de la columna **Activo**. El cambio es inmediato.

Para volver a mostrarlo, encendelo.

### Saber si un anuncio se está viendo

Mirá la columna **Situación**.

| Situación | Qué significa |
| --- | --- |
| **Visible** | Se está mostrando ahora |
| **Programado** | Está encendido, pero todavía no llegó su fecha de inicio |
| **Finalizado** | Ya pasó su fecha de fin |
| **Apagado** | El interruptor Activo está apagado |

### Modificar o borrar un anuncio

- **Modificar:** hacé clic en **Editar** en la fila, cambiá lo que necesites y guardá.
- **Borrar:** hacé clic en **Borrar** y confirmá. A diferencia de clientes y planes, el borrado de un anuncio es **definitivo**.

> Cada socio puede cerrar un anuncio en su portal. A ese socio no le vuelve a aparecer; los demás lo siguen viendo.

## Secciones del sitio

En **Admin del sitio** hay una pantalla por cada parte de la página pública: **Portada**, **Horarios y ubicación**, **Comunidad** y **Opiniones**. Todas funcionan igual:

1. Cambiá lo que necesites.
2. Tocá **Guardar** (o Ctrl+S). Aparece el aviso "Cambios guardados".
3. Tocá **Ver en el sitio** para ver cómo quedó.

Lo que dejás vacío no se muestra en el sitio. Si vaciás una sección entera, desaparece de la página.

### Portada

Lo primero que se ve al entrar: el rótulo chico, el título (la segunda parte sale en verde), el texto de bienvenida y el fondo. El fondo puede ser un video, con una imagen que se ve mientras carga; si no subís video, la imagen queda de fondo. Abajo están el **título de la página** (el que aparece en la pestaña del navegador y en Google) y la **descripción** que muestra Google debajo del título.

### Horarios y ubicación

- **Horarios:** tocá **Agregar franja horaria** y escribí los días ("Lunes a viernes") y el horario ("07:00 a 22:00" o "Cerrado"). Las franjas se reordenan arrastrándolas.
- **Dirección:** tal como querés que se lea.
- **Mapa:** en Google Maps buscá el gimnasio, tocá **Compartir → Insertar un mapa → Copiar HTML** y pegá el código entero en el campo. Si pegás otra cosa, el sistema lo rechaza.

### Comunidad

Rótulo, título y texto; un video con su imagen fija y la frase que aparece encima (con una parte en verde); y hasta dos fotos. Cada foto necesita una descripción corta de lo que se ve. Con dos fotos, la primera sale más ancha.

### Opiniones

Tocá **Agregar opinión** y cargá el nombre, las estrellas (de 1 a 5) y el texto. Se muestran en el orden de la lista; arrastralas para cambiarlo.

### Videos e imágenes

- **Videos:** MP4 de hasta 12 MB. Para que la página cargue rápido, conviene comprimirlos antes (1280 px de ancho, sin sonido).
- **Imágenes:** JPG, PNG o WebP. Se achican solas antes de subir.

## Portal del socio

Esta sección cuenta lo que ve el socio, para que puedas ayudarlo cuando consulte en recepción.

### Cómo entra el socio por primera vez

1. Abre `/ingresar` en su celular (o toca **Socios** en el sitio público).
2. Escribe su **DNI** como usuario (también puede usar su email) y su **DNI** como contraseña.
3. El portal le pide elegir una **contraseña propia**: al menos 8 caracteres, con letras y números, y distinta de su DNI.
4. Recién después puede usar el resto del portal.

Si marca **Mantener la sesión iniciada**, no tiene que volver a ingresar en ese celular.

### Instalar el portal como una app

- **Android (Chrome):** menú del navegador → **Agregar a la pantalla de inicio**.
- **iPhone (Safari):** botón **Compartir → Agregar a inicio**.

Queda un ícono en el celular que abre el portal a pantalla completa.

### Qué pantallas tiene

| Pantalla | Qué muestra |
| --- | --- |
| **Inicio** | El termómetro de ocupación, los anuncios vigentes y su membresía: estado, créditos (o "Pase libre"), plan, vencimiento y días que faltan |
| **Escanear QR** | La cámara para registrar el ingreso |
| **Historial** | Sus últimos pagos y sus últimas asistencias |
| **Mi cuenta** | Sus datos (no los puede cambiar), el cambio de contraseña y el botón para salir |

El **termómetro de ocupación** dice "Tranqui", "Movido" o "A pleno" según cuánta gente ingresó en los últimos 90 minutos: hasta 15, hasta 25 o más. No muestra el número y se actualiza cada 5 minutos.

Un socio nunca ve datos de otro. El portal tampoco le muestra sus observaciones de salud, su certificado, su teléfono ni su fecha de nacimiento.

### Problemas frecuentes del socio

| El socio dice… | Qué hacer |
| --- | --- |
| "Me olvidé la contraseña" | [Restablecé su contraseña](#restablecer-la-contraseña-del-portal-de-un-socio) y avisale que entre con el DNI |
| "Me dice cuota vencida" | Su membresía está En pausa. Cobrale la renovación o dale cortesía |
| "No me abre la cámara" | Tiene que darle permiso de cámara al navegador. El sitio debe abrirse con `https://` |
| "Escaneé dos veces" | Dentro de los 10 minutos no se le descuenta otro crédito |
| "Mis datos están mal" | Los corregís vos desde **Editar** en su ficha |
| "No puedo entrar al portal" | Revisá que no esté dado de baja y que escriba bien el DNI |

## Lo que el sistema hace solo

Todas las madrugadas, en hora argentina, el sistema ordena las membresías sin que nadie tenga que hacer nada.

| Hora | Qué hace |
| --- | --- |
| **01:00** | **Cierra los meses terminados.** Las membresías Entrenando cuya fecha de vencimiento ya pasó quedan En pausa. El cliente también pasa a En pausa, salvo que tenga otra membresía que lo deje entrar |
| **01:05** | **Red de seguridad de renovaciones.** Si una membresía con el ciclo terminado tiene un pago posterior a su vencimiento y no quedó renovada, le inicia el mes nuevo |

Detalles que conviene saber:

- Una membresía que vence hoy sigue Entrenando hasta mañana.
- Las membresías en **Activo flex** (cortesía) no se tocan.
- "En pausa" aparece en naranja, nunca en rojo: avisa que toca renovar, sin alarmar.

## Preguntas frecuentes

### ¿Por qué un cliente que pagó sigue en "Para cobrar"?

Porque pagó antes de que terminara su mes y el vencimiento no se movió. Ver [Cobrar por adelantado](#cobrar-por-adelantado).

### ¿Por qué el botón "Registrar pago" está gris?

El cliente no tiene ninguna membresía a la que cobrarle: o no tiene ninguna, o todas están Vencidas. Creale una desde la pestaña **Membresías**.

### ¿Por qué no aparece "Dar cortesía"?

Solo aparece si la membresía vigente está Entrenando o En pausa. No aparece si ya tiene cortesía (Activo flex), si está Vencida o si el cliente no tiene membresía.

### ¿Por qué no puedo borrar una membresía?

Porque ya tiene pagos registrados. Pasala a **Vencida**.

### ¿Por qué no me deja guardar un cliente?

Lo más común: el DNI o el email ya los tiene otro cliente (también cuenta uno dado de baja), falta un dato obligatorio o un archivo pesa más de 2 MB.

### ¿Un socio con créditos en negativo puede entrar?

Sí. Los créditos no bloquean el ingreso: solo lo bloquea el estado de la membresía (En pausa o Vencida).

### ¿Qué diferencia hay entre "En pausa" y "Vencida"?

**En pausa** es transitorio: terminó el mes y se espera que renueve; se le puede cobrar. **Vencida** es definitiva: la membresía se cerró, no admite pagos y no vuelve a activarse.

### ¿Puede un cliente tener dos membresías?

Sí. Al cobrar, el sistema te pide elegir a cuál corresponde el pago. La ficha muestra como "vigente" la abierta que empezó más recientemente.

## Mantenimiento

Esta sección es solo para quien administra el sistema. Las tareas se hacen en el servidor, no desde el panel.

### Quién puede ver este manual

Solo la cuenta cuyo email coincide con `ADMIN_EMAIL` del archivo `.env`. Para el resto del personal, el enlace no aparece en el menú y la dirección responde "Prohibido".

> Si cambiás el email de esa cuenta desde **Perfil**, actualizá también `ADMIN_EMAIL` en el `.env` y corré `php artisan config:cache`. Si no, dejás de ver el manual.

### Cuentas del personal

Hoy todas las cuentas del personal tienen los mismos permisos en el panel, y **no se pueden crear cuentas nuevas desde el panel**. La cuenta del administrador se crea con los datos `ADMIN_*` del `.env` al correr `php artisan db:seed --force`.

### Tareas habituales

| Necesito… | Qué hacer |
| --- | --- |
| Cambiar el código del QR de ingreso | Cambiar `GYM_CHECKIN_TOKEN` en el `.env`, correr `php artisan config:cache` y reimprimir el cartel |
| Destrabar a alguien del personal que perdió su celular | `php artisan staff:disable-two-factor su@email.com` |
| Obligar al personal a usar verificación en dos pasos | Hoy es opcional. Para obligarla: `STAFF_TWO_FACTOR_REQUIRED=true` en el `.env` y `php artisan config:cache` |
| Borrar los clientes y anuncios de ejemplo | `php artisan demo:remove` |
| Correr a mano el cierre de meses | `php artisan memberships:update-completed-cycles` |
| Correr a mano la red de seguridad de renovaciones | `php artisan memberships:renew-credits` |
| Comprobar que las tareas automáticas están programadas | `php artisan schedule:list` |
| Ver los últimos errores | `tail -n 50 storage/logs/laravel.log` |

Las tareas automáticas de la madrugada solo corren si el servidor tiene activado el cron que ejecuta `php artisan schedule:run`.

### Qué hay que resguardar

- **La `APP_KEY` del `.env`.** Las observaciones de salud se guardan cifradas con esa clave. Si se pierde o se cambia, **no se pueden recuperar**. Guardala aparte, junto con los backups.
- **La base de datos.** Contiene clientes, membresías, pagos y asistencias.
- **La carpeta `storage/app`.** Contiene las fotos, los certificados y las imágenes de los anuncios.

### Lo que el sistema todavía no hace

- Editar o anular un pago desde el panel.
- Crear cuentas del personal o darles permisos distintos.
- Cobrar en línea con una pasarela de pago.
- Reservar turnos o clases.
- Registrar la salida del gimnasio.
- Administrar desde el panel la portada, los horarios, la comunidad y las opiniones del sitio.

El detalle técnico (instalación, despliegue y seguridad) está en el archivo `README.md` del proyecto.
