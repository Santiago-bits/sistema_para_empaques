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
> tiene otras tablas y en `bootstrap/cache` quedan cachés viejas. Usá una **base nueva** (sección 2, paso 3) y corré
> `scripts/despues-del-deploy.sh`: borra esas cachés. La base vieja queda intacta por si necesitás algo de ella.

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

## 2. Instalar en Hostinger con el deploy por Git de hPanel (una sola vez)

Es la forma más simple: Hostinger baja el código de GitHub **dentro de `public_html`** cada vez que tocás
«Implementar» (o en cada push, si activás la implementación automática). El archivo `.htaccess` de la raíz del
proyecto manda todo a la carpeta `public/` y bloquea los archivos sensibles: **no lo borres**.

1. **PHP 8.2 o 8.3**: hPanel → Avanzado → Configuración de PHP (las extensiones necesarias vienen activas).
2. **SSL**: hPanel → Seguridad → SSL → instalar el certificado gratuito.
3. **Base de datos NUEVA**: hPanel → Bases de datos → MySQL → crear base y usuario. Si en el hosting había una
   versión anterior del sistema, **no reutilices esa base** (tiene otras tablas): dejala como respaldo.
4. **Git**: hPanel → Avanzado → Git → repositorio `https://github.com/Santiago-bits/sistema_para_empaques.git`,
   rama `main`, carpeta de instalación vacía (= `public_html`). Tocá **Implementar**.
5. **Primera configuración por SSH** (hPanel → Avanzado → Acceso SSH):
   ```bash
   cd ~/domains/TU-DOMINIO/public_html
   cp .env.example .env
   nano .env
   ```
   Completar:
   ```ini
   APP_NAME="Panel General"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://TU-DOMINIO
   DB_HOST=localhost
   DB_DATABASE=u123456789_panel
   DB_USERNAME=u123456789_panel
   DB_PASSWORD=la-contraseña-de-la-base
   GALPON_INSTALLATION_ID=panel-central
   GALPON_CENTRAL_MODE=true
   MAIL_MAILER=log
   ```
   y después:
   ```bash
   php artisan key:generate
   bash scripts/despues-del-deploy.sh
   ```
   (Si `php -v` dice una versión menor a 8.2: `PHP=/opt/alt/php82/usr/bin/php bash scripts/despues-del-deploy.sh`
   y usá esa misma ruta en lugar de `php`.)
6. **Tareas programadas**: hPanel → Avanzado → Cron Jobs → «cada minuto»:
   ```
   /usr/bin/php /home/u123456789/domains/TU-DOMINIO/public_html/artisan schedule:run
   ```
7. **Primer ingreso**: abrí `https://TU-DOMINIO`, creá el Super Administrador y vas a ver **Panel general**.

### Alternativa (más prolija, sin el botón de hPanel)

El código en una carpeta `sistema/` al lado de `public_html` y `public_html` como enlace a `sistema/public`
(`ln -s sistema/public public_html`). Se actualiza por SSH con `bash scripts/actualizar-servidor.sh`. Sirve
igual; elegí **una** de las dos formas y no las mezcles.

---

## 3. Pasar tus cambios al hosting

1. En tu PC: probá el cambio (`scripts\iniciar-galpon.bat` / `scripts\iniciar-panel.bat`) y subilo:
   ```bash
   git push origin main
   ```
2. En hPanel → Git: **Implementar** (o automático). Hostinger baja el código y corre `composer install`.
3. Por SSH, una vez:
   ```bash
   cd ~/domains/TU-DOMINIO/public_html && bash scripts/despues-del-deploy.sh
   ```
   Aplica migraciones nuevas, datos base y regenera cachés. Aunque te olvides de este paso, el sistema detecta
   las cachés de la versión anterior y las descarta solo; lo que no puede hacer solo es crear tablas nuevas.

Conviene actualizar primero el Panel General y después los empaques (docs/INSTALACION.md, paso 11).

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
