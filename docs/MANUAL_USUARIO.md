# Manual de usuario — Operadores

Cada usuario ve en el menú sólo lo que su rol permite. Si falta una opción, pedila al administrador.

## Ingresar

- Abrí el navegador en la dirección del sistema (por ejemplo `http://192.168.1.100`).
- Ingresá con tu **usuario, DNI, CUIT, código interno o email** (según cómo lo configuró el galpón) y tu contraseña.
- **¿Olvidaste tu contraseña?** Tocá el enlace debajo de «Contraseña». Si tenés email cargado te llega un
  enlace; si no, el administrador recibe un aviso y te da una contraseña temporal. Al entrar con la
  temporal el sistema te pide elegir una nueva.
- Si aparece una franja roja **«Sin conexión con el servidor»**, lo que hagas en ese momento **no se
  guarda**. Esperá a que vuelva la conexión.

Botón ☰ (arriba a la izquierda) abre el menú en celular/tablet.

## Instalar el sistema como app

- **Computadora (Chrome o Edge):** botón verde «Instalar app» arriba a la derecha (o el ícono de instalar en la
  barra de direcciones). Queda un ícono en el escritorio y en el menú Inicio y se abre en su propia ventana.
- **Celular Android:** en Chrome, menú ⋮ → «Instalar app».
- **iPhone:** en Safari, botón Compartir → «Agregar a inicio».

La app siempre carga desde el servidor: cuando se sube una versión nueva, se ve la próxima vez que se abre.
Necesita la dirección con `https://` (en Hostinger ya viene) o la red del galpón.

## Atajos de teclado

Apretá **?** en cualquier pantalla para ver todos los atajos (también en **Sistema → Ayuda y atajos**).
Los más usados:

