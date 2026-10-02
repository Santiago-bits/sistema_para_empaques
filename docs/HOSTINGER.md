# Panel General en Hostinger (y cómo trabajar en tu PC)

Esquema:

```
 TU PC (desarrollo)                    GitHub                         HOSTINGER (producción)
 ─────────────────                     ──────                         ──────────────────────
 Galpón     localhost:8000   ──push──▶  sistema_para_empaques  ──pull──▶  Panel General
 Panel      127.0.0.1:8001                                                https://panel.tu-dominio.com
 (bases galpon / galpon_panel)                                              ▲
                                                                            │ uso y soporte (internet)
                                                     Empaque 1, Empaque 2, … (cada uno en su PC)
```

- **Lo que viaja de tu PC al hosting es el código** (por GitHub). Los cambios de tablas viajan como
  migraciones y se aplican solos al actualizar.
- **Los datos del hosting nunca se pisan** con los de tu PC: clientes, reportes y tickets del Panel General
  quedan en Hostinger.

> **Restricción de Hostinger**: `proc_open` está deshabilitado. El sistema ya está preparado (el `composer.json`
> no ejecuta `php artisan` al instalar y las tareas programadas corren dentro del mismo proceso). No agregues
> `"@php artisan …"` a los scripts de `composer.json`: el deploy se corta.

> **Si en Hostinger ya había una versión anterior del sistema** (la de códigos QR de septiembre): esa base de datos
> tiene otras tablas. **No** apuntes esta versión a esa base: creá una **base nueva** en hPanel (paso 3 de la
> sección 2) y poné sus datos en `.env`. La base vieja queda intacta por si necesitás algo de ella.

---

## 1. Trabajar en tu PC

Requisitos: XAMPP con **MySQL encendido** (XAMPP Control Panel → MySQL → Start; tildá «Svc» para que arranque
con Windows), PHP 8.2 y Composer.

| Para… | Hacer |
|---|---|
| Abrir el sistema del galpón | Doble clic en `scripts\iniciar-galpon.bat` → http://localhost:8000 |
| Preparar el Panel General local (una sola vez) | Doble clic en `scripts\preparar-panel-local.bat` (crea la base `galpon_panel` y `.env.panel`) |
| Abrir el Panel General local | Doble clic en `scripts\iniciar-panel.bat` → http://127.0.0.1:8001 |

Los dos pueden estar abiertos a la vez (usan bases y sesiones distintas). La primera vez que entrás al panel
te pide crear el **Super Administrador**.

**Probar la conexión galpón ↔ panel en tu PC:**

1. En el panel local: Panel general → Clientes y uso → **Nuevo cliente** (por ejemplo «Galpón de prueba»).
2. Copiá las 3 líneas que muestra la ficha y pegalas al final del `.env` del galpón (no del `.env.panel`).
   La URL queda `GALPON_CENTRAL_URL=http://127.0.0.1:8001`.
3. En la carpeta del sistema: `php artisan config:clear` y luego `php artisan galpon:central-sync --report`.
4. En el panel aparece el cliente «Conectado» con su uso. Creá un ticket en el galpón (Soporte → Nuevo ticket),
   tocá «Sincronizar ahora», respondelo desde el panel y volvé a sincronizar: la respuesta aparece en el galpón.

**Cuando un cambio te gusta**, subilo a GitHub:

```bash
git add -A
git commit -m "feat: descripción del cambio"
git push origin main
```

---

## 2. Instalar el Panel General en Hostinger (una sola vez)

Necesitás un plan con **acceso SSH** (Premium, Business o Cloud; en hPanel: Avanzado → Acceso SSH). Si usás un
VPS de Hostinger, seguí docs/INSTALACION.md como en cualquier Linux.

1. **Dominio o subdominio**: hPanel → Sitios web → agregar `panel.tu-dominio.com` (o usar el dominio principal).
2. **PHP 8.2 o 8.3**: hPanel → Avanzado → Configuración de PHP. Verificá que estén activas `pdo_mysql`,
   `mbstring`, `openssl`, `zip`, `fileinfo`, `curl` (vienen activas por defecto).
3. **Base de datos**: hPanel → Bases de datos → MySQL → crear base y usuario (anotá nombre, usuario y contraseña;
   el host suele ser `localhost`).
4. **SSL**: hPanel → Seguridad → SSL → instalar el certificado gratuito para el dominio.
5. **Conectarte por SSH** (los datos están en hPanel → Acceso SSH):
   ```bash
   ssh -p 65002 u123456789@IP-DEL-SERVIDOR
   ```
