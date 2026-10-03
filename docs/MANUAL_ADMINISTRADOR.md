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

## Administración general (`/administradorgeneral`)

Sólo para el **Super Administrador** (el dueño del sistema). Se entra desde el menú Sistema → Administración
general o escribiendo `tu-dominio/administradorgeneral`. Para cualquier otro usuario esa dirección no existe.

- **Invisible para el galpón**: el dueño del galpón y su personal no te ven en ningún lado (usuarios, actividad,
  conectados, historial de cambios, búsquedas). Si algo lo hiciste vos, ellos ven «Soporte del sistema».
- **Cómo entrar**: primero ingresá al sistema con tu usuario de Super Administrador (si escribís la dirección sin
  haber ingresado, te lleva al ingreso y después vuelve sola). Si te olvidaste el usuario o la contraseña del
  Super Administrador, en la pantalla de ingreso tocá «¿Sos el dueño y no recordás el usuario o la contraseña?»:
  con la contraseña de la base de datos (hPanel → Bases de datos) elegís una nueva y te muestra tu usuario.
- **Seguridad**: pide volver a escribir tu contraseña cada 15 minutos (aunque alguien encuentre tu sesión abierta,
  no entra). «Salir de la administración» la vuelve a pedir. Cada ingreso, cada intento fallido y cada ficha
  consultada quedan en la auditoría.
- **Contraseñas**: se guardan **cifradas** (bcrypt). Nadie puede verlas, ni vos ni quien tenga acceso a la base.
  Si alguien se olvida la suya, en su ficha tocás **Generar contraseña temporal**: se muestra una sola vez,
  se cierran sus sesiones y el sistema le pide cambiarla apenas ingresa.
- **Usuarios**: todos los datos de cada uno (nombre, usuario, email, teléfono, DNI, CUIT, rol, sectores, último
  ingreso e IP, sesiones abiertas, actividad). Desde la ficha: corregir usuario/email/teléfono (con motivo),
  dar de baja o reactivar (con motivo) y cerrar todas sus sesiones.
- **Clientes y pagos** (al tocar **Activar gestión de clientes**): cada empaque cliente con su cuota mensual,
  **hasta cuándo pagó** (Al día / Por vencer / Vencido / Sin pagos), si se conecta y cuánto usa el sistema
  (usuarios activos, cajones en 30 días). Registrar un pago suma los meses a continuación de lo ya pagado;
  un pago mal cargado se **anula** con motivo y la cobertura vuelve atrás sola. El estado de pago es
  informativo: nunca bloquea los datos del cliente.
- **Soporte**: los pedidos de los clientes llegan a la campanita y, si tenés email cargado y el envío de correos
  configurado, también por email. El resumen muestra los pendientes.

## Usuarios

Administración → Usuarios. Datos: usuario, DNI, CUIT, código interno, email (opcional), galpones,
embalador vinculado, cliente/propietario vinculado (portal) y **modo kiosco**.

**¿A qué partes del sistema puede entrar?** Al crear o editar un empleado elegís:

- **Por sectores** (recomendado): tildás lo que atiende — Etiquetas, Romaneo y producción, Ingreso de fruta,
  Calidad, Galpón y cámaras, Cargas y despacho, Facturación, Contabilidad y tesorería, Insumos y mantenimiento,
  Reportes, Personal y catálogos, Incidentes. Sólo ve eso en el menú (más el tablero, las alertas y soporte).
- **Acceso total**: rol Administrador del galpón, ve y configura todo.
- **Rol predefinido**: los roles armados (Operador de cargas, Calidad, etc.) para casos avanzados.

Sólo podés repartir sectores que vos mismo tenés, y nadie puede cambiarse sus propios accesos. Para un ajuste
fino (un permiso suelto), usá «Permisos» en la ficha del usuario.

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

Además: selecciones, tipos de envase, cuadrillas, empleados y embaladores. Cada ficha guarda los datos de
todos los días (CUIL, dirección, CBU/alias, RENSPA, seguro y VTV del camión, licencia y contacto de emergencia
del chofer…). Los vencimientos de licencia, seguro, VTV y habilitación SENASA generan alertas.

**Exportar**: en el listado de cada catálogo, botón **Excel** (o CSV). Respeta la búsqueda y los filtros.

**Importar datos** (botón **Importar** del catálogo o Sistema → Importar datos): subir un Excel/CSV, el sistema
muestra una vista previa con los errores fila por fila **antes** de guardar. Nada se importa hasta confirmar.
Dos modos:

- **Agregar nuevos y actualizar los existentes**: si el código, CUIT, DNI o patente ya existe, actualiza ese
  registro con las columnas que trae el archivo (una planilla puede traer sólo DNI + teléfono).
- **Sólo agregar nuevos**: los que ya existen se informan como duplicados.

Lo más práctico: **exportá → corregí en Excel → importá** con «actualizar los existentes». Los encabezados de
la exportación son los mismos que pide la importación.

## Corregir datos cargados

Nada hay que borrar y volver a hacer. Cada corrección pide un **motivo** y queda en la auditoría (quién, cuándo,
valor anterior y nuevo):

| Qué | Dónde |
|---|---|
| Peso, variedad, tamaño o embalador de un cajón (romaneo) | Producción → Registros → **Corregir**, o la ficha del cajón. También dentro de una carga en armado (los totales se recalculan) |
| Control de calidad / rechazo | Su ficha o el listado → **Corregir** |
| Camión, chofer, acoplado, guía, datos comerciales o flete de una carga cerrada/despachada | Ficha de la carga → **Corregir datos** (el flete se corrige solo en la cuenta del transportista) |
| Kilos, precio o productor de un lote ya liquidado | Editar el lote: se vuelve a liquidar solo |
| Cobro, pago o ajuste de cuenta corriente / movimiento de caja | Botón **Corregir** en la fila |
| Datos o importe de un cheque | Ficha del cheque → **Corregir datos** |

Excepción: una **factura autorizada por ARCA** no se puede modificar por ley; se corrige con una nota de crédito.

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
