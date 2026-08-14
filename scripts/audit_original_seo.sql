SELECT 'SeoDaily' AS table_name, COUNT(*) AS rows_count FROM "SeoDaily"
UNION ALL SELECT 'SeoWeekly', COUNT(*) FROM "SeoWeekly"
UNION ALL SELECT 'SeoMonthly', COUNT(*) FROM "SeoMonthly"
UNION ALL SELECT 'SeoGaCache', COUNT(*) FROM "SeoGaCache"
UNION ALL SELECT 'StoreConfig', COUNT(*) FROM "StoreConfig"
UNION ALL SELECT 'SystemConfig', COUNT(*) FROM "SystemConfig";

SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name IN ('SeoDaily', 'SeoWeekly', 'SeoMonthly', 'SeoGaCache')
ORDER BY table_name, ordinal_position;

SELECT "storeId", COUNT(*) AS config_count,
       string_agg(key, ', ' ORDER BY key) AS config_keys
FROM "StoreConfig"
GROUP BY "storeId";
