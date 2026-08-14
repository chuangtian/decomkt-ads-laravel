#!/usr/bin/env bash
set -Eeuo pipefail

APP=/var/www/admin.decomkt.com/current
cd "$APP"

chown -R www-data:www-data "$APP/storage" "$APP/bootstrap/cache"
chmod -R ug+rwX "$APP/storage" "$APP/bootstrap/cache"
supervisorctl start 'decomkt-admin-queue:*' || supervisorctl restart 'decomkt-admin-queue:*'

echo '=== CONNECTIONS ==='
set +e
php artisan integrations:test all
CONNECTION_STATUS=$?

echo '=== DESIGN SNAPSHOT ==='
php artisan data:sync-design
DESIGN_STATUS=$?

echo '=== EXTERNAL SNAPSHOTS ==='
php artisan data:sync-external-pages
EXTERNAL_STATUS=$?
set -e

echo '=== FINAL COUNTS ==='
php artisan tinker --execute='
foreach (["ReputationReview", "RedditPost", "SocialMediaPost", "FacebookPost", "Employee", "EmployeePermission", "Store", "StoreConfig", "SystemConfig"] as $table) { echo $table."=".DB::table($table)->count().PHP_EOL; }
'

echo '=== SERVICES ==='
systemctl is-active nginx mysql php8.5-fpm supervisor cron
supervisorctl status
printf 'integration_status=%s design_status=%s external_status=%s\n' "$CONNECTION_STATUS" "$DESIGN_STATUS" "$EXTERNAL_STATUS"
