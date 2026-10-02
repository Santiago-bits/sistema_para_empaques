# Facturación electrónica con ARCA (ex AFIP)

## Modos

| Modo | Qué hace | Cuándo usarlo |
|---|---|---|
| **Simulación** (por defecto) | No se conecta a ARCA. Devuelve un CAE ficticio que empieza con `99` | Capacitación, pruebas, demo |
| **Homologación** | Se conecta al entorno de pruebas de ARCA (WSAA + WSFEv1) con un certificado de testing | Validar la configuración antes de facturar de verdad |
| **Producción** | Comprobantes reales con validez fiscal | Operación diaria |

Por seguridad, el modo **producción sólo funciona si `APP_ENV=production`**: en una PC de pruebas o de
desarrollo es imposible emitir un comprobante real por error.

## Flujo de un comprobante

```
Borrador ──(Enviar a ARCA)──▶ Pendiente ──▶ Autorizado (CAE + vencimiento)
   ▲                                    └─▶ Rechazado (motivo de ARCA) ──▶ corregir y reenviar
```

- Los totales (neto, IVA por alícuota, total) los calcula **el servidor**, nunca el navegador.
- El número definitivo lo asigna la autorización, con bloqueo para que dos envíos simultáneos no
  tomen el mismo número.
- Cada intento queda registrado (ARCA → historial) **sin** guardar certificados ni tokens.
- El PDF del comprobante autorizado incluye el **QR fiscal** (RG 4892).
- Tipo de comprobante según la condición del emisor (Configuración → ARCA): Responsable Inscripto →
  Factura A (cliente RI) o B (consumidor final / exento / monotributo); Monotributo → Factura C.

## Configurar homologación / producción

1. **Clave fiscal** del CUIT emisor con nivel 3 o superior.
2. Generar la clave privada y el pedido de certificado (en el servidor, con OpenSSL de XAMPP):
   ```powershell
   cd C:\galpon-certificados
   C:\xampp\apache\bin\openssl.exe genrsa -out privada.key 2048
   C:\xampp\apache\bin\openssl.exe req -new -key privada.key -subj "/C=AR/O=EMPRESA/CN=galpon/serialNumber=CUIT 30XXXXXXXXX" -out pedido.csr
   ```
3. En ARCA: **Administración de certificados digitales** (homologación: «WSASS – Autogestión
   Certificados Homologación») → subir `pedido.csr` → descargar el certificado `.crt`.
4. **Administrador de relaciones de clave fiscal** → asociar el certificado al servicio
   **«Facturación electrónica» (wsfe)**.
5. Dar de alta el **punto de venta** «Web Services» en ARCA y cargarlo en Configuración → ARCA.
6. En el `.env` del servidor (nunca en el repositorio):
   ```ini
   ARCA_CUIT=30XXXXXXXXX
   ARCA_CERT_PATH=C:\galpon-certificados\certificado.crt
   ARCA_KEY_PATH=C:\galpon-certificados\privada.key
   ARCA_KEY_PASSPHRASE=
   ```
7. `php artisan config:cache`, elegir el modo en Configuración → ARCA y probar con un comprobante.

> Guardá la carpeta de certificados **fuera** de la carpeta del sistema y con acceso restringido.
> Los certificados vencen: renovalos antes de la fecha de vencimiento.

## Errores frecuentes

| Mensaje | Qué revisar |
|---|---|
| Certificado o clave inválidos | Rutas del `.env`, que el `.crt` corresponda a esa `.key` |
| Computador no autorizado a acceder al servicio | Falta asociar el certificado a «wsfe» (paso 4) |
| Punto de venta inválido | Alta del punto de venta tipo Web Services y número en Configuración |
| Fecha fuera de rango | La fecha del comprobante admite ±5 días (productos) respecto de hoy |
| Sin respuesta / tiempo agotado | Conexión a internet del servidor; el comprobante queda pendiente y se puede reintentar |
