#!/usr/bin/env bash
set -Eeuo pipefail

cd /var/www/admin.decomkt.com/current

echo '=== CONFIG KEYS ==='
php artisan tinker --execute='
DB::table("StoreConfig")->orderBy("storeId")->orderBy("key")->get(["storeId", "key", "encrypted"])->each(function ($row) { echo "STORE|{$row->storeId}|{$row->key}|".((int) $row->encrypted).PHP_EOL; });
DB::table("SystemConfig")->orderBy("key")->get(["key", "encrypted"])->each(function ($row) { echo "SYSTEM|-|{$row->key}|".((int) $row->encrypted).PHP_EOL; });
'

echo '=== TABLE COUNTS ==='
php artisan tinker --execute='
foreach (Schema::getTableListing() as $table) { echo $table."|".DB::table($table)->count().PHP_EOL; }
'

echo '=== CREDENTIAL AUDIT ==='
php artisan credentials:audit || true

echo '=== INTEGRATION TESTS ==='
php artisan integrations:test all || true
