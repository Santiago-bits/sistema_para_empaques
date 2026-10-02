#!/usr/bin/env bash
# Ejecutar por SSH después de cada deploy por Git de Hostinger (el botón «Implementar» de hPanel):
#   cd ~/domains/TU-DOMINIO/public_html && bash scripts/despues-del-deploy.sh
# Hostinger ya bajó el código y corrió composer install; esto aplica lo que falta.
# Los DATOS no se tocan: sólo se crean las tablas/columnas nuevas.
set -euo pipefail
cd "$(dirname "$0")/.."
PHP="${PHP:-php}"   # si el php de la consola es viejo: PHP=/opt/alt/php82/usr/bin/php bash scripts/despues-del-deploy.sh

if [ ! -f .env ]; then
    echo "Falta el archivo .env: copialo de .env.example y completá la base de datos (ver docs/HOSTINGER.md)."
    exit 1
fi

echo "==> Limpiando cachés de la versión anterior"
rm -f bootstrap/cache/*.php
$PHP artisan package:discover

echo "==> Migraciones y datos base"
$PHP artisan migrate --force
$PHP artisan db:seed --class=SystemSeeder --force

[ -e public/storage ] || $PHP artisan storage:link

echo "==> Cachés nuevas"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo "==> Listo: $($PHP artisan --version)"
$PHP artisan galpon:deploy-check || true
