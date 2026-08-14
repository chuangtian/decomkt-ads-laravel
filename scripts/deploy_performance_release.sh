#!/usr/bin/env bash
set -Eeuo pipefail

BASE=/var/www/admin.decomkt.com
ARCHIVE=/root/deploy_admin/decomkt-multistore-20260814-v6.tar.gz
STAMP="$(date +%Y%m%d%H%M%S)"
RELEASE="$BASE/releases/$STAMP"
PREVIOUS="$(readlink -f "$BASE/current")"
SWITCHED=0

rollback() {
    if [[ "$SWITCHED" == 1 && -n "$PREVIOUS" && -d "$PREVIOUS" ]]; then
        ln -sfn "$PREVIOUS" "$BASE/current"
        systemctl reload php8.5-fpm || true
        supervisorctl restart 'decomkt-admin-queue:*' || true
    fi
}
trap rollback ERR

mkdir -p "$RELEASE/storage/framework/cache/data" "$RELEASE/storage/framework/sessions" "$RELEASE/storage/framework/views" "$RELEASE/storage/logs" "$RELEASE/bootstrap/cache"
tar -xzf "$ARCHIVE" -C "$RELEASE"
cp "$PREVIOUS/.env" "$RELEASE/.env"

cd "$RELEASE"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

find app routes config database/migrations -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/decomkt-php-lint.log
test -f public/build/manifest.json
php artisan route:list >/tmp/decomkt-route-list.log
php scripts/smoke_page_access.php
php artisan migrate --force
php artisan optimize
php artisan storage:link || true

chown -R root:root "$RELEASE"
chown -R www-data:www-data "$RELEASE/storage" "$RELEASE/bootstrap/cache"
chown root:www-data "$RELEASE/.env"
chmod 640 "$RELEASE/.env"
chmod -R ug+rwX "$RELEASE/storage" "$RELEASE/bootstrap/cache"

ln -sfn "$RELEASE" "$BASE/current"
SWITCHED=1
systemctl reload php8.5-fpm
supervisorctl restart 'decomkt-admin-queue:*'
nginx -t

trap - ERR
echo "PREVIOUS_RELEASE=$PREVIOUS"
echo "DEPLOYED_RELEASE=$RELEASE"
echo "PHP_LINT_FILES=$(wc -l </tmp/decomkt-php-lint.log)"
echo "ROUTES=$(wc -l </tmp/decomkt-route-list.log)"
echo "HTTPS_LOGIN=$(curl -sS -o /dev/null -w '%{http_code}' https://admin.decomkt.com/login)"
echo "HTTPS_ROOT=$(curl -sS -o /dev/null -w '%{http_code}' https://admin.decomkt.com/)"
