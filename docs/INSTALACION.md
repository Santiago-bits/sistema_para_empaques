# Instalación en el galpón (Windows + red LAN)

Esta guía instala el sistema en **una PC servidor** dentro del galpón. Todas las demás PCs, tablets
y celulares de la red lo usan desde el navegador, sin instalar nada.

```
      ┌─────────────── Red local del galpón (router / switch) ───────────────┐
      │                                                                      │
 ┌────┴─────┐   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐   ┌──┴───────────┐
 │ SERVIDOR │   │ PC escaneo 1 │   │ PC cargas    │   │ Oficina      │   │ Celular /    │
 │ XAMPP    │   │ (kiosco +    │   │ (lector de   │   │ (facturación,│   │ tablet       │
 │ IP fija  │   │  lector)     │   │  códigos)    │   │  reportes)   │   │ (Wi-Fi)      │
 └──────────┘   └──────────────┘   └──────────────┘   └──────────────┘   └──────────────┘
   http://192.168.1.100  ← todos entran a esta dirección
```

## 1. Requisitos del servidor

| | Mínimo | Recomendado |
|---|---|---|
| Sistema | Windows 10/11 64 bits | Windows 11 Pro |
| Procesador / RAM | 4 núcleos · 8 GB | 6+ núcleos · 16 GB |
| Disco | 100 GB SSD | 250 GB SSD + disco externo para backups |
| Red | IP **fija** en la LAN | Cable (no Wi-Fi) |
| Energía | — | UPS (corte de luz sin dañar la base) |

Software: **XAMPP 8.2** (Apache + PHP 8.2 + MariaDB), **Composer 2** y **Node.js 20 LTS** (sólo para
compilar la interfaz; los archivos compilados ya vienen en `public/build`).

Extensiones de PHP necesarias (en `C:\xampp\php\php.ini`, quitar el `;` de adelante):
`extension=pdo_mysql`, `extension=mbstring`, `extension=openssl`, `extension=zip`,
`extension=fileinfo`, `extension=curl`. No hace falta `gd` ni `soap` (QR y códigos se generan en SVG
y ARCA se consume por HTTP).

**OPcache (obligatorio para la velocidad):** en el mismo `php.ini` agregar o descomentar:

```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
```

Sin OPcache cada pedido recompila todo el sistema (medido: ~0,6 s de más por pedido); con OPcache y las
cachés del paso 4 las pantallas y el escaneo responden en décimas de segundo. El Panel desarrollador avisa
si está desactivado.

## 2. Darle IP fija al servidor

1. Panel de control → Redes → adaptador → Propiedades → IPv4.
2. Usar una IP fuera del rango DHCP del router, por ejemplo `192.168.1.100`, máscara `255.255.255.0`,
   puerta de enlace la IP del router.
3. Alternativa: reservar la IP en el router por la MAC del servidor.

## 3. Instalar el sistema

```powershell
# 1) Copiar el sistema a C:\xampp\htdocs\galpon  (o clonar el repositorio)
cd C:\xampp\htdocs
git clone https://github.com/Santiago-bits/sistema_para_empaques.git galpon
cd galpon

# 2) Dependencias de PHP (sin las de desarrollo)
composer install --no-dev --optimize-autoloader

# 3) Configuración
copy .env.example .env
php artisan key:generate
```

Editar `.env` (con el Bloc de notas):

```ini
APP_ENV=production
APP_DEBUG=false                     # IMPORTANTE: nunca true en producción
APP_URL=http://192.168.1.100        # la IP fija del servidor: se imprime en el QR de los remitos
DB_DATABASE=galpon
DB_USERNAME=galpon
DB_PASSWORD=una-clave-larga-y-segura
GALPON_INSTALLATION_ID=empaque-nombre-del-cliente
GALPON_MYSQL_BIN=C:\xampp\mysql\bin # para los backups
```

> `APP_URL` **debe** ser la IP (o nombre) con la que entran las otras PCs. Todos los enlaces absolutos
> (QR de remitos, emails de recuperación) se arman con ese valor, nunca con lo que envía el navegador.
> Si queda `localhost`, el QR y los enlaces no funcionan en otras PCs.
>
> `GALPON_TRUSTED_PROXIES` se deja **vacío** si Apache atiende directamente (lo normal). Sólo si hay un
> proxy inverso adelante se pone su IP; nunca un rango de toda la red.

## 4. Base de datos

Abrir `http://localhost/phpmyadmin` (o la consola de MariaDB) y ejecutar:

```sql
CREATE DATABASE galpon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'galpon'@'localhost' IDENTIFIED BY 'una-clave-larga-y-segura';
GRANT ALL PRIVILEGES ON galpon.* TO 'galpon'@'localhost';
FLUSH PRIVILEGES;
```

> Cambiá también la contraseña del usuario `root` de MariaDB (por defecto XAMPP lo deja vacío).

Crear las tablas y los datos base:

```powershell
php artisan migrate --force --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 5. Apache

Crear `C:\xampp\apache\conf\extra\httpd-galpon.conf`:

```apache
<VirtualHost *:80>
    ServerName 192.168.1.100
    DocumentRoot "C:/xampp/htdocs/galpon/public"
    <Directory "C:/xampp/htdocs/galpon/public">
        AllowOverride All
        Require ip 192.168.1 127.0.0.1
    </Directory>
</VirtualHost>
```

Y al final de `C:\xampp\apache\conf\httpd.conf` agregar `Include conf/extra/httpd-galpon.conf`.
El `Require ip 192.168.1` permite el acceso sólo desde la red local (ajustar al rango de la red).

En el **Firewall de Windows** permitir el puerto 80 entrante **sólo en redes privadas**.
En el panel de XAMPP marcar Apache y MySQL como **servicios** (arrancan solos con Windows).

## 6. Primer ingreso

Desde cualquier PC de la red abrir `http://192.168.1.100`. Como todavía no hay usuarios, se abre el
**instalador**: datos de la empresa y usuario Super Administrador. Después:

