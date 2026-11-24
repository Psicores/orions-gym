# Estrategia comercial y precios

Guía para vender el sistema a gimnasios de Argentina: chicos de barrio, medianos y en crecimiento.
Se apoya en lo que el sistema hace hoy (ver `MODULOS_Y_FUNCIONALIDADES.md`) y en el plan de
plantilla (ver `BACKLOG_PLANTILLA.md`).

**Premisa:** precios accesibles y claros para sumar muchos gimnasios y construir un ingreso mensual
recurrente. No se busca vender caro a uno solo: se busca que cada gimnasio pague poco, todos los
meses, durante años.

**Precios en USD.** Son de referencia y no incluyen IVA. Cómo pasarlos a pesos: ver
[Cómo cobrar en pesos](#cómo-cobrar-en-pesos). Los costos de terceros (Hostinger, Meta, Mercado
Pago, dominios) se relevaron en noviembre de 2026: **verificalos antes de presupuestar**, porque
cambian seguido.

---

## 1. Cómo venderlo

### Propuesta de valor

> **"Sabé en todo momento quién te debe, cobrá más rápido y dejá de perder plata en el mostrador.
> Con tu marca, tu página y tus socios entrando con el celular."**

Qué dolor concreto le saca al dueño:

| Dolor del dueño | Qué hace el sistema | Módulo |
| --- | --- | --- |
| "No sé quién me debe; me entero tarde" | Estados automáticos y lista **Para cobrar** con cobro en un clic | Gestión |
| "Se me va plata en la caja y no sé quién cobró qué" | Cada cobro registra quién lo hizo; **Ingresos** muestra el día por persona y medio | Gestión |
| "Los socios me preguntan todo el día cuántas clases les quedan" | Portal en el celular: cuota, vencimiento, créditos, historial | Portal |
| "Entra gente que no pagó" | Ingreso con QR: descuenta clases y avisa si debe | Portal + QR |
| "El kiosco es un agujero negro: falta mercadería y no sé por qué" | Stock con historial, ventas con quién vendió, anulaciones con motivo | Kiosco |
| "No tengo página o la tengo desactualizada" | Página propia con planes y precios que se actualizan solos desde el panel | Landing |
| "Mis profes cobran y después no me cierra" | Roles: el profe cobra y vende, pero no toca precios ni ve lo de los demás | Gestión |
| "Uso cuaderno o Excel y se pierde todo" | Todo en la nube, con copias de seguridad diarias | Todos |

