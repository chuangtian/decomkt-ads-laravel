#!/usr/bin/env bash
set -Eeuo pipefail

BASE=/var/www/admin.decomkt.com
DEPLOY=/root/deploy_admin
STAMP="$(date +%Y%m%d%H%M%S)"
RELEASE="$BASE/releases/$STAMP"
DB_NAME=decomkt_ads
DB_USER=decomkt_admin

mkdir -p "$RELEASE" "$BASE/releases" "$DEPLOY/backups"
tar -xzf "$DEPLOY/decomkt_laravel_20260813.tar.gz" -C "$RELEASE"
cp "$DEPLOY/.env" "$RELEASE/.env"

if mysql -NBe "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$DB_NAME'" | grep -qx "$DB_NAME"; then
    mysqldump --single-transaction --routines --triggers "$DB_NAME" > "$DEPLOY/backups/${DB_NAME}_${STAMP}.sql"
fi

DB_PASS="$(openssl rand -hex 24)"
install -m 600 /dev/null "$DEPLOY/database-password"
printf '%s' "$DB_PASS" > "$DEPLOY/database-password"

mysql <<SQL
DROP DATABASE IF EXISTS ${DB_NAME};
CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
mysql "$DB_NAME" < "$DEPLOY/decomkt_ads_20260813.sql"

set_env() {
    local key="$1" value="$2"
    if grep -q "^${key}=" "$RELEASE/.env"; then
        sed -i "s#^${key}=.*#${key}=${value}#" "$RELEASE/.env"
    else
        printf '%s=%s\n' "$key" "$value" >> "$RELEASE/.env"
    fi
}

set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL https://admin.decomkt.com
set_env ASSET_URL https://admin.decomkt.com
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASS"
set_env CACHE_STORE database
set_env SESSION_DRIVER database
set_env SESSION_SECURE_COOKIE true
set_env SESSION_DOMAIN admin.decomkt.com
set_env QUEUE_CONNECTION database
set_env LOG_LEVEL warning

cd "$RELEASE"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
php artisan migrate --force
php artisan storage:link || true
php artisan optimize

chown -R root:root "$RELEASE"
chown -R www-data:www-data "$RELEASE/storage" "$RELEASE/bootstrap/cache"
chown root:www-data "$RELEASE/.env"
chmod 640 "$RELEASE/.env"
chmod -R ug+rwX "$RELEASE/storage" "$RELEASE/bootstrap/cache"
ln -sfn "$RELEASE" "$BASE/current"

cat > /etc/nginx/sites-available/admin.decomkt.com <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name admin.decomkt.com;
    root /var/www/admin.decomkt.com/current/public;
    index index.php;
    charset utf-8;
    client_max_body_size 50M;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        # Inertia business pages preload many split assets in the Link header.
        fastcgi_buffer_size 128k;
        fastcgi_buffers 8 128k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sfn /etc/nginx/sites-available/admin.decomkt.com /etc/nginx/sites-enabled/admin.decomkt.com
unlink /etc/nginx/sites-enabled/default 2>/dev/null || true
nginx -t
systemctl reload nginx

cat > /etc/supervisor/conf.d/decomkt-admin-queue.conf <<'SUPERVISOR'
[program:decomkt-admin-queue]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php artisan queue:work database --sleep=2 --tries=3 --timeout=180 --max-time=3600
directory=/var/www/admin.decomkt.com/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/admin.decomkt.com/current/storage/logs/queue.log
stopwaitsecs=360
SUPERVISOR
supervisorctl reread
supervisorctl update
supervisorctl restart decomkt-admin-queue:*

cat > /etc/cron.d/decomkt-admin <<'CRON'
* * * * * www-data cd /var/www/admin.decomkt.com/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/decomkt-admin
systemctl reload cron

echo "DEPLOYED_RELEASE=$RELEASE"
echo "APP_STATUS=$(curl -sS -o /dev/null -w '%{http_code}' -H 'Host: admin.decomkt.com' http://127.0.0.1/login)"
echo "DB_TABLES=$(mysql -NBe "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")"
echo "QUEUE_STATUS=$(supervisorctl status decomkt-admin-queue:* | awk '{print $2}')"
