# Sistema de Gestión para Galpón de Empaque — Guía para desarrolladores

Laravel 12 · PHP 8.2 · MySQL/MariaDB · Blade + Tailwind 4 + Alpine.js · Chart.js · Sanctum (API).
Idioma de la interfaz: **español (Argentina)**. Código (clases, columnas, rutas internas) en inglés; URLs visibles y textos en español.

Prioridad absoluta: **integridad de datos + trazabilidad + concurrencia + seguridad + velocidad de operación**.

## Comandos

```bash
composer install && npm install && npm run build
php artisan migrate --seed                 # esquema + datos base (SystemSeeder)
php artisan db:seed --class=DemoSeeder     # datos de demostración (NUNCA en producción)
php artisan test                            # tests (SQLite en memoria, ver .env.testing)
php artisan serve                           # http://127.0.0.1:8000
```

## Arquitectura

```
app/
├── Enums/            Estados con transiciones válidas (CrateStatus, PalletStatus, LoadStatus, ...)
├── Exceptions/       BusinessException (mensaje al usuario), ConcurrencyException, InvalidTransitionException
├── Http/Controllers/ Controladores PEQUEÑOS: validan, autorizan y delegan a Services
├── Http/Requests/    Form Requests (validación backend obligatoria)
├── Http/Middleware/  module:, lan:, active, kiosk, SecurityHeaders, RedirectIfNotInstalled
├── Models/           Eloquent + Concerns (Auditable, HasStateHistory, BelongsToWarehouse)
├── Policies/         Autorización por registro
├── Services/         Lógica de negocio (transacciones, locks, auditoría)
├── Support/          Helpers (Menu, PermissionRegistry, CurrentWarehouse, ErrorReporter)
├── Events/ Listeners/ Jobs/ Notifications/
config/permissions.php  Catálogo de permisos y roles por defecto
config/menu.php         Menú lateral (ruta + módulo + permiso)
config/galpon.php       Versión, ARCA, dispositivos, WhatsApp
routes/web.php          Carga routes/public/*.php (sin login) y routes/modules/*.php (con login)
```

## Reglas obligatorias

1. **Rutas**: cada módulo tiene su archivo `routes/modules/<modulo>.php`. Se cargan automáticamente dentro del grupo
   `['auth', 'active', 'kiosk']`. Cada grupo de rutas aplica `->middleware(['module:<key>', 'can:<permiso>'])`.
   Los nombres de ruta deben coincidir con los de `config/menu.php`.
2. **Permisos**: usar siempre los slugs de `config/permissions.php` (`$user->can('crates.view')`, `@can`, `can:` middleware).
   `Gate::before` bloquea permisos de módulos desactivados y concede todo al super admin. No inventar permisos nuevos sin
   agregarlos a `config/permissions.php`.
3. **Controladores pequeños**: nada de lógica de negocio en el controlador. Usar Services (`app/Services`).
4. **Transacciones**: toda operación crítica dentro de `DB::transaction()`. Para asignaciones concurrentes usar
   UPDATE condicional (`where('status', ...)->where('version', ...)->update(...)` y verificar filas afectadas == 1)
   o `lockForUpdate()`. Nunca "leer, verificar en PHP y luego escribir" sin lock.
5. **Estados**: cambiar estados SÓLO con `StateTransitionService::transition($model, Enum::X, $notas, $extra)`.
   Valida la transición, es atómico (bloqueo optimista con `version`), guarda historial y audita.
6. **Auditoría**: los modelos con `Auditable` registran create/update/delete automáticamente con valor anterior/nuevo.
   Para acciones de negocio usar `app(AuditService::class)->log('accion', $modelo, $old, $new, 'descripción', 'motivo')`.
   Nunca registrar contraseñas, tokens ni credenciales.
7. **Numeración**: `app(SequenceService::class)->next('crate')` (claves: pallet, crate, lot, load, remito, invoice, ticket, incident).
8. **Errores de negocio**: `throw new BusinessException('Mensaje claro para el usuario')`. Se muestra como flash
   (web) o JSON 422 (`{message}`) automáticamente. Errores técnicos muestran un código `ERR-AAAAMMDD-XXXXX`.
9. **Eliminación lógica**: entidades importantes usan SoftDeletes. Preferir "anular" (estado) antes que borrar.
10. **Paginación**: siempre `->paginate($this->perPage($request))->withQueryString()`; filtros por GET.
    Exportaciones respetan exactamente los filtros activos.
11. **Rendimiento**: eager loading (`with()`), índices existentes, nada de cargar miles de filas en memoria
    (usar `cursor()`/`lazy()` en exportaciones).
12. **Configuración**: `setting('production.weight_min')`, `module_enabled('loads')`. Helpers de formato:
    `kg()`, `num()`, `pct()`, `money()`, `fdate($fecha, true)`.
13. **Multigalpón**: los modelos operativos usan `BelongsToWarehouse` (completa `warehouse_id` solo).

## Interfaz (Blade)

Layout: `<x-layouts.app title="...">` (admin), `<x-layouts.kiosk>` (producción, sin menú), `<x-layouts.guest>`.

Componentes disponibles (`resources/views/components`):
`x-page-header` (title, subtitle, back, slot actions) · `x-panel` (title, slot actions, padding) · `x-stat` (label, value,
icon, hint, color, href, delta) · `x-table` (+ slot footer para `{{ $items->links() }}`) · `x-empty` · `x-filters`
(exports) · `x-input` · `x-select` (options [valor => etiqueta], placeholder) · `x-textarea` · `x-checkbox` · `x-field` ·
`x-badge` (color) · `x-status` (enum con label()/color()) · `x-active-badge` · `x-modal` · `x-dl` (items) ·
`x-timeline` (events) · `x-progress` · `x-icon` (name) · `x-flash`.

Clases CSS: `btn btn-primary|btn-secondary|btn-danger|btn-warning|btn-ghost btn-sm|btn-lg`, `panel`, `form-input`,
`form-label`, `table`, `num` (números alineados a la derecha), `code` (códigos en monoespaciado), `link`.

- Los códigos (cajón, pallet, carga, remito, patente) se muestran con clase `code`.
- Formularios críticos: `<form x-data x-confirm="¿Confirmás...?">` (doble confirmación).
- JS: `window.api(url, {method, body})` (CSRF + errores uniformes), `window.newIdempotencyKey()`, `window.sounds.success()`.
- **NUNCA** usar `@json([...])` con arrays multilínea (rompe el compilador de Blade): calcular la variable en `@php` antes.
- Modo oscuro: siempre incluir variantes `dark:` en clases de color personalizadas.
- Pantallas de operador: botones y campos grandes, foco automático, usable 100% con teclado y lector de códigos.

## Tests

- `tests/Feature/<Area>/...Test.php`, `use RefreshDatabase;` → SystemSeeder se ejecuta solo.
- Helpers: `$this->actingAsRole('admin')`, `$this->actingWithPermissions(['crates.view'])`.
- Probar: permisos (403 sin permiso), módulo desactivado (404), validaciones, concurrencia (dos intentos sobre el
  mismo registro → sólo uno gana), idempotencia, auditoría.

## Git

Ramas: `main` (estable), `develop`, `feature/*`, `bugfix/*`. Commits convencionales en español:
`feat: agregar módulo de cargas`, `fix: evitar duplicación de cajones`.
