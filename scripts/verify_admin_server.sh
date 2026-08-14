#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=/var/www/admin.decomkt.com/current

systemctl restart cron
chmod 600 /root/deploy_admin/.env /root/deploy_admin/database-password 2>/dev/null || true

echo '=== SERVICES ==='
for service in nginx mysql php8.5-fpm supervisor cron; do
    printf '%-14s ' "$service"
    systemctl is-active "$service"
done

echo '=== HTTP ==='
curl --fail --silent --show-error --head -H 'Host: admin.decomkt.com' http://127.0.0.1/login | sed -n '1,10p'

echo '=== LARAVEL ==='
cd "$APP_DIR"
php artisan about --only=environment,cache,drivers | sed -n '1,100p'

echo '=== DATABASE ==='
php artisan tinker --execute="echo 'tables='.DB::selectOne(\"SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE()\")->n.PHP_EOL; echo 'users='.DB::table('users')->count().PHP_EOL; echo 'snapshots='.(Schema::hasTable('external_data_snapshots') ? DB::table('external_data_snapshots')->count() : (Schema::hasTable('ExternalDataSnapshot') ? DB::table('ExternalDataSnapshot')->count() : 'table-missing')).PHP_EOL;"

echo '=== QUEUE ==='
supervisorctl status

echo '=== SCHEDULE ==='
php artisan schedule:list | sed -n '1,120p'

echo '=== LOGS ==='
tail -n 25 storage/logs/laravel.log 2>/dev/null || true
