#!/usr/bin/env bash
set -Eeuo pipefail

ARCHIVE=/root/deploy_admin/decomkt-multistore-20260814-v4.tar.gz
CURRENT=/var/www/admin.decomkt.com/current
TMP="$(mktemp -d /root/deploy_admin/preflight-v4.XXXXXX)"

cleanup() {
    case "$TMP" in
        /root/deploy_admin/preflight-v4.*) rm -rf -- "$TMP" ;;
    esac
}
trap cleanup EXIT

tar -xzf "$ARCHIVE" -C "$TMP"
cp "$CURRENT/.env" "$TMP/.env"
cd "$TMP"

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
find app routes config database/migrations -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/decomkt-v4-php-lint.log
php artisan route:list >/tmp/decomkt-v4-route-list.log
php artisan route:list --name=store. >/tmp/decomkt-v4-store-routes.log
grep -q '{storeSlug}/organic/seo' /tmp/decomkt-v4-store-routes.log
if grep -q 'stores/{storeSlug}' /tmp/decomkt-v4-store-routes.log; then
    echo 'Unexpected /stores/{storeSlug} route remains.' >&2
    exit 1
fi
php artisan migrate --pretend --force >/tmp/decomkt-v4-migrate-pretend.log
test -f public/build/manifest.json

echo "PHP_LINT_FILES=$(wc -l </tmp/decomkt-v4-php-lint.log)"
echo "ROUTES=$(wc -l </tmp/decomkt-v4-route-list.log)"
cat /tmp/decomkt-v4-store-routes.log
