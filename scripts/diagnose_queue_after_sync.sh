#!/usr/bin/env bash
set -u

echo '=== SUPERVISOR ==='
supervisorctl status
supervisorctl tail -100 decomkt-admin-queue:decomkt-admin-queue_00 stderr 2>&1 || true
supervisorctl tail -100 decomkt-admin-queue:decomkt-admin-queue_00 stdout 2>&1 || true

echo '=== CONFIG ==='
sed -n '1,160p' /etc/supervisor/conf.d/decomkt-admin-queue.conf

echo '=== APP ==='
cd /var/www/admin.decomkt.com/current
php artisan about --only=environment,drivers
php artisan queue:work database --once --tries=1 --timeout=30 -v 2>&1 || true

echo '=== LOG ==='
tail -n 100 storage/logs/laravel.log 2>/dev/null || true
