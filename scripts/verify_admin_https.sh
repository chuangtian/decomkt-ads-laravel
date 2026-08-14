#!/usr/bin/env bash
set -Eeuo pipefail

echo '=== HTTP REDIRECT ==='
curl --silent --show-error --head http://admin.decomkt.com/login | sed -n '1,8p'

echo '=== HTTPS LOGIN ==='
curl --fail --silent --show-error --head https://admin.decomkt.com/login | sed -n '1,12p'

echo '=== HTTPS APP ROUTES ==='
for path in / /workspace/brand /organic/seo /ecommerce/design /reputation/overview /ads/facebook; do
    code=$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "https://admin.decomkt.com${path}")
    printf '%-24s %s\n' "$path" "$code"
    if [[ "$code" -ge 500 ]]; then
        exit 1
    fi
done

echo '=== CERTIFICATE ==='
openssl s_client -connect admin.decomkt.com:443 -servername admin.decomkt.com </dev/null 2>/dev/null \
    | openssl x509 -noout -subject -issuer -dates

echo '=== NGINX ==='
nginx -t

echo '=== RECENT ERRORS ==='
tail -n 30 /var/log/nginx/error.log 2>/dev/null || true
tail -n 30 /var/www/admin.decomkt.com/current/storage/logs/laravel.log 2>/dev/null || true
