#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=/var/www/admin.decomkt.com/current
OUTPUT=${1:-/root/deploy_admin/backups/pre-multistore-20260814.sql.gz}

env_value() {
    local key="$1"
    grep -m1 "^${key}=" "$APP_DIR/.env" | cut -d= -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//'
}

DB_HOST_VALUE="$(env_value DB_HOST)"
DB_PORT_VALUE="$(env_value DB_PORT)"
DB_DATABASE_VALUE="$(env_value DB_DATABASE)"
DB_USERNAME_VALUE="$(env_value DB_USERNAME)"
DB_PASSWORD_VALUE="$(env_value DB_PASSWORD)"

mkdir -p "$(dirname "$OUTPUT")"
MYSQL_PWD="$DB_PASSWORD_VALUE" mysqldump \
    --single-transaction --quick --no-tablespaces \
    -h "$DB_HOST_VALUE" -P "$DB_PORT_VALUE" \
    -u "$DB_USERNAME_VALUE" "$DB_DATABASE_VALUE" \
    | gzip -1 > "$OUTPUT"
test -s "$OUTPUT"
ls -lh "$OUTPUT"
