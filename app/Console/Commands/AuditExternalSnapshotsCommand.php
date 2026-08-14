<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditExternalSnapshotsCommand extends Command
{
    protected $signature = 'data:audit-external-snapshots';

    protected $description = 'Report non-secret snapshot health and row schemas';

    public function handle(): int
    {
        $this->line(json_encode(['key' => 'table:SystemConfig', 'configuredKeys' => DB::table('SystemConfig')->orderBy('key')->pluck('key')->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line(json_encode(['key' => 'table:StoreConfig', 'configuredKeys' => DB::table('StoreConfig')->where('storeId', 'default-store')->orderBy('key')->pluck('key')->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach (['SeoMonthly', 'SeoWeekly', 'SeoDaily', 'SeoGaCache'] as $table) {
            $latest = DB::table($table)->latest($table === 'SeoMonthly' ? 'month' : ($table === 'SeoWeekly' ? 'startDate' : ($table === 'SeoDaily' ? 'date' : 'updatedAt')))->first();
            $decoded = $latest && isset($latest->data) ? json_decode((string) $latest->data, true) : null;
            $this->line(json_encode([
                'key' => 'table:'.$table,
                'recordCount' => DB::table($table)->count(),
                'latestPeriod' => $latest->month ?? $latest->startDate ?? $latest->date ?? $latest->endDate ?? null,
                'dataKeys' => is_array($decoded) ? array_keys($decoded) : [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $rows = DB::table('ExternalDataSnapshot')->orderBy('key')->get();

        foreach ($rows as $snapshot) {
            $payload = json_decode((string) $snapshot->payload, true) ?: [];
            $records = $payload['rows'] ?? (array_is_list($payload) ? $payload : []);
            $first = is_array($records) ? ($records[0] ?? []) : [];

            $this->line(json_encode([
                'key' => $snapshot->key,
                'recordCount' => (int) $snapshot->recordCount,
                'syncedAt' => $snapshot->syncedAt,
                'lastError' => $snapshot->lastError,
                'payloadError' => $payload['error'] ?? null,
                'metricLabels' => collect($payload['metrics'] ?? [])->pluck('label')->values()->all(),
                'rowKeys' => is_array($first) ? array_keys($first) : [],
                'rowSample' => $snapshot->key === 'page:/organic/affiliate' && is_array($first)
                    ? collect($first)->only(['Name', '周', '开始日期', '结束日期', '点击', 'GMV'])->all()
                    : null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
