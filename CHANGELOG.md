# Historial de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Versionado semántico.

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
