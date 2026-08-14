#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT=/software/decomkt-ads
OUT=/root/decomkt-migration-export

cd "$PROJECT"
install -d -m 700 "$OUT"

run_psql() {
    docker compose -f docker-compose.prod.yml exec -T postgres \
        sh -lc 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" "$@"' -- "$@"
}

{
    echo '=== SOURCE ==='
    hostname
    git rev-parse HEAD
    git status --short
    echo '=== STORES ==='
    run_psql -At <<'SQL'
SELECT id || '|' || slug || '|' || name || '|' || status FROM "Store" ORDER BY "createdAt";
SQL
    echo '=== CONFIG KEYS ==='
    run_psql -At <<'SQL'
SELECT 'STORE|' || "storeId" || '|' || key || '|' || encrypted::text FROM "StoreConfig"
UNION ALL
SELECT 'SYSTEM|-|' || key || '|' || encrypted::text FROM "SystemConfig"
ORDER BY 1;
SQL
    echo '=== TABLE COUNTS ==='
    run_psql -At <<'SQL'
SELECT format('SELECT %L || ''|'' || count(*) FROM %I;', tablename, tablename)
FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename
\gexec
SQL
} >"$OUT/audit.txt"

run_psql <<'SQL' >"$OUT/config_bundle.csv"
COPY (
    WITH target_store AS (
        SELECT id
        FROM "Store"
        WHERE slug = 'macfoxbike' OR status = 'ACTIVE'
        ORDER BY CASE WHEN slug = 'macfoxbike' THEN 0 ELSE 1 END, "createdAt"
        LIMIT 1
    )
    SELECT 'store'::text AS scope, key, value, encrypted
    FROM "StoreConfig"
    WHERE "storeId" = (SELECT id FROM target_store)
    UNION ALL
    SELECT 'system'::text AS scope, key, value, encrypted
    FROM "SystemConfig"
) TO STDOUT WITH (FORMAT CSV, HEADER TRUE);
SQL

run_psql -At <<'SQL' >"$OUT/data_bundle.jsonl"
SELECT format(
    'SELECT json_build_object(''table'', %L, ''row'', row_to_json(source_row)) FROM %I AS source_row;',
    tablename,
    tablename
)
FROM pg_tables
WHERE schemaname = 'public'
  AND tablename NOT IN ('StoreConfig', 'SystemConfig')
ORDER BY tablename
\gexec
SQL

chmod 600 "$OUT"/*
tar -C /root -czf /root/decomkt-migration-export.tar.gz decomkt-migration-export
chmod 600 /root/decomkt-migration-export.tar.gz

echo "audit=$OUT/audit.txt"
echo "bundle=/root/decomkt-migration-export.tar.gz"
wc -c "$OUT/config_bundle.csv" "$OUT/data_bundle.jsonl" /root/decomkt-migration-export.tar.gz
