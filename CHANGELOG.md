# Historial de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Versionado semántico.

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
