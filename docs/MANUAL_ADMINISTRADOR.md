# Manual del administrador

## Roles y permisos

| Rol | Uso típico |
|---|---|
| Super Administrador / Desarrollador | Todo, incluido restaurar backups, logs técnicos y licencias |
| Administrador del galpón | Todo el negocio (no restaura backups ni ve logs técnicos) |
| Supervisor | Producción, calidad, cargas, reportes, cierre diario, autorizaciones de peso |
| Operador de ingreso | Pallets, lotes, modo escaneo, ubicaciones |
| Embalador | «Mi producción» |
| Operador de cargas | Armado, cierre y despacho de cargas, remitos, documentos |
| Control de calidad | Controles, rechazos, cámaras |
| Facturación / Administración | Comprobantes, ARCA, costos, insumos, reportes |
| Cliente / Propietario | Sólo el portal con su propia mercadería |

- **Roles y permisos**: se pueden crear roles nuevos y elegir permiso por permiso.
- **Permisos individuales**: desde la ficha de un usuario se puede **otorgar o quitar** un permiso puntual
  sin cambiar su rol.
- Un administrador **no** puede modificar a un Super Administrador.

## Usuarios

Administración → Usuarios. Datos: usuario, DNI, CUIT, código interno, email (opcional), rol, galpones,
embalador vinculado, cliente/propietario vinculado (portal) y **modo kiosco**.

- **Dar de baja**: cambiar el estado a Inactivo. Sus sesiones abiertas se cierran solas en el siguiente clic.
  Nunca se borran usuarios: su historial queda.
- **Restablecer contraseña**: en la ficha del usuario. Genera una contraseña temporal que se muestra
  **una sola vez** (dásela en persona), cierra sus sesiones y le exige cambiarla al ingresar.
- **Sesiones activas**: ver quién está conectado, desde qué IP, y cerrar una sesión a distancia.

## Módulos

Administración → Módulos. Un módulo apagado desaparece del menú, sus pantallas dan «no encontrado» y
sus permisos dejan de valer. Los datos **no se borran**: al volver a encenderlo, todo sigue ahí.
Núcleo y Catálogos no se pueden apagar.

## Configuración

| Pestaña | Qué se configura |
|---|---|
| Empresa | Nombre, CUIT, domicilio, logo (aparece en remitos, facturas y reportes) |
| Regional | Moneda, formato |
| Producción | Rango de peso permitido, lote obligatorio, balanza, objetivos diario/semanal/mensual |
| Campos | Qué campos son obligatorios u opcionales en cada pantalla |
| Numeraciones | Prefijos y próximos números (cajones, pallets, lotes, cargas, remitos, tickets, incidentes) |
| Alertas | Qué alertas están activas y sus umbrales (horas de carga pendiente, días de vencimiento…) |
| Seguridad y red | Con qué identificadores se ingresa, restricción por IP de la LAN |
| Backups | Diario, semanal, días de retención |
| ARCA | Modo (simulación / homologación / producción), punto de venta, condición del emisor |

## Catálogos e importación

Productores, propietarios, clientes y sus destinos, proveedores, transportistas, camiones, choferes
(con vencimiento de licencia), variedades, tamaños, turnos, líneas, motivos, objetivos y temporadas.

**Importar datos** (Administración → Importar datos): subir un Excel/CSV, el sistema muestra una vista
previa con los errores fila por fila **antes** de guardar. Nada se importa hasta confirmar.

## Cierre diario

Reportes → Cierre diario. Muestra el resumen del día (cajones, kilos, merma, cargas, facturas,
incidentes, por variedad y por embalador). Al **cerrar**, ese resumen queda congelado: si después se
corrige algo, la corrección queda en auditoría pero el cierre muestra lo que había. Reabrir un cierre
requiere permiso especial y un motivo.

## Reportes y estadísticas

- **Reportes**: producción, embaladores, variedades, tamaños, productores, cargas, merma, ejecutivo…
  Se exportan a **Excel, CSV o PDF** respetando exactamente los filtros aplicados. Los reportes grandes
  se generan en segundo plano y avisan por la campanita cuando están listos.
- **Estadísticas**: hoy, semana, mes, temporada o rango, con comparaciones contra períodos anteriores.

## Costos y rentabilidad

Costos → Nuevo costo: categoría (insumos, transporte, mano de obra, mantenimiento, otros), importe en
pesos (se puede escribir `125.000,50`), fecha y, opcionalmente, la carga a la que corresponde.
Con el permiso «Ver ganancias», la pantalla muestra **ingresos facturados** (sólo comprobantes autorizados
por ARCA, en pesos según su cotización; las notas de crédito restan), costos, resultado, margen y costo
por kilo procesado.

## Auditoría

Administración → Auditoría. Todo cambio importante queda registrado con usuario, fecha, IP, valor
anterior y valor nuevo: altas, modificaciones, anulaciones, cambios de estado, ingresos fallidos,
descargas, cambios de permisos, backups, restauraciones. Las contraseñas y tokens **nunca** se registran.

## Alertas

Se evalúan solas cada 5 minutos (requiere la tarea programada, ver instalación). Quien tiene «Configurar
y resolver alertas» puede resolverlas a mano o pedir una evaluación inmediata («Evaluar ahora»). Las
alertas de advertencia y críticas se avisan por la campanita (y por WhatsApp si la integración está activa).

## Portal de clientes y propietarios

1. Activar el módulo **Portal de clientes**.
2. Crear un usuario con rol **Cliente / Propietario** y vincularlo a un cliente y/o propietario.
3. El cliente ve sus cargas, remitos y facturas autorizadas (con PDF); el propietario ve su mercadería en
   galpón. Un usuario sin vínculo no ve nada.

## Backups

Ver [BACKUPS.md](BACKUPS.md).

## Tokens de API

Administración → Tokens de API, para balanzas, sensores e integraciones. Ver [API.md](API.md).