1. **Módulos**: activar sólo lo que se usa (Administración → Módulos).
2. **Configuración**: empresa, rango de peso permitido, numeraciones, alertas, ARCA.
3. **Catálogos**: variedades, tamaños, productores, propietarios, clientes, destinos, camiones, choferes,
   embaladores (se pueden importar desde Excel: Administración → Importar datos).
4. **Usuarios** con su rol (Operador de ingreso, Embalador, Operador de cargas, Calidad, Facturación,
   Supervisor, Administrador, Cliente/Propietario).

## 7. Tareas programadas (obligatorio)

Backups automáticos, alertas y la cola de trabajos dependen de una tarea que corra **cada minuto**.

Programador de tareas de Windows → Crear tarea:

- **General**: «Ejecutar tanto si el usuario inició sesión como si no», «Con los privilegios más altos».
- **Desencadenador**: diariamente, repetir cada **1 minuto** durante **1 día**, indefinidamente.
- **Acción**: programa `C:\xampp\php\php.exe`, argumentos `artisan schedule:run`,
  iniciar en `C:\xampp\htdocs\galpon`.

Verificación: el **Panel desarrollador** muestra un aviso si el programador no corrió en los últimos
5 minutos. Qué se programa: alertas cada 5 min, backup diario 02:00, backup semanal domingo 03:00,
procesamiento de la cola cada minuto y limpieza diaria.

## 8. Lectores de códigos y cámara del celular

**Lector USB o Bluetooth (tipo supermercado)** — la opción más rápida para puestos fijos:

- Funciona como un teclado: no requiere drivers ni configuración en el sistema.
- Configurarlo (con los códigos de su manual) para que envíe **Enter** al final de cada lectura. Así cada
  pantalla avanza sola: cajón → embalador → peso → guardado.
- Ideal: lector configurado con el mismo idioma de teclado que Windows (Español Latinoamérica). Si quedó
  en inglés, el guion de los códigos llega como apóstrofo; el sistema lo corrige solo en los campos de escaneo.
- Bluetooth: emparejarlo con la PC/tablet en modo «teclado (HID)».

**Cámara del celular o tablet** — botón «Cámara» al lado de cada campo de escaneo (producción, kiosco,
armado de cargas, mover pallets, calidad, búsqueda):

- Lee códigos de barras de las etiquetas (Code 128) y QR, completa el campo y sigue el flujo solo.
- En el armado de cargas queda abierta en modo continuo para leer cajón tras cajón.
- **«Sacar foto del código»** funciona siempre, también con `http://` en la red local.
- **Cámara en vivo** (apuntar y leer sin sacar foto): los navegadores la permiten sólo con **https**.
  Para activarla en la LAN:
  1. Generar un certificado para la IP del servidor, por ejemplo con [mkcert](https://github.com/FiloSottile/mkcert):
     `mkcert -install` y `mkcert 192.168.1.100` (genera `192.168.1.100.pem` y `192.168.1.100-key.pem`).
  2. En Apache (`httpd-ssl.conf` de XAMPP) un `<VirtualHost *:443>` igual al del paso 5 con
     `SSLEngine on`, `SSLCertificateFile` y `SSLCertificateKeyFile` apuntando a esos archivos.
  3. Instalar el certificado raíz de mkcert (`rootCA.pem`) en cada celular/tablet (Ajustes → Seguridad →
     Instalar certificado) para que el navegador lo acepte.
  4. Cambiar `APP_URL=https://192.168.1.100` y ejecutar `php artisan config:cache`.

## 9. PCs de producción (modo kiosco)

- Lector de códigos USB configurado como teclado con **Enter** al final de cada lectura.
- Crear el usuario del puesto con **modo kiosco** activado: sólo ve la pantalla de escaneo.
- Navegador en pantalla completa (F11) o acceso directo con `--kiosk http://192.168.1.100/kiosco`.

## 10. Correo (opcional)

Para que la recuperación de contraseña envíe enlaces por email, completar en `.env`:

```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=587
MAIL_USERNAME=sistema@tu-empresa.com
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=sistema@tu-empresa.com
```

Sin correo configurado el sistema funciona igual: quien no recuerda su contraseña genera un aviso al
administrador, que le asigna una temporal desde la ficha del usuario.

## 11. Actualizar a una versión nueva

```powershell
php artisan galpon:backup          # SIEMPRE antes de actualizar
php artisan down
git pull                           # o copiar los archivos nuevos
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 12. Problemas frecuentes

| Síntoma | Causa / solución |
|---|---|
| Otras PCs no abren el sistema | Firewall (puerto 80, red privada), IP fija, `Require ip` de Apache |
| El QR del remito abre «localhost» | `APP_URL` mal configurado → corregir y `php artisan config:cache` |
| «Sin conexión con el servidor» en rojo | Cable/Wi-Fi de esa PC o servidor apagado. Lo escaneado durante el corte **no** se guardó |
| Backup falla: no se encontró mysqldump | Configurar `GALPON_MYSQL_BIN=C:\xampp\mysql\bin` |
| Backups/alertas no corren solos | Falta la tarea programada del paso 7 |
| Error con código `ERR-AAAAMMDD-XXXXX` | Enviar el código a soporte (Soporte → Nuevo ticket); el detalle queda en Panel desarrollador → Errores |
| Se perdió la contraseña del administrador | En el servidor: `php artisan galpon:create-admin` |
