# Historial de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Versionado semántico.

## [Sin publicar]

### Cambiado
- **Leyes en la etiqueta de fábrica**: el origen («Producción Argentina»), SENASA, registro provincial, RENSPA y el
  Decreto-Ley 9.244/63 se imprimen salvo que se apaguen. La pantalla de imprimir avisa qué números legales faltan
  cargar (o si están apagados) con un enlace a Configuración → Etiquetas.
- **Pestañas más grandes y claras** en todas las secciones (Tesorería, Herramientas técnicas, Administración general,
  portal de clientes, Movimientos y el menú de Configuración): letra más grande y en negrita, íconos más grandes y la
  pestaña elegida bien marcada.

### Agregado
- **Etiquetas que se leen bien y a prueba de cambios**: el código del cajón va grande; donde iba el QR hay un
  **recuadro de seguridad** con el **peso neto en grande**, calibre, fecha de empaque y un **código de control**
  (CTRL) sobre un fondo rayado (si se borra o raspa algo, se nota). Si alguien cambia a mano el peso, el control no
  coincide con el que muestra el sistema en «Verificar etiqueta» (ficha del cajón, del pallet y Seguir un cajón).
  El código de barras sale siempre entero y a lo ancho, con el número abajo; si los datos no entran, la letra se
  achica sola. Etiquetas chicas (menos de 55×38 mm): sólo código, peso con control y código de barras. Tamaño mínimo
  50×30 mm.
- **Cajón de madera de fábrica**: cada galpón trae el envase «Cajón de madera» y los cajones nuevos (escaneo,
  etiquetas, alta a mano) lo toman si no se elige otro. Se cambia en Configuración → Producción → «Envase de los cajones».
- **Códigos con sólo el número en todo el sistema**: en los campos de cajón, pallet, lote, carga y remito el prefijo
  («CJ-», «PAL-», «LOT-», «CARG-», «0001-») va fijo al lado y alcanza con escribir el número (al apretar Enter o
  filtrar, «123» pasa a «CJ-000123»). Vale en los filtros de Cajones, Producción, Lotes, Cargas y Remitos, en el alta
  de cajones, en Escanear cajones, en Seguir un cajón y al armar cargas (ahí sólo el número = cajón; para un pallet,
  PAL-…). El lector de códigos sigue funcionando igual. En Pallets, buscar «45» encuentra PAL-000045.
- **Etiquetas por rango, más rápido**: «CJ-» va solo (se escribe sólo el número) y «Hasta» se completa de a 50
  (se puede cambiar con las flechitas o a mano; «Volver a 50» lo recalcula). Al abrir propone el bloque de 50 del
  último cajón. Generar cajones nuevos viene en 50 por defecto.
- **Capacidad de cada pallet (1 a 50 cajones)**: valor general en Configuración → Producción («Cajones por pallet») y
  propio de cada pallet. El sistema no deja cargar más cajones que esa cantidad (los anulados no cuentan); el pallet
  muestra «40 de 50 · Faltan 10» y el listado «40 / 50». Se ve de qué **ingreso de fruta (bines)** sale cada pallet.

### Corregido
- El código de barras de la etiqueta salía cortado abajo (el lector podía no leerlo).
- En Escanear cajones, los textos de ayuda de los campos grandes se cortaban en el celular.
- Reimprimir por rango no encontraba los cajones si el código se escribía con menos ceros («CJ-00001» en vez de
  «CJ-000001»): ahora se arma el código completo desde el número.
- El botón «Escanear cajones» de Registros de producción decía «Escanear cajocaneo».
- En las listas con búsqueda, «Todos» / «Todas» se ven como texto normal (antes en gris, parecía deshabilitado).

## [1.12.0] - 2026-11-10

### Agregado
- **Asociaciones de citricultores** (Administración general → Asociaciones): lista común a todo el sistema
  (nombre, nombre corto, CUIT, localidad, provincia, activa). Cada galpón se vincula a **una o a varias** desde su
  ficha. Las asociaciones del galpón salen impresas en sus **etiquetas de pallets y cajones**, **facturas**,
  **remitos**, **romaneos** y **resúmenes de cuenta**. El administrador del galpón las ve en Configuración → Empresa.

## [1.11.1] - 2026-11-10