**Diferenciales frente a la competencia** (los sistemas locales cobran entre US$ 25 y 90 por mes,
ver [Referencias](#referencias)):

- **Marca propia:** su logo, sus colores, su dominio. No es "una cuenta más" dentro de otra app.
- **Página web incluida** y editable, conectada con los planes y las novedades.
- **App para el socio sin pasar por tiendas** (PWA): se instala desde el navegador.
- **Ingreso con QR sin hardware:** sin molinete, sin lector. Un cartel impreso.
- **Kiosco integrado** con descuento automático para socios al día.
- **Soporte directo por WhatsApp** con quien lo desarrolló, en castellano y en horario argentino.
- **Pensado para Argentina:** efectivo con precio distinto, transferencias, celulares +549, pesos.

### Argumentos de venta y objeciones

| Objeción | Respuesta |
| --- | --- |
| **"Ya uso Excel / cuaderno."** | "El Excel te dice lo que anotaste, no quién no vino a pagar. El sistema te avisa solo quién vence esta semana y quién ya está debiendo. Si recuperás una cuota por mes que hoy se te escapa, el sistema se paga solo." |
| **"No quiero pagar todos los meses."** | "El abono incluye el servidor, las copias de seguridad, las actualizaciones y el soporte. Es lo que te garantiza que funcione el lunes a las 7 de la mañana. Cuesta menos que una cuota de un socio." Ofrecer pago anual con 2 meses de regalo. |
| **"Mis profes no saben usar sistemas."** | "Si usan WhatsApp, lo pueden usar. Cobrar son dos clics. La capacitación está incluida y el manual está dentro del sistema." Mostrar la pantalla de venta del kiosco en la demo. |
| **"Es caro."** | Comparar con lo que pierde: una cuota mensual que se escapa, mercadería que falta, una hora diaria de recepción respondiendo "¿cuántas clases me quedan?". Ofrecer el paquete Inicial y sumar después. |
| **"¿Y si después no lo quiero?"** | "Tus datos son tuyos: si te vas, te los entrego en Excel. Sin permanencia larga." (Ver términos.) |
| **"Ya tengo otro sistema."** | "¿Te da tu página, tu marca y el kiosco? Te migro los socios sin costo extra si contratás el Profesional." |
| **"Mis socios son grandes, no van a usar la app."** | "La app es opcional. El sistema sirve igual desde recepción. Los que la usen te ahorran preguntas." |
| **"¿Y si se cae internet?"** | "El sistema está en la nube: funciona desde el celular con datos aunque se corte el wifi del gimnasio." |

### Proceso de venta recomendado

1. **Contacto:** recomendación, Instagram, proveedores de equipamiento, Orions como caso de éxito.
2. **Demo de 20 minutos** en el gimnasio demo (DEM-01 del backlog), con datos de ejemplo:
   - cobrar una cuota;
   - ver "Para cobrar";
   - el socio escanea el QR con el celular del dueño;
   - una venta en el kiosco.
3. **Presupuesto el mismo día** (plantilla al final).
4. **Cierre:** seña del 50 % del setup y fecha de arranque.
5. **Implementación** (de 3 a 7 días según los datos) y **capacitación**.
6. **Seguimiento a los 15 y a los 30 días:** ofrecer un módulo extra (upselling) y pedir un testimonio.

### Modelo de venta: setup + abono mensual (recomendado)

| | Setup + abono mensual | Licencia única |
| --- | --- | --- |
| Entrada para el gimnasio | Baja (fácil decir que sí) | Alta (cuesta cerrar) |
| Tu ingreso | Recurrente y predecible | Un solo pago; después, nada |
| Actualizaciones y seguridad | Incluidas: todos en la misma versión | Hay que cobrarlas aparte y suelen quedar desactualizados |
| Soporte | Previsible y pago | Se pide gratis "porque ya lo pagué" |
| Valor de tu negocio | Crece con cada cliente | No crece |

**Recomendación:** siempre setup + abono. Licencia única solo si el gimnasio insiste, y con estas
condiciones: **US$ 1.500 o más**, hosting a su cargo, **sin actualizaciones** y soporte a valor hora.
Casi nadie la elige cuando ve las dos opciones lado a lado, y está bien que así sea.

---

## 2. Costos e ítems por unidad

### Valor hora de referencia

- **Valor hora interno:** US$ 20 (lo que vale tu tiempo para armar presupuestos de setup).
- **Valor hora para trabajos fuera del abono:** **US$ 25**, o paquete de 5 horas a US$ 110.

Ajustalo a tu realidad, pero usá siempre el mismo número: es lo que hace que los presupuestos cierren.

### 2.1 Infraestructura y hosting

**Opción A: varios gimnasios en un plan de Hostinger (recomendada).** Cada gimnasio es un sitio
aparte (su dominio, su base de datos, su `.env`) dentro de un mismo plan.

| Plan Hostinger | Precio de entrada | Precio de renovación | Gimnasios razonables | Costo por gimnasio en renovación |
| --- | --- | --- | --- | --- |
| Business (compartido) | aprox. US$ 3–4 por mes | aprox. US$ 17 por mes | 3 a 5 | US$ 3–6 por mes |
| Cloud Startup | aprox. US$ 7–8 por mes | aprox. US$ 26 por mes | 8 a 15 | US$ 2–3 por mes |

- **Presupuestá siempre con el precio de renovación**, no con el de entrada: es de 3 a 5 veces más
  caro y llega al terminar el primer período.
- Conviene pagar 24 a 48 meses por adelantado: congela el precio y lo amortizás con los abonos.
- La cantidad de gimnasios por plan es una estimación: depende de cuántos socios y cuánto uso
  tengan. Mirá el consumo de CPU y RAM en hPanel y pasá a un plan mayor cuando se acerque al límite.
- **Costo de referencia para presupuestar: US$ 4 por gimnasio por mes**, con margen.

**Opción B: servidor propio para un cliente** (más aislamiento, cliente grande o que lo exige).

| Ítem | Costo aproximado |
| --- | --- |
| VPS de Hostinger (KVM 1 o 2) | US$ 10–20 por mes en renovación (las fuentes no coinciden: verificar) |
| Tu tiempo de administración del servidor (actualizaciones del sistema operativo, seguridad, PHP, backups) | 1 a 2 horas por mes, unos US$ 25–50 |
| **Precio sugerido al cliente** | **+ US$ 45 por mes sobre el abono**, y setup de servidor de US$ 80 |

En un VPS, el sistema operativo, el firewall y las actualizaciones pasan a ser tu responsabilidad.
No lo ofrezcas de entrada: solo si el cliente lo pide.

**Dominio, SSL y correo**

| Ítem | Costo aproximado | Quién lo paga |
| --- | --- | --- |
| Dominio `.com.ar` (NIC Argentina) | aprox. AR$ 8.500 por año (valor 2025, verificar) | El gimnasio, **a su nombre** |
| Dominio `.com` | aprox. US$ 10–15 por año | El gimnasio |
| SSL | Gratis (Let's Encrypt, incluido en Hostinger) | — |
| Correos del dominio (`recepcion@gimnasio.com.ar`) | Incluidos en algunos planes de Hostinger según el plan; si no, Google Workspace a unos US$ 7 por usuario por mes (verificar) | El gimnasio, aparte |

**El dominio siempre a nombre del gimnasio** (con su CUIT o DNI en NIC Argentina). Si después se
van, no queda el reclamo de "me tenés secuestrado el dominio". El hosting, a tu nombre.

### 2.2 Etapas de implementación (setup)

| Etapa | Qué incluye | Horas | Precio |
| --- | --- | --- | --- |
| **Instalación y despliegue** | Sitio en Hostinger, base de datos, `.env`, `gym:install`, cron, SSL, dominio, verificación | 2–3 h | US$ 60 |
| **Marca** | Logo, colores, íconos de la app, nombre, datos del gimnasio | 1–2 h | US$ 40 |
| **Carga inicial** | Planes, personal con roles y hasta 100 socios desde Excel (con importador) | 2 h | US$ 40 |
| Carga extra de socios | Cada 100 socios más, o Excel desprolijo que hay que limpiar | 1 h por bloque | US$ 20 por bloque |
| **Capacitación** | 1 sesión con el dueño (1 h) + 1 con los profes (1 h), presencial o virtual, más el manual integrado | 2 h | US$ 40 |
| **QR de ingreso** | Puntos de acceso, impresión del cartel y prueba con socios | 0,5 h | Incluido en instalación |
| **Total base (Gestión + Portal)** | | 7–9 h | **US$ 180** |

### 2.3 Precio por módulo

| Módulo | Qué incluye | Setup | Mensual |
| --- | --- | --- | --- |
| **Gestión** (base, obligatorio) | Socios, membresías, planes, cobros, estados automáticos, "Para cobrar", Ingresos y rendición de caja, roles de profes, escritorio, manual | Incluido en la implementación | **US$ 20** |
| **Portal + QR** | App PWA del socio, ingreso con QR, puntos de acceso, ocupación, anuncios en el portal | Incluido en la implementación base; US$ 40 si se agrega después | **US$ 10** |
| **Kiosco** | Productos, stock, pantalla de venta PC/tablet, lector de códigos, descuento de socio, historial y anulación | US$ 50 (configuración, hasta 50 productos cargados y prueba del lector) | **US$ 8** |
| **Landing + gestión** | Página pública con su marca, secciones editables, planes y novedades automáticas, dominio propio | US$ 120 (armado de textos, fotos, horarios y portada) | **US$ 7** |

**Módulos futuros** (precios orientativos para cuando estén listos; ver Fase 4 del backlog):

| Módulo | Setup | Mensual | Además |
| --- | --- | --- | --- |
| Botón de WhatsApp (mensaje armado en un clic) | US$ 20 | Incluido en Profesional y Full; US$ 3 suelto | — |
| WhatsApp automático (recordatorios, pago recibido) | US$ 80 (verificación en Meta, plantillas) | US$ 12 | **+ consumo de Meta al costo** (ver 4) |
| Cobros online con Mercado Pago (QR, link) | US$ 80 | US$ 12 | La comisión de Mercado Pago la paga el gimnasio |
| Débito automático de la cuota | US$ 60 | US$ 10 | Ídem |
| Reservas de clases con cupo | US$ 80 | US$ 12 | — |
| Rutinas y progreso | US$ 60 | US$ 10 | — |
| Caja con apertura y cierre, y fiado | US$ 40 | US$ 6 | — |
| Facturación electrónica ARCA | US$ 100 | US$ 12 | Requiere certificado del gimnasio en ARCA |
| Servidor propio (VPS) | US$ 80 | US$ 45 | Ver 2.1 |

### 2.4 Abono mensual: qué incluye y qué no

**Incluye:**

- Hosting en el servidor, base de datos y SSL.
- **Copias de seguridad:** diarias automáticas de Hostinger, más una semanal propia descargada fuera del servidor.
- **Monitoreo:** aviso automático si el sitio se cae (por ejemplo, UptimeRobot gratuito) y revisión mensual de errores.
- **Actualizaciones:** de seguridad (Laravel, Filament, PHP) y del sistema (mejoras de la plantilla que salen para todos).
- **Soporte por WhatsApp:** lunes a viernes de 9 a 18. Respuesta en menos de 24 horas hábiles; si el sistema no anda, menos de 4 horas.
- **Hasta 1 hora por mes de pequeños cambios:** un texto, un plan, una duda de uso, un ajuste de un dato. No es acumulable.
- **Capacitación de un profe nuevo por mes, si hace falta** (30 minutos).

**No incluye** (se cobra aparte a valor hora o con presupuesto):

- Desarrollos a medida o funciones nuevas que pide solo ese gimnasio.
- Rediseños de la página o cambios grandes de diseño.
- Cargas masivas de datos posteriores al arranque.
- Soporte fuera de horario, fines de semana y feriados: **valor hora x 1,5**, salvo caídas del sistema, que siempre se atienden.
- Restaurar datos borrados por error del gimnasio: una restauración por año sin cargo; después, valor hora.
- Costos de terceros: Meta, Mercado Pago, dominio, correos, hardware (lector, tablet, impresora).
- Integraciones con otros sistemas.

**Una regla para no regalar horas:** todo pedido que no entra en "1 hora de cambios chicos" se
responde con un presupuesto antes de hacerlo. "Lo hago y después vemos" es la forma más rápida de
trabajar gratis.

---

## 3. Paquetes comerciales

| | **Inicial** (gancho) | **Profesional** (el recomendado) | **Full** |
| --- | --- | --- | --- |
| Gestión: socios, cobros, Ingresos, roles | ✔ | ✔ | ✔ |
| Portal del socio + ingreso con QR | ✔ | ✔ | ✔ |
| Kiosco | — | ✔ | ✔ |
| Landing + gestión de la página | — | ✔ | ✔ |
| Botón de WhatsApp | — | ✔ | ✔ |
| Módulos nuevos a medida que salen (cobros online, reservas, WhatsApp automático, rutinas) | — | Con descuento | ✔ Incluidos (más consumos de terceros) |
| Soporte | Normal | Normal | **Prioritario** (respuesta en menos de 4 h hábiles) y 2 h por mes de cambios |
| Dominio incluido | — | — | ✔ |
| **Setup** | **US$ 120** | **US$ 290** | **US$ 450** |
| **Abono mensual** | **US$ 25** | **US$ 39** | **US$ 59** |
| Si se paga por año | US$ 250 (2 meses de regalo) | US$ 390 | US$ 590 |

**Por qué estos números:**

- **Inicial** cuesta menos que la suma de sus módulos (US$ 30 por mes) y queda en el rango más bajo del mercado: es para que digan que sí rápido. El setup se bonifica de US$ 180 a US$ 120.
- **Profesional** suma módulos que valen US$ 48 por mes sueltos, por US$ 39, y su setup baja de US$ 370 (base 180 + kiosco 50 + landing 120 + WhatsApp 20) a US$ 290. Es el paquete que más conviene y el que hay que mostrar primero.
- **Full** existe sobre todo para que Profesional parezca razonable (efecto ancla), y para los gimnasios en crecimiento que quieren todo.

**El gancho para entrar rápido:**

- **Precio fundador** para los primeros 5 gimnasios: 50 % de descuento en el setup y el abono congelado 12 meses, a cambio de un testimonio y de poder mostrarlos como caso.
- **Setup en 2 o 3 cuotas.**
- **Primer mes de abono sin cargo** si arranca en el mes.

**Upselling, en este orden** (lo que más suele pedir el dueño después de usarlo):

1. **Del Inicial al Profesional:** a los 30 días, cuando el dueño ya confía. "Sumá la página y el kiosco: te descuento el setup de lo que ya pagaste."
2. **Cobros online:** cuando ve la lista "Para cobrar" llena. "Que te paguen desde el celular sin pasar por recepción."
3. **WhatsApp automático:** cuando se cansa de mandar recordatorios a mano.
4. **Reservas de clases:** para gimnasios con funcional, spinning o yoga.

### Proyección de ingresos recurrentes

Abono promedio estimado: US$ 35 (mezcla de los tres paquetes).

| Gimnasios | Ingreso mensual | Hosting (aprox. US$ 4 por gimnasio) | Soporte estimado (1 a 1,5 h por gimnasio) | Margen antes de impuestos |
| --- | --- | --- | --- | --- |
| 5 | US$ 175 | US$ 20 | 5–8 h | aprox. US$ 155 |
| 10 | US$ 350 | US$ 40 | 10–15 h | aprox. US$ 310 |
| 25 | US$ 875 | US$ 100 | 25–38 h | aprox. US$ 775 |
| 50 | US$ 1.750 | US$ 200 | 50–75 h | aprox. US$ 1.550 |

A partir de unos 25 gimnasios, el soporte ocupa casi una semana de trabajo por mes. Ahí conviene:
- mejorar la documentación y el manual (menos consultas);
- sumar a alguien para el soporte de primer nivel;
- subir los precios para los clientes nuevos.

### Cómo cobrar en pesos

- **Precio en USD, factura en pesos** al tipo de cambio del día de emisión. Definí cuál usás (oficial vendedor del BNA o MEP), ponelo por escrito en el presupuesto y no lo cambies.
- **Alternativa más simple para el cliente:** el precio en pesos fijo por trimestre, recalculado con el dólar al inicio de cada trimestre.
- Aceptá pago en USD (transferencia o efectivo) si el cliente lo prefiere.
- **Cobro del abono:** débito automático o suscripción de Mercado Pago (descontá su comisión de tu margen), o transferencia del 1 al 10 de cada mes.
- Si sos **monotributista**, los precios van "finales". Si sos **responsable inscripto**, van "+ IVA". Fijate que el total anual entre en tu categoría de monotributo.

---

## 4. Gastos ocultos y consideraciones operativas

### Costos de terceros

| Ítem | Referencia (verificar) | Cómo manejarlo |
| --- | --- | --- |
| **Renovación del hosting** | De 3 a 5 veces el precio de entrada | Presupuestar con la renovación y pagar períodos largos |
| **WhatsApp automático (Meta)** | En Argentina, aprox. US$ 0,026–0,030 por mensaje de utilidad (recordatorios, pago recibido) y US$ 0,06–0,07 por mensaje de marketing; responder dentro de las 24 h es gratis. Meta cambia las tarifas cada trimestre | **Se trasladan al gimnasio al costo** (o un paquete: "hasta 500 mensajes por mes incluidos"). Ejemplo: 200 socios × 2 recordatorios = 400 mensajes, unos US$ 12 por mes |
| **Verificación en Meta** | Gratis, pero tarda días o semanas y requiere un número de teléfono **dedicado** | Incluirlo en el setup del módulo y avisar los tiempos |
| **Mercado Pago** | QR con dinero en cuenta, aprox. 0,8 %; QR con débito, aprox. 1,35 %; crédito o link de pago con el dinero al instante, aprox. 6–7 %; todos + IVA. Más barato si se espera la acreditación | **Lo paga el gimnasio.** Es una razón para mantener el precio en efectivo más bajo |
| **Dominio y correos** | Ver 2.1 | A nombre y a cargo del gimnasio |
| **Hardware** | Lector USB de US$ 20–40, tablet, impresora para el QR | Lo compra el gimnasio; vos recomendás modelos |
| **Herramientas propias** | Monitoreo, gestor de contraseñas y almacenamiento de backups fuera del servidor (gratis o pocos dólares por mes) | Dentro del costo de US$ 4 por gimnasio |

### Tu tiempo y tu negocio

- **Soporte fuera de horario:** si no lo ponés por escrito, el sábado a las 22 te escriben por un
  socio que no puede entrar. Definí horario, recargo y qué es "urgente" (el sistema no anda) contra
  "no urgente" (una duda).
- **Impuestos:** facturar requiere monotributo o responsable inscripto. Considerá IIBB de tu provincia y que los cobros por Mercado Pago tienen retenciones.
- **Inflación y tipo de cambio:** sin una cláusula de ajuste, en 6 meses trabajás más barato. Precio en USD o ajuste trimestral, por escrito.
- **Dependencia de una sola persona:** si te enfermás o te vas de vacaciones, nadie más sabe operar. Tené documentado el alta y los despliegues (`ALTA_GIMNASIO.md`, `PUESTA_A_PUNTO_PRODUCCION.md`) y las credenciales en un gestor de contraseñas compartible con alguien de confianza.
- **Escala de soporte:** cada gimnasio nuevo suma consultas. Medí las horas por cliente desde el primer día.

### Datos personales y responsabilidad

- El sistema guarda **datos personales y de salud** (apto médico, observaciones). Por la **Ley 25.326**:
  - el **gimnasio es el responsable** de esos datos;
  - vos sos el **encargado del tratamiento** (los alojás y procesás por cuenta del gimnasio).
  - Ponelo en el contrato.
- **Consentimiento:** el sistema ya registra si cada socio aceptó avisos por WhatsApp y novedades. Nunca mandar mensajes a quien no aceptó.
- **Seguridad:**
  - los datos de salud van cifrados;
  - la `APP_KEY` de cada gimnasio se guarda aparte. Si se pierde, esos datos no se recuperan.
- **Disponibilidad:** en hosting compartido **no prometas 99,9 %**. Prometé "monitoreo y respuesta en menos de 4 horas ante una caída en horario".

### Propiedad del código y términos

- **El código y la plantilla son tuyos.** El gimnasio recibe una **licencia de uso** mientras pague el abono: no compra el código ni puede revenderlo ni copiarlo.
- **Los datos son del gimnasio.** Si se va, recibe una exportación de sus socios, pagos y ventas en Excel o CSV (una vez sin cargo).
- **El dominio es del gimnasio** (a su nombre). El hosting y el sistema, tuyos.
- **Permanencia:** sin permanencia larga. Mes a mes, con aviso de 30 días para darse de baja. El pago anual no se reintegra.
- **Cambios de precio:** aviso con 30 días. Los fundadores tienen el precio congelado 12 meses.

### Qué pasa si el cliente deja de pagar

Definilo por escrito desde el primer presupuesto:

| Día | Qué pasa |
| --- | --- |
| Vencimiento (día 10) | Recordatorio amable |
| Día 15 | Segundo aviso: "el día 20 el sistema pasa a solo lectura" |
| Día 20 | **Modo suspendido:** el panel muestra un aviso, se puede consultar pero no cobrar ni vender; el portal y el QR siguen andando (no castigar a los socios) |
| Día 45 | Sitio dado de baja; los datos se conservan 60 días más |
| Día 105 | Se entrega la exportación de datos si la pide y se borra todo |
| Reactivación | Pagar lo adeudado más un cargo de reconexión de US$ 20 |

Hoy el sistema **no tiene modo suspendido ni exportación de datos automática**. Están en el backlog
de la plantilla (OPS-06 y OPS-07) para antes del segundo gimnasio de clientes.

### Para el backlog

Ítems comerciales que surgen de esta estrategia, ya sumados a `BACKLOG_PLANTILLA.md`:

- **Modo suspendido** por falta de pago (OPS-06): un interruptor en `.env` que deja el panel en solo lectura con un aviso, sin cortar el portal ni el QR.
- **Exportación de datos** del gimnasio (OPS-07): socios, membresías, pagos, ventas y stock en CSV, desde un comando.
- **Importadores** de socios y productos (PRD-03 y PRD-04): bajan el costo del setup.
- **Monitoreo** (OPS-08): un endpoint de salud para UptimeRobot que incluye la última corrida del cron (OPS-01).

---

## 5. Plantilla de presupuesto

Para mandar por WhatsApp o pegar en un PDF. Completá lo que está entre corchetes.

```text
PRESUPUESTO · Sistema de gestión para [NOMBRE DEL GIMNASIO]
Fecha: [dd/mm/aaaa] · Válido por 15 días

Hola [NOMBRE DEL DUEÑO]: te paso la propuesta que charlamos.

QUÉ INCLUYE — Paquete [INICIAL / PROFESIONAL / FULL]
✔ Gestión de socios, membresías y cobros (quién debe, "Para cobrar", ingresos del día por persona)
✔ Roles para tus profes (cobran y venden, sin tocar precios)
✔ App para tus socios en el celular + ingreso con QR
[✔ Kiosco: ventas, stock y lector de códigos, con descuento para socios al día]
[✔ Página web con tu marca, editable desde el panel]
[✔ Botón de WhatsApp para recordarles la cuota a tus socios]

IMPLEMENTACIÓN (pago único)
· Instalación en servidor, dominio y SSL
· Tu logo, tus colores y tus datos
· Carga de planes, personal y hasta [100] socios desde tu Excel
· Capacitación para vos y tus profes (2 sesiones)
Total implementación: US$ [___] — [50 % al confirmar y 50 % al arrancar / en 3 cuotas]

ABONO MENSUAL: US$ [___]
Incluye servidor, copias de seguridad diarias, actualizaciones, monitoreo
y soporte por WhatsApp (lun a vie de 9 a 18, respuesta en menos de 24 h hábiles).
Pago anual: US$ [___] (2 meses de regalo).

NO INCLUYE: dominio ([US$/AR$ ___] por año, a tu nombre), desarrollos a medida
(US$ 25 por hora, siempre con presupuesto previo), hardware (lector, tablet).

CONDICIONES
· Precios en USD, se pagan en pesos al dólar [oficial BNA / MEP] del día de la factura.
· Sin permanencia: te das de baja avisando con 30 días.
· Tus datos son tuyos: si te vas, te los entrego en Excel.
· Arranque estimado: [fecha], [3 a 7] días después de recibir tus datos.

[Precio fundador: 50 % de descuento en la implementación y abono congelado 12 meses.]

¿Arrancamos? Cualquier duda, escribime.
[TU NOMBRE] · [TU WHATSAPP]
```

---

## Referencias

Relevadas en noviembre de 2026. Son orientativas: verificar antes de presupuestar.

- Precios de Hostinger (entrada y renovación):
  [webhostingguide.net](https://webhostingguide.net/hostinger-pricing/),
  [cybernews.com](https://cybernews.com/best-web-hosting/hostinger-review/pricing/),
  [bearhost.com](https://bearhost.com/blogs/hostinger-pricing).
  Los precios de VPS difieren entre fuentes.
- Competencia en Argentina:
  [My Gym Online](https://www.my-gym.online/precios),
  [GymManager](https://gymmanager.com.ar/),
  [GymCore](https://www.gymcore.com.ar/),
  [GymGestión](https://gymgestion.com/argentina/),
  [Midu](https://midu.app/software-para-gimnasios),
  [comparativa de GymGestión](https://gymgestion.com/blog/software-gimnasio-argentina-comparativa.html).
  Rangos publicados: aprox. AR$ 25.000 a 145.000 por mes o US$ 25 a 89 por mes, según el proveedor y la cantidad de socios.
- WhatsApp Business Platform:
  [precios oficiales de Meta](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing)
  (cobro por mensaje desde julio de 2025);
  tarifas de Argentina según [SleekFlow](https://help.sleekflow.io/en_US/whatsapp/pricing) y
  [Zernio](https://zernio.com/blog/whatsapp-business-api-pricing). Las fuentes no coinciden: tomar la tarifa vigente de Meta.
- Dominio `.com.ar`: [guía de Tiendanube](https://www.tiendanube.com/blog/como-registrar-dominio-com-ar-en-nic/)
  (AR$ 8.500 por año en 2025) y [nic.ar](https://nic.ar) para el valor vigente.
- Comisiones de Mercado Pago:
  [iProfesional](https://www.iprofesional.com/tecnologia/386153-mercado-pago-que-debes-saber-sobre-comisiones-plazos-y-montos),
  [Cronista](https://www.cronista.com/finanzas-mercados/atencion-mercado-pago-la-app-te-cobra-esta-comision-por-usar-su-servicio),
  [Mercado Pago, cobrar con QR](https://www.mercadopago.com.ar/herramientas-para-vender/cobrar-con-qr?code=POINT_ORG).
  Las tasas exactas se ven en la cuenta de cada comercio.
