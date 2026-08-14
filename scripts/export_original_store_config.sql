\copy (SELECT key, value FROM "StoreConfig" ORDER BY key) TO '/tmp/store-config.csv' WITH (FORMAT csv, HEADER true)