### Cambiado
- **Administración general → Usuarios**: primero un cuadro por galpón (activo o suspendido, cuántos usuarios,
  cuántos con contraseña temporal y cuántos nunca entraron) y uno aparte para los Super Administradores; al tocar
  un galpón se ven sólo sus usuarios. Buscar sigue mirando todos los galpones y dice de cuál es cada usuario.

### Corregido
- **Importar una carpeta**: las planillas que dependen de otras de la misma carpeta (destinos de sus clientes,
  camiones y choferes de sus transportistas, empleados de sus cuadrillas) aparecían con todas sus filas en error
  en la vista previa. Ahora la vista previa simula la carga completa en orden (sin guardar nada) y muestra los
  mismos números que se van a cargar al confirmar.
- Los destinos encuentran a su cliente también por el nombre de fantasía.
- Mensaje claro cuando no se encuentra el cliente, transportista o cuadrilla de una fila: dice cuál y qué hacer.

## [1.11.0] - 2026-11-10

### Agregado
- **Multigalpón: un solo sistema para infinidad de galpones.** Cada galpón tiene sus propios usuarios, fichas
  (productores, clientes, camioneros, camiones…), lotes, cajones, pallets, cargas, remitos, facturas, caja,
  informes, configuración (nombre, CUIT, logo, código ARCA, etiquetas), numeraciones (cada uno empieza en 1) y
  módulos. **Ningún galpón ve ni toca datos de otro**: todas las consultas se filtran solas por galpón, las
  validaciones no dejan elegir un registro de otro galpón y los códigos se pueden repetir entre galpones.
- **Administración general → Galpones** (sólo el Super Administrador): crear galpones, crearles su
  **administrador general** (con contraseña temporal) y ver su puesta en marcha. El administrador de cada galpón
  carga a sus empleados y maneja todo su galpón. Un galpón se puede **suspender** (nadie de ese galpón entra; los
  datos se conservan).
- **Selector de galpón** en el encabezado para el Super Administrador; los demás ven en qué galpón están.
- Los avisos y alertas automáticas, el valor del dólar y las exportaciones grandes funcionan galpón por galpón.
- Lo que ya estaba cargado queda como el primer galpón: no se pierde ni se mueve nada. La base se actualiza sola
  en la primera visita (con copia de seguridad previa).

### Seguridad
- Con más de un galpón, las copias de seguridad (contienen la base entera) y los registros técnicos son sólo del
  Super Administrador, igual que la configuración de seguridad, ingreso y backups (valen para todo el sistema).
- Un administrador de galpón no puede abrir, editar ni cerrar la sesión de usuarios de otro galpón (ni del Super
  Administrador): para él no existen.

### Cambiado
- El Panel General de licencias pasa a llamarse **«Instalaciones aparte»** (galpones con su propio sistema), para
  no confundirse con los galpones de este sistema.
- **Panel General → Galpones** (antes «Clientes y uso»): el desarrollador da de alta cada galpón (nombre, CUIT,
  localidad, contacto, licencia y módulos) y anota el usuario de su **administrador general**, que crea a mano en el
  sistema del galpón. Ese administrador carga a sus empleados desde su propio sistema. Cada galpón tiene una
  **puesta en marcha** con los pasos (sistema conectado, administrador creado, empleados cargados) y el listado
  marca los que todavía no tienen administrador. Cada galpón sigue con su propio sistema: sus datos nunca se mezclan.
  Las direcciones viejas (`/panel-general/clientes`) redirigen solas.
- **Importar una carpeta entera** (Importar y exportar → «Importar una carpeta entera», o Planillas subidas): se elige
  una carpeta (o varias planillas) y cada una va sola a su sección (camioneros, productores, camiones, clientes…).
  Se decide por el nombre del archivo y los títulos de las columnas, y **ante la duda no se elige**: si las columnas
  sirven para dos secciones (por ejemplo productores y propietarios) o el nombre dice una cosa y las columnas otra,
  la planilla queda «sin reconocer» y se elige a mano. Nada se guarda hasta confirmar; al confirmar se cargan
  primero transportistas, clientes y cuadrillas, y después camiones, camioneros, destinos y empleados. Las planillas
  de DTV-e se derivan a Logística → DTV-e. También se puede subir la carpeta **comprimida en .zip** (la única forma
  desde el celular, que no deja elegir carpetas) o **arrastrarla** a la pantalla.
- **Código del galpón en ARCA en todo lo que se imprime**: etiquetas de cajones y pallets (aunque se oculten los
  datos de la empresa), remitos, facturas, romaneos y resúmenes de cuenta.
