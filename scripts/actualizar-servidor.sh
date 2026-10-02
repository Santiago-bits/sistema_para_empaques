#!/usr/bin/env bash
# Actualiza el sistema en el servidor (Hostinger u otro Linux) con lo último de GitHub.
# Uso (por SSH, dentro de la carpeta del sistema):   bash scripts/actualizar-servidor.sh
# Los DATOS del servidor no se tocan: sólo se actualiza el código y se aplican las migraciones nuevas.
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP:-php}"           # si el php de la consola es viejo: PHP=/opt/alt/php82/usr/bin/php bash scripts/actualizar-servidor.sh
COMPOSER="${COMPOSER:-composer}"
BRANCH="${BRANCH:-main}"

echo "==> Versión actual: $($PHP artisan --version 2>/dev/null || echo '?')"
$PHP artisan down --retry=30 || true
trap '$PHP artisan up || true' EXIT

echo "==> Bajando cambios de GitHub ($BRANCH)"
git fetch origin "$BRANCH"
git merge --ff-only "origin/$BRANCH"

echo "==> Dependencias de PHP"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "==> Backup antes de migrar (si el hosting lo permite)"
$PHP artisan galpon:backup --type=manual || echo "   (sin backup por comando: usá los backups del panel de Hostinger)"

echo "==> Migraciones y datos base"
$PHP artisan migrate --force
$PHP artisan db:seed --class=SystemSeeder --force

echo "==> Cachés"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

$PHP artisan up
trap - EXIT
echo "==> Listo: $($PHP artisan --version)"
$PHP artisan galpon:deploy-check || true
