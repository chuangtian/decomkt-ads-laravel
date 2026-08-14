#!/usr/bin/env bash
set -Eeuo pipefail

APP=/var/www/admin.decomkt.com/current
BUNDLE=/root/deploy_admin/decomkt-old-current.tar.gz
IMPORT_DIR=/root/deploy_admin/old-current
STAMP=$(date +%Y%m%d%H%M%S)
BACKUP=/root/deploy_admin/pre-old-sync-${STAMP}.sql.gz
SOURCE_STORE=cmrec8n6900004kpelq7zhabl

umask 077
install -d -m 700 "$IMPORT_DIR"
tar -xzf "$BUNDLE" -C "$IMPORT_DIR" --strip-components=1

echo '=== BACKUP ==='
mysqldump --single-transaction --routines --events decomkt_ads | gzip -9 >"$BACKUP"
echo "$BACKUP"

cd "$APP"
php artisan down --retry=30
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

echo '=== CONFIGURATION ==='
php artisan legacy:import-config-bundle "$IMPORT_DIR/config_bundle.csv" --store=default-store
php artisan cache:clear
php artisan credentials:audit

echo '=== DOMAIN DATA ==='
php artisan legacy:sync-data-bundle "$IMPORT_DIR/data_bundle.jsonl" \
    --source-store="$SOURCE_STORE" \
    --target-store=default-store

echo '=== OPTIMIZE ==='
php artisan optimize:clear
php artisan optimize
chown -R www-data:www-data "$APP/storage" "$APP/bootstrap/cache"
chmod -R ug+rwX "$APP/storage" "$APP/bootstrap/cache"
php artisan up
trap - EXIT

supervisorctl restart 'decomkt-admin-queue:*'

echo '=== CONNECTIONS ==='
php artisan integrations:test all

echo '=== EXTERNAL SNAPSHOTS ==='
php artisan data:sync-design || true
php artisan data:sync-external-pages || true

echo '=== FINAL COUNTS ==='
php artisan tinker --execute='
foreach (["ReputationReview", "RedditPost", "SocialMediaPost", "FacebookPost", "Employee", "EmployeePermission", "Store"] as $table) { echo $table."=".DB::table($table)->count().PHP_EOL; }
'

echo '=== SERVICES ==='
systemctl is-active nginx mysql php8.5-fpm supervisor cron
supervisorctl status