- La etiqueta del cajón muestra el **destino** (el de la carga en la que está, cuando ya se cargó).
- **Avisos de errores por correo** al desarrollador: cada 5 minutos se revisan los errores registrados y el log; se mandan
  agrupados por causa (como mucho un correo cada 15 minutos). Se configura en Herramientas técnicas → Avisos por correo,
  con botones para mandar un correo de prueba, revisar ahora y limpiar.
- **Limpieza semanal**: cada 7 días se borran los errores técnicos viejos y se vacían los logs (la auditoría no se toca).

### Corregido

### Seguridad
- Nadie que no sea el Super Administrador puede dar permisos que él mismo no tiene (ni editando un rol, ni con
  permisos individuales): evita que alguien con «Roles y permisos» se suba de rango solo.

- En los listados de clientes, productores, etc. se entra a la ficha **tocando el nombre o cualquier parte de la fila**
  (ya no hace falta el «Ver»).
- Al abrir la ficha de un cliente, productor, transportista, proveedor o empleado se ven **directo sus movimientos de
  cuenta corriente** (con filtro de fechas y saldo). Para cobrar, pagar o hacer ajustes: botón «Cuenta corriente».
- **Movimientos de cada cliente, productor, camionero, transportista, proveedor y empleado** (botón «Movimientos» en
  su ficha): en una sola lista se ve la plata (cuenta corriente), los bines y envases, la fruta que ingresó y las
  cargas, con filtros por tipo y fechas, y un resumen arriba (cuánto debe, cuántos bines tiene, kilos, cargas).
- **Bines y envases**: se registra cuántos se le entregan o devuelve cada uno. Si es **como parte de pago**, se
  descuenta (o se suma) en su cuenta corriente al valor que se indique; al anular uno se anula también el otro.
  Permisos nuevos: «Ver movimientos…» (`containers.view`) y «Registrar y anular… bines y envases» (`containers.manage`).

### Cambiado
- Todo con esquinas rectas (tarjetas, botones, campos, etiquetas, barras; sólo los círculos quedan redondos); colores de la marca un poco más suaves; importes en verde (entra) y rojo
  (sale) en caja y cuentas corrientes, siempre en un solo renglón.
- Guía de Hostinger: cómo configurar el correo (SMTP) para recibir soporte y avisos.
- Menú lateral en acordeón: se ven sólo los apartados (Fichas, Producción, Logística, …) y al tocar uno se despliegan
  sus opciones. Se agregó «Imprimir etiquetas» en Producción.
- Listas con búsqueda: en las listas largas (clientes, camioneros, transportistas, empleados, variedades, …) se
  escribe para filtrar y se elige con ↑ ↓ y Enter.
- El menú lateral ahora se despliega por secciones (Fichas, Producción, Galpón, Logística, …): la sección de la
  pantalla en la que estás queda abierta y las demás se abren o cierran con un clic; cada usuario la deja como prefiere.
- Se sacaron del menú **Mapa del galpón** y **Ubicaciones** (y su atajo G U). En su lugar, la sección **Galpón** del
  menú muestra el **código del galpón en ARCA**, que carga el dueño en Configuración → ARCA → «Código del galpón (ARCA)».

## [1.10.1] - 2026-10-03

### Corregido
- **Ingresos de fruta (lotes)** y **DTV-e** daban error en MySQL (Hostinger): los totales se calculaban mezclados con
  las columnas del listado. Ahora se calculan aparte.
- **La base de datos se pone al día sola después de cada actualización**: el deploy por Git de Hostinger no crea las
  tablas y columnas nuevas, por eso las pantallas nuevas (rendimiento de cera, DTV-e, etc.) daban error. La
  primera visita después de actualizar hace una copia de seguridad, crea lo que falta y agrega los permisos y
  módulos nuevos (sin pisar lo configurado). Si algo falla, el super admin lo ve en la administración general con
  un botón «Actualizar base de datos».

## [1.10.0] - 2026-10-03

### Agregado (lo que el galpón llevaba en la planilla de Excel)
- **DTV-e (SENASA)** (Logística → DTV-e): registro de ingresos y egresos con número, tipo, emisor, establecimiento,
  destinatario, destino, transporte y una línea por especie/variedad (cantidad, unidad, kg por unidad y kg totales,
  que se calculan solos). Saldo de kilos por variedad, filtros, exportación a Excel con las mismas columnas que la
  planilla e **importación de la planilla existente** (encuentra sola la fila de títulos y no repite DTV ya cargados).
  Se arma solo desde una carga (egreso) o un ingreso de fruta (ingreso).