| Tecla | Qué hace |
|---|---|
| **F1** / **F2** / **F3** / **F4** | Ayuda · Modo escaneo · Cargas · Pallets (F8 cajones, F9 tablero) |
| **G** y luego una letra | Ir a una sección (G C cargas, G J cajones, G F facturación, G T reportes…) |
| **/** o **Ctrl+K** | Buscador |
| **Alt+N** | Nuevo registro · **Ctrl+S** guardar · **Ctrl+Shift+E** exportar a Excel |
| **T** | Entrar a la tabla: **↑ ↓** filas, **Enter** abre, **Av Pág / Re Pág** cambian de página |
| **Esc** | Cerrar ventanas o salir del campo para usar las teclas de una letra |

Sólo aparecen los atajos de las secciones que tu usuario puede abrir. En el modo escaneo y en calidad,
F2/F3/F4 mantienen su función propia de esa pantalla.

## Cómo escanear códigos

Nunca hace falta tipear un código número por número:

- **Lector USB o Bluetooth** (como los de supermercado): apuntá y apretá el gatillo. El código se carga
  solo y la pantalla avanza al siguiente paso.
- **Cámara del celular o tablet**: tocá el botón **Cámara** (ícono de cámara) al lado del campo.
  Apuntá al código de barras o QR de la etiqueta: suena, vibra y se completa solo. Si la cámara en vivo no
  está disponible, tocá **«Sacar foto del código»**: sacás la foto y el sistema lee el código de la imagen.
- En el **armado de cargas** la cámara queda abierta: leés un cajón tras otro y tocás **Terminar** al final.
- Con poca luz usá el botón **Linterna** (si el celular la tiene).

## Modo escaneo (producción)

Pantalla pensada para usar 100 % con el lector de códigos, sin mouse.

1. **Cajón**: escaneá la etiqueta del cajón.
2. **Embalador**: escaneá la credencial del embalador. Con **Fijar** tildado queda puesto para los siguientes.
3. **Peso**: lo toma la balanza o se escribe (acepta `18,5` o `18.5`). Enter.
4. **Variedad / Tamaño / Lote**: con **Fijar** quedan seleccionados para toda la tanda.
5. Se escucha un sonido y la pantalla se pone **verde**: registrado. **Rojo**: hay un error (se explica
   en pantalla y el cursor vuelve al campo a corregir). **Naranja**: requiere atención.

Reglas que el sistema controla solo:

- Un cajón no se puede registrar dos veces (aviso de **duplicado**).
- Si el peso está fuera del rango permitido, hace falta que un **supervisor** autorice con su usuario y
  contraseña, indicando el motivo. Queda registrado quién autorizó.
- Si se corta la red justo al guardar y se reintenta, no se duplica (cada envío tiene una clave única).

**Modo kiosco**: pantalla completa sin menú para las PCs fijas de producción. Los usuarios marcados como
kiosco sólo ven esta pantalla.

**Mi producción** (embaladores): cajones y kilos propios del día y del período, por variedad.

**Paradas**: registrá cuándo se detuvo una línea y el motivo; cerrala cuando se retoma.

## Ingreso de mercadería

- **Lotes**: productor, propietario, variedad, fecha, origen.
- **Pallets**: se asocian a un lote; se imprime la etiqueta con código de barras.
- **Cajones**: listado con filtros por estado, variedad, embalador y fecha. Desde la ficha se ve toda su
  **trazabilidad** y su historial. «Anular» pide motivo y queda auditado.

## Calidad

- **Controles**: buscá el cajón o pallet, cargá el resultado (aprobado / rechazado) y observaciones.
- **Rechazos y merma**: kilos descartados por motivo; alimentan los reportes de merma.

## Galpón

- **Mapa**: estado de ocupación de cada sector (verde < 60 %, amarillo 60–90 %, rojo > 90 %).
- **Mover**: escaneá el pallet y la ubicación destino. Cada movimiento queda en el historial.
- **Cámaras frigoríficas**: lecturas de temperatura/humedad (manuales o de sensores). Una lectura fuera
  de rango genera una alerta crítica.
- **Insumos**: ingresos, egresos y ajustes de stock; aviso de stock bajo.

## Cargas y despacho

1. **Nueva carga**: cliente, destino (sólo los del cliente), camión y chofer.
2. **Armado**: escaneá cajones o pallets completos. El contador se actualiza al instante. Si otro operario
   ya cargó ese cajón en otra carga, el sistema lo rechaza: **un cajón nunca queda en dos cargas**.
   Para quitar, escaneá con la opción «Quitar».
3. **Cerrar**: revisá el resumen (cajones, kilos, por variedad y tamaño) y confirmá.
4. **Remito**: emitilo desde la carga cerrada. El PDF tiene un **QR** que el cliente puede escanear para
   consultar el remito sin usuario.
5. **Checklist de despacho**: tildá cada control (precintos, documentación, temperatura, etc.).
6. **Despachar**: sólo con el checklist completo y el remito emitido.
7. **Entrega**: el receptor firma en pantalla (celular/tablet), con nombre y DNI.

Reabrir una carga cerrada pide motivo y no se permite si ya tiene remito emitido o factura autorizada.

## Tesorería

- **Caja**: abrila al empezar el día (propone lo contado en el último cierre). Registrá ingresos y egresos en
  efectivo. Al cerrar, contá el efectivo: si hay diferencia, explicá el motivo. Atajo **G B**.
- **Cuentas corrientes** (**G M**): elegí cliente, productor, transportista, proveedor o empleado. Saldo positivo
  = nos debe; negativo = le debemos. «Registrar cobro o pago» con efectivo (entra/sale de la caja), transferencia
  o cheque. Para pagar con un cheque de cartera, elegí «Endosar». Las facturas y los fletes se cargan solos.
- **Cheques** (**G V**): vistas En cartera, Por vencer, Por cobrar, Emitidos, Endosados y Rechazados. Desde la
  ficha: depositar, marcar cobrado, rechazado (el importe vuelve a la deuda de quien lo entregó) o anular.
- **Cotización del dólar** (**G O**): cargala cada día; se ve arriba a la derecha y se propone al facturar en US$.
- **Compra de fruta**: en el lote cargá kilos recibidos y precio por kilo y tocá «Liquidar al productor».
- Nada se borra: los movimientos se **anulan con motivo** y quedan en la auditoría.

## Incidentes

Registrá problemas (cajón faltante, error de peso, transporte, documentación, producto dañado). Podés
vincularlos a un cajón, pallet o carga escaneando su código. Los de prioridad alta o crítica generan
una alerta. Para marcarlo resuelto hay que contar cómo se resolvió.

## Notificaciones y alertas

- **Campanita**: avisos personales (pedidos de contraseña, exportaciones listas, respuestas de soporte).
- **Alertas**: problemas del galpón (stock bajo, cargas demoradas, temperatura, vencimientos). Se
  resuelven solas cuando la situación se normaliza.

## Soporte

Si algo no funciona: **Soporte → Nuevo ticket**. Si apareció un código `ERR-…`, copialo en la
descripción: con ese código el técnico encuentra el detalle exacto del error.

## Búsqueda global

Arriba de todo: escribí o **escaneá** un código. Si coincide exactamente con un cajón, pallet, lote,
carga o remito, abre directamente su ficha. También busca productores, clientes (por nombre o CUIT),
patentes, choferes (DNI), embaladores y usuarios.
