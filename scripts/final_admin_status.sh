#!/usr/bin/env bash
set -Eeuo pipefail

echo '=== ACTIVE SERVICES ==='
for service in nginx mysql php8.5-fpm supervisor cron certbot.timer; do
    printf '%-16s ' "$service"
    systemctl is-active "$service"
done

echo '=== APPLICATION ==='
cd /var/www/admin.decomkt.com/current
printf 'release=' && readlink -f /var/www/admin.decomkt.com/current
printf 'environment=' && php artisan env | tail -n 1 | sed 's/^[[:space:]]*//'
printf 'queue=' && supervisorctl status decomkt-admin-queue:decomkt-admin-queue_00
printf 'https=' && curl --silent --show-error --output /dev/null --write-out '%{http_code}\n' https://admin.decomkt.com/login

echo '=== ERRORS SINCE BUFFER FIX ==='
journalctl -u nginx -u php8.5-fpm --since '2026-08-13 08:02:00' --no-pager -p warning || true
grep '2026/08/13 08:0[2-9].*upstream sent too big header' /var/log/nginx/error.log || true
tail -n 20 storage/logs/laravel.log 2>/dev/null || true