- **Tratamientos** (Logística → Tratamientos): fecha, cliente, destino, cantidad, tipo de tratamiento (configurable
  en Configuración → Tratamientos) y empresa que lo hace, con totales por tipo y exportación.
- **Rendimiento de cera** (Galpón → Rendimiento de cera): desde, hasta y bultos de cada tambor de cera u otro
  insumo; los bultos se cuentan solos con la producción registrada o se cargan a mano.
- **Rendimiento por quinta** (Informes): bines y kilos que entraron de cada productor contra bultos y kilos
  empacados y descartados, con el porcentaje.
- **Ingresos de fruta (lotes)**: chofer, bines y n° de DTV; listado como la planilla de ingresos, con totales,
  filtro «temporada en curso» (ingresos corrientes) y exportación a Excel.
- **Cargas**: precio por unidad y subtotal (cajones × precio), como la planilla de cargas.

### Corregido
- Modo oscuro: los calendarios, relojes y flechas de las listas se dibujan claros (antes el ícono del calendario
  quedaba negro sobre negro).

## [1.9.2] - 2026-10-03

### Cambiado
- Logo nuevo: rodaja de naranja con hoja sobre verde degradado, en el menú, la pantalla de ingreso, la pestaña del
  navegador y los íconos de la app instalada (escritorio y celular).

## [1.9.1] - 2026-10-03

### Cambiado
- Nueva sección **Fichas** en el menú, a la vista: Clientes, Productos (variedades), Productores, Proveedores,
  Camioneros, Camiones, Transportistas, Embaladores, Empleados y «Todas las fichas…». La ficha de variedades se
  llama «Productos (variedades de fruta)».
- Las pantallas de impresión (etiquetas, credenciales, romaneo, resumen de cuenta) se abren en la misma ventana y
  siempre tienen el botón «← Volver» (en la app instalada no hay barra del navegador para volver).

## [1.9.0] - 2026-10-03

### Agregado
- **App instalable** en computadoras (Chrome/Edge) y celulares (Android y iPhone): botón «Instalar app» en el
  encabezado, ícono en el escritorio/menú Inicio/pantalla del celular y ventana propia como un programa. La app
  carga siempre desde el servidor: cada actualización que se sube (Hostinger → Implementar) la ven todos al
  abrirla, sin reinstalar. Lleva el nombre de la empresa. Si se corta la conexión muestra un aviso claro (no
  guarda pantallas ni datos en la PC). Instrucciones en Ayuda y atajos.

## [1.8.1] - 2026-10-03

### Cambiado
- **El Super Administrador es invisible para el galpón**: el dueño del galpón y su personal no lo ven en Usuarios,
  Roles, Actividad del personal, Quién está conectado, Historial de cambios, búsquedas ni listas para elegir
  personas; si hace falta mostrar quién hizo algo, dice «Soporte del sistema». Él sí ve a todos.
- Nada se desplaza hacia el costado: las tablas que no entran en el ancho se muestran como tarjetas (de a dos o
  más en pantallas anchas, una debajo de otra en el celular), las pestañas bajan de renglón, el mapa del galpón se
  achica para entrar y las fichas de detalle ya no se estiran por un dato largo.
- Se quitó el casillero «Puesto fijo de escaneo» del formulario de usuario: los accesos se eligen con los sectores.

## [1.8.0] - 2026-10-03

### Agregado
- **Importar y exportar** (en el menú, sección Sistema): un solo lugar para bajar a Excel o subir desde Excel
  clientes, proveedores, camioneros, camiones, transportistas, embaladores, productores y demás fichas, con su
  planilla modelo; también los movimientos (producción, cargas, rechazos, cuentas corrientes y cheques).
- **Datos de ejemplo** con un botón en la administración general: al menos 10 de cada cosa (fichas, 10 cámaras de
  frío, lotes, pallets, cajones con etiqueta, 10 cargas con remitos y facturas, caja, cheques, cuentas corrientes,
  máquinas y mantenimientos, incidentes, costos, paradas de línea, insumos y sus movimientos). No crea usuarios
  y funciona en el hosting (no necesita consola ni dependencias de desarrollo).

