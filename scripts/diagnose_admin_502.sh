#!/usr/bin/env bash
set -u

echo '=== SERVICES ==='
systemctl status php8.5-fpm nginx --no-pager -l | sed -n '1,160p'

echo '=== NGINX ERRORS ==='
tail -n 100 /var/log/nginx/error.log

echo '=== PHP LOGS ==='
journalctl -u php8.5-fpm --since '15 minutes ago' --no-pager -n 160
tail -n 100 /var/log/php8.5-fpm.log 2>/dev/null || true

echo '=== LARAVEL LOGS ==='
tail -n 160 /var/www/admin.decomkt.com/current/storage/logs/laravel.log 2>/dev/null || true

echo '=== RESOURCES ==='
free -h
df -h /
ps -eo pid,ppid,%cpu,%mem,rss,etime,args --sort=-rss | sed -n '1,30p'

echo '=== SOCKET ==='
ls -la /run/php/
