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

Atajos: tecla **/** abre el buscador. Botón ☰ (arriba a la izquierda) abre el menú en celular/tablet.

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
