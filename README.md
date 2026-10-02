# Sistema de Gestión para Galpón de Empaque

Sistema web multiusuario para galpones de empaque de fruta: ingreso de pallets y lotes, producción
por escaneo, calidad, ubicaciones, cargas, remitos con QR, facturación electrónica con ARCA,
reportes, auditoría y backups. Funciona en la **red local (LAN)** del galpón: un servidor y todas
las PCs, tablets y celulares acceden desde el navegador.

> Prioridades: **integridad de datos · trazabilidad · concurrencia · seguridad · velocidad de operación**.

## Qué hace

| Área | Funciones principales |
|---|---|
| **Producción** | Modo escaneo con lector de códigos (cajón → embalador → peso), modo kiosco, autorización de supervisor, detección de duplicados, idempotencia, paradas de línea, «Mi producción» por embalador |
| **Ingreso** | Pallets, lotes, productores y propietarios; etiquetas con código de barras |
| **Calidad** | Controles, rechazos y merma por motivo |
| **Galpón** | Mapa editable, ubicaciones, movimientos, cámaras frigoríficas (sensores), insumos, mantenimiento |
| **Logística** | Armado de cargas concurrente (dos operarios no pueden cargar el mismo cajón), cierre con checklist, despacho, remito PDF con QR público y firma de entrega, documentos adjuntos |
| **Facturación** | Comprobantes A/B/C, totales calculados en el servidor, CAE de ARCA (modo simulación y producción), PDF con QR fiscal RG 4892 |
| **Gestión** | Tablero en vivo, reportes exportables (Excel/CSV/PDF), estadísticas, cierre diario, costos y rentabilidad, incidentes, alertas automáticas, búsqueda global |
| **Trazabilidad** | Productor → Lote → Pallet → Cajón → Carga → Remito → Factura, con historial de estados |
| **Administración** | Usuarios, roles y permisos granulares, módulos activables, configuración, auditoría completa, sesiones activas, backups verificados y restauración, recuperación de contraseña |
| **Integraciones** | Portal de clientes/propietarios, API REST con tokens (balanzas, sensores), panel de desarrollador, tickets de soporte |

## Tecnología

Laravel 12 · PHP 8.2+ · MySQL 8 / MariaDB 10.4+ · Blade + Tailwind CSS 4 + Alpine.js · Chart.js · Sanctum.
Interfaz en español (Argentina), modo claro/oscuro y diseño responsive (PC, tablet y celular).

## Instalación rápida (desarrollo)

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # estructura + roles, permisos y configuración base
php artisan db:seed --class=DemoSeeder   # opcional: datos de demostración (NUNCA en producción)
php artisan serve
```

Abrí `http://127.0.0.1:8000`. Sin usuarios cargados, el sistema abre el **instalador** (`/instalar`)
para crear la empresa y el super administrador. Con los datos demo, los usuarios son `admin.demo`,
`ingreso`, `cargas`, `calidad`, `facturacion`, `supervisor`, `embalador`, `kiosco` y `cliente`
(contraseña: variable `DEMO_PASSWORD`, por defecto `demo1234`).

**Instalación en el galpón (Windows + red LAN): ver [docs/INSTALACION.md](docs/INSTALACION.md).**

## Documentación

| Documento | Para quién |
|---|---|
| [docs/INSTALACION.md](docs/INSTALACION.md) | Técnico: servidor Windows/XAMPP, red LAN, tareas programadas, actualización |
| [docs/MANUAL_USUARIO.md](docs/MANUAL_USUARIO.md) | Operadores: escaneo, ingreso, calidad, cargas, remitos |
| [docs/MANUAL_ADMINISTRADOR.md](docs/MANUAL_ADMINISTRADOR.md) | Administración: usuarios, permisos, módulos, configuración, cierre, costos |
| [docs/BACKUPS.md](docs/BACKUPS.md) | Backups automáticos, verificación y restauración |
| [docs/ARCA.md](docs/ARCA.md) | Facturación electrónica: certificados, homologación y producción |
| [docs/API.md](docs/API.md) | Integraciones: tokens, balanzas, sensores, endpoints |
| [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Desarrolladores: diagrama de datos, flujos, concurrencia, seguridad |
| [CLAUDE.md](CLAUDE.md) | Reglas de desarrollo del proyecto |
| [CHANGELOG.md](CHANGELOG.md) | Historial de versiones |

## Tests

```bash
php artisan test
```

Más de 170 tests de integración: permisos (403), módulos desactivados (404), validaciones,
concurrencia (dos intentos sobre el mismo registro → sólo uno gana), idempotencia, aislamiento de
datos del portal, API, backups/restauración y un recorrido automático de todas las pantallas con
cada rol.

## Comandos útiles

| Comando | Uso |
|---|---|
| `php artisan schedule:run` | Tareas programadas (configurarlo cada minuto, ver instalación) |
| `php artisan galpon:backup` | Backup manual de la base |
| `php artisan galpon:check-alerts` | Evaluar alertas ahora |
| `php artisan galpon:create-admin` | Crear un super administrador si se perdió el acceso |
| `php artisan galpon:seed-performance` | Generar volumen para pruebas de rendimiento (sólo desarrollo) |

## Licencia

Software propietario. Todos los derechos reservados.
