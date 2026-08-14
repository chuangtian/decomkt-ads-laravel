#!/usr/bin/env bash
set -Eeuo pipefail

cat >/etc/nginx/conf.d/decomkt-fastcgi-buffers.conf <<'NGINX'
# Inertia responses preload many split frontend assets through the Link header.
fastcgi_buffer_size 128k;
fastcgi_buffers 8 128k;
fastcgi_busy_buffers_size 256k;
NGINX

nginx -t
systemctl reload nginx

echo 'FastCGI response buffers updated.'