### Cambiado
- Textos más simples, en criollo: «Inicio» (antes Dashboard), «Escanear cajones», «Cámaras de frío», «Fichas»
  (antes Catálogos), «Camioneros» (antes Choferes), «Copias de seguridad» (antes Backups), «Balanzas y sensores»
  (antes Tokens de API, con una explicación de para qué sirve), «Historial de cambios» (antes Auditoría),
  «Herramientas técnicas», «Puesto fijo de escaneo» (antes Modo kiosco), entre otros.
- El menú de la izquierda queda donde lo dejaste al cambiar de pantalla (antes volvía siempre arriba).
- Los importes de las tarjetas ya no se cortan a la mitad en pantallas angostas: se achica la letra.

## [1.7.2] - 2026-10-03

### Agregado
- «Recuperar acceso del dueño» permite elegir también un usuario nuevo para el Super Administrador.

## [1.7.1] - 2026-10-03

### Agregado
- **Recuperar acceso del dueño** (enlace en la pantalla de ingreso): si se olvidó el usuario o la contraseña del
  Super Administrador, con la contraseña de la base de datos se elige una nueva y se muestra el usuario.
- Al escribir `/administradorgeneral` sin haber ingresado, la pantalla de ingreso lo explica y, al ingresar,
  lleva directo a la administración general.

### Corregido
- «Sesión expirada» (419) en la pantalla de ingreso: vuelve al ingreso con un aviso en lugar del error.

## [1.7.0] - 2026-10-03

### Agregado
- **Administración general** en `/administradorgeneral`, sólo para el Super Administrador y pidiendo de nuevo su
  contraseña cada 15 minutos (todo queda auditado):
  - **Usuarios**: todos los datos de cada usuario para ayudarlo; contraseña temporal si se la olvidó (las
    contraseñas siguen cifradas y no se pueden ver), corregir usuario/email/teléfono con motivo, baja/alta con
    motivo y cerrar todas sus sesiones.
  - **Clientes y pagos**: cuota mensual de cada empaque, registro y anulación de pagos, «pagado hasta» y estado
    (al día, por vencer, vencido, sin pagos), conexión y uso del sistema.
  - **Soporte**: pedidos pendientes de los clientes; el aviso llega a la campanita y también por email si está
    configurado el correo.
  - La gestión de clientes (Panel General) se activa con un botón, sin tocar el `.env`.

## [1.6.2] - 2026-10-03

### Corregido
- **Instalador**: al apartar las tablas viejas también se apartaban las de sesiones y caché del sistema anterior,
  que el sistema estaba usando, y desde ahí todas las páginas daban error («No se pudo completar la operación»).
  Ahora, al apartarlas, crea en el momento las tablas del sistema (o usa archivos si no puede). Los servidores que
  quedaron con ese error se arreglan solos al actualizar.
- Al terminar la instalación con la base vacía, el administrador quedaba afuera y tenía que volver a ingresar.
- **Instalador**: «Sesión expirada» (419) al tocar «Instalar sistema» con la página abierta desde antes de
  actualizar. Ahora vuelve al formulario con lo cargado y, si el navegador no guarda la sesión, explica la causa.
- Las páginas no se guardan en la caché de LiteSpeed (Hostinger): una página guardada traería una clave de
  formulario vieja y daría «Sesión expirada».

## [1.6.1] - 2026-10-02

### Corregido
- **Instalador**: decía «la base ya tiene tablas de otro sistema» aunque la base configurada estuviera vacía, porque
  contaba también las tablas de otras bases a las que tiene acceso el mismo usuario de MySQL. Ahora mira sólo la
  base configurada.
- Cambiar el `.env` (por ejemplo, para usar otra base de datos) ahora descarta la configuración en caché: antes,
  después de `config:cache`, el sistema seguía usando la base anterior.
- Se había subido al repositorio la marca interna `storage/framework/tables.ready`: en una base vacía podía dar
  error 500 al abrir el instalador. Se quitó y ahora la marca recuerda a qué base corresponde.

### Agregado
- **Instalador**: si la base tiene tablas de otro sistema, se pueden apartar desde el navegador (se renombran con el
  prefijo `viejo_`, no se borra nada). Pide la contraseña de la base para confirmar que lo hace el dueño del servidor.

## [1.6.0] - 2026-10-02