6. **Bajar el sistema** al lado de la carpeta pública (nunca dentro de `public_html`):
   ```bash
   cd ~/domains/panel.tu-dominio.com
   git clone https://github.com/Santiago-bits/sistema_para_empaques.git sistema
   cd sistema
   php -v        # debe decir 8.2 o más; si no: usar /opt/alt/php82/usr/bin/php en lugar de php
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
   Si el repositorio es privado, GitHub te pide un token: creálo en GitHub → Settings → Developer settings →
   Personal access tokens, con permiso de solo lectura del repositorio.
7. **Configurar `.env`** (`nano .env`):
   ```ini
   APP_NAME="Panel General"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://panel.tu-dominio.com
   DB_HOST=localhost
   DB_DATABASE=u123456789_panel
   DB_USERNAME=u123456789_panel
   DB_PASSWORD=la-contraseña-de-la-base
   GALPON_INSTALLATION_ID=panel-central
   GALPON_CENTRAL_MODE=true
   MAIL_MAILER=log
   ```
8. **Tablas, enlaces y cachés**:
   ```bash
   php artisan migrate --force --seed
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
9. **Apuntar la web a la carpeta `public`**: el dominio sirve `public_html`; reemplazala por un enlace a
   `sistema/public` (si `public_html` tenía algo, se guarda como copia):
   ```bash
   cd ~/domains/panel.tu-dominio.com
   mv public_html public_html_anterior
   ln -s sistema/public public_html
   ```
10. **Tareas programadas**: hPanel → Avanzado → Cron Jobs → «cada minuto» con el comando (ajustá tu usuario):
    ```
    /usr/bin/php /home/u123456789/domains/panel.tu-dominio.com/sistema/artisan schedule:run
    ```
11. **Primer ingreso**: abrí `https://panel.tu-dominio.com`, creá el Super Administrador y vas a ver
    **Panel general → Clientes y uso** en el menú.
12. **Verificar**: `php artisan galpon:deploy-check` debe terminar en «Instalación correcta».

---

## 3. Pasar tus cambios al hosting

**Si usás la implementación automática de Git de Hostinger** (hPanel → Avanzado → Git): con cada push a `main`,
Hostinger baja el código y ejecuta `composer install`. Eso **no** aplica las migraciones ni regenera las cachés:
después de cada deploy entrá por SSH y ejecutá el script de abajo (si el código ya está bajado, sólo hace el resto).

Después de hacer `git push` desde tu PC, por SSH:

```bash
cd ~/domains/panel.tu-dominio.com/sistema
bash scripts/actualizar-servidor.sh
```

El script pone el sitio en mantenimiento unos segundos, baja el código de GitHub, instala dependencias, hace un
backup (si el plan lo permite), aplica las migraciones nuevas, regenera las cachés y vuelve a abrir el sitio.
Si el `php` de la consola es viejo: `PHP=/opt/alt/php82/usr/bin/php bash scripts/actualizar-servidor.sh`.

**Empaques clientes**: en cada galpón se actualiza igual (docs/INSTALACION.md, paso 11). Conviene actualizar
primero el Panel General y después los empaques.

---

## 4. Conectar cada empaque al Panel de Hostinger

En el Panel General: Clientes y uso → **Nuevo cliente** → copiar las 3 líneas de la ficha al `.env` del servidor
de ese empaque, con la URL de Hostinger (`GALPON_CENTRAL_URL=https://panel.tu-dominio.com`). En el empaque:

```bash
php artisan config:cache
php artisan galpon:deploy-check --ping     # «Respuesta del Panel General: HTTP 200» = conectado
```

## 5. Backups y problemas frecuentes en Hostinger

- **Backups**: Hostinger hace copias automáticas (hPanel → Archivos → Copias de seguridad). En planes compartidos
  el backup por comando puede no estar disponible (`galpon:deploy-check` avisa «proc_open deshabilitado»).
- **Error 500 después de actualizar**: `php artisan config:clear` y revisar `storage/logs/laravel.log`; luego
  volver a `php artisan config:cache`.
- **«Instalación o clave de licencia inválida» (401) en un empaque**: el `GALPON_INSTALLATION_ID` o la
  `GALPON_LICENSE_KEY` de su `.env` no coinciden con la ficha del cliente.
- **El panel no muestra datos de un empaque**: en el empaque, `php artisan galpon:central-sync --report` muestra
  el error exacto; verificar que tenga internet y la tarea programada funcionando.
- **Permisos**: `chmod -R 775 storage bootstrap/cache`.
