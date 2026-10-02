# Historial de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Versionado semántico.

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