### Corregido
- **Instalador**: daba error 503 («En mantenimiento») si la base tenía tablas de otro sistema y fallaba en una base
  nueva al crear la empresa. Ahora crea las tablas solo si la base está vacía (sin consola/SSH), avisa claramente
  si la base es de otro sistema (sin tocarla) y muestra el motivo exacto de cualquier problema de conexión.
- El instalador ya no muestra datos de conexión de la base (servidor, nombre): son parte de la seguridad del servidor.
- Con la base vacía, sesiones y caché usan archivos hasta que existan sus tablas.

### Agregado
- **Zona horaria** elegible en el instalador y en Configuración → Regional (preparado para usar el sistema fuera de Argentina).
- Backups de MySQL **sin mysqldump** (volcado y restauración en PHP) cuando el hosting no permite ejecutar programas
  (Hostinger). Se puede forzar con `GALPON_BACKUP_DRIVER=php`.

## [1.5.0] - 2026-10-02

### Agregado
- **Todo se puede corregir sin borrar ni rehacer** (con motivo y en auditoría): controles de calidad, rechazos,
  cajones/pesos del romaneo (también dentro de una carga en armado), datos de transporte y comerciales de cargas
  cerradas o despachadas (el flete se re-imputa solo), lotes ya liquidados (se re-liquidan solos), cheques y
  movimientos de caja y de cuentas corrientes. Las facturas autorizadas por ARCA se corrigen con nota de crédito (ley).
- **Exportar a Excel/CSV** cada catálogo con los mismos encabezados de la importación, e **importar** agregando nuevos
  y **actualizando los existentes** (sólo las columnas que trae el archivo).
- Importación de clientes, productores, propietarios, proveedores, transportistas, camiones, choferes, embaladores,
  empleados, cuadrillas, destinos, variedades, tamaños, selecciones y envases.
- Datos completos: chofer (CUIL, dirección, nacimiento, categoría de licencia, contacto de emergencia), camión (año,
  chasis, seguro, VTV/RTO, habilitación SENASA), transportista y proveedor (dirección, IVA, CBU, alias), productor
  (RENSPA, IVA, CBU), embalador y empleado (CUIL, teléfono, dirección) y galpón (razón social, IIBB, inicio de actividades).
- Alertas de vencimiento de seguro, VTV y habilitación SENASA de los camiones.

## [1.4.0] - 2026-10-02

### Agregado
- **Actividad del personal** (Sistema → Actividad del personal): quién está conectado, días activos, ingresos,
  operaciones y cajones por empleado, uso por día y partes del sistema más usadas.
- **Romaneo del lote** imprimible: kilos empacados por variedad, calibre y selección, descarte por motivo,
  rinde contra los kilos recibidos y firmas.
- Instalación en **Hostinger** (docs/HOSTINGER.md), `scripts/actualizar-servidor.sh` para pasar cambios al hosting,
  `galpon:deploy-check` que verifica la instalación y scripts para correr el galpón y el Panel General en tu PC.

### Seguridad
- Nadie puede asignar un rol con permisos que él mismo no tiene.

## [1.3.0] - 2026-10-02

### Agregado
- **Empleados por sectores**: al crear un usuario, el dueño tilda los sectores que atiende (Etiquetas, Romaneo y
  producción, Ingreso de fruta, Calidad, Galpón y cámaras, Cargas, Facturación, Contabilidad y tesorería,
  Insumos, Reportes, Personal, Incidentes) o le da Acceso total. Sólo se reparten sectores que quien edita
  tiene y nadie cambia sus propios accesos.
- **Panel General del proveedor** (`GALPON_CENTRAL_MODE=true`): alta de muchos empaques clientes con su licencia,
  uso de cada uno (conexión, versión, usuarios activos, cajones, kilos, cargas, facturas, errores, gráfico de 30
  días) y bandeja de **soporte de clientes** para responder pedidos de cambios o mejoras.
- Cada empaque se conecta solo al Panel General (`GALPON_CENTRAL_URL` + `GALPON_LICENSE_KEY`): envía totales de
  uso, sube sus tickets y baja las respuestas (`galpon:central-sync`, cada 5 minutos, y botón «Sincronizar ahora»).

## [1.2.0] - 2026-10-02

### Agregado
- **Tesorería** (módulo nuevo): caja de efectivo con saldo anterior, ingresos, egresos, anulación con motivo y
  cierre con arqueo; **cuentas corrientes** de clientes, productores, transportistas, proveedores y empleados
  (cobros y pagos en efectivo, transferencia o cheque, ajustes, saldo inicial, resumen imprimible y Excel,
  consulta de saldos); **cheques** de terceros y propios (cartera, por vencer, por cobrar, depósito, cobro,
  endoso, rechazo con reimputación automática, e-cheq); **cotización del dólar** visible arriba en todas las
  pantallas y propuesta al facturar en dólares.
- Facturas autorizadas y notas de crédito se imputan solas en la cuenta del cliente; los fletes de cargas
  despachadas, en la del transportista. Botón «Imputar pendientes» para lo anterior.
- Compra de fruta: kilos recibidos y precio por kilo en el lote, liquidación al productor con tasa de asociación.
- Catálogos: selecciones (Extra, Elegido, Comercial), tipos de envase, cuadrillas y empleados.
- Cargas: acoplado, N° de guía, destino comercial, canal de comercialización, condición de venta y flete.
- Etiqueta de la caja con datos oficiales: empaque y CUIT, SENASA, Reg. Provincial de Empaque, RENSPA,
  Decreto-Ley 9.244/63, «Producción Argentina», calibre, selección, envase, kg aprox. y N° de empacador.
- Permisos nuevos (treasury.*, cash.manage, accounts.*, checks.manage, exchange.manage, lots.settle, staff.*);
  al actualizar, los roles del sistema reciben los permisos nuevos que les corresponden.

### Corregido
- La cotización de una factura en dólares aceptaba sólo punto decimal: ahora acepta «1.234,50».

## [1.1.0] - 2026-10-02

### Agregado
- Atajos de teclado tipo Excel en todo el sistema: teclas de función (F1 ayuda, F2 modo escaneo, F3 cargas,
  F4 pallets, F8 cajones, F9 tablero), «G y una letra» para ir a cada sección, Alt+N nuevo, Ctrl+S guardar,
  / o Ctrl+K buscar, Alt+M menú, Ctrl+Shift+E exportar a Excel.
- Navegación de listados con el teclado: T entra a la tabla, ↑ ↓ Inicio Fin recorren filas, Enter abre,
  Av Pág / Re Pág cambian de página.
- Pantalla «Ayuda y atajos» (menú Sistema y menú del usuario) y ventana rápida con la tecla «?». Sólo muestra
  los atajos de las secciones habilitadas para cada usuario. La tecla de función figura junto a cada ítem del menú.

## [1.0.0] - 2026-10-01

Primera versión completa del sistema.

### Núcleo
- Instalador inicial, usuarios, roles y permisos granulares (por rol y por usuario), módulos activables,
  configuración por pestañas, auditoría completa, sesiones activas con cierre remoto, multigalpón.
- Login por usuario, DNI, CUIT, código interno o email; recuperación de contraseña (enlace por email o
  contraseña temporal asignada por un administrador con cambio obligatorio).
- Centro de notificaciones, alertas automáticas, búsqueda global con apertura directa por código.
- Interfaz responsive (PC, tablet, celular) con modo claro/oscuro.

### Operación
- Catálogos e importación desde Excel/CSV con vista previa de errores.
- Lotes, pallets, cajones, etiquetas con código de barras, trazabilidad completa.
- Modo escaneo y kiosco con autorización de supervisor, duplicados e idempotencia; paradas de línea.
- Calidad, rechazos y merma; ubicaciones con mapa editable; cámaras frigoríficas; insumos; mantenimiento.
- Cargas concurrentes, cierre, checklist, despacho, remito PDF con QR público, firma de entrega, documentos.
- Facturación A/B/C con ARCA (simulación, homologación, producción) y QR fiscal.
- Incidentes con responsable, vínculo a cajón/pallet/carga e historial.

### Gestión
- Tablero en vivo, reportes exportables (Excel/CSV/PDF), estadísticas con comparaciones, cierre diario,
  costos y rentabilidad, portal de clientes y propietarios.

### Administración técnica
- Backups automáticos verificados (SHA-256), restauración con doble confirmación y backup previo.
- Panel de desarrollador (estado, errores, logs enmascarados, licencias), tickets de soporte.
- API REST v1 con tokens por habilidad (consultas, balanzas, sensores).
- Comandos: `galpon:backup`, `galpon:check-alerts`, `galpon:create-admin`, `galpon:seed-performance`.
