<?php

namespace App\Services\DataSync;

use App\Services\Credentials\CredentialService;
use App\Services\Integrations\FeishuConnector;
use Illuminate\Support\Facades\DB;
use Throwable;

class DesignDataSyncService
{
    public const SNAPSHOT_KEY = 'feishu.design';

    public function __construct(
        private readonly CredentialService $credentials,
        private readonly FeishuConnector $feishu,
    ) {}

    /** @return list<array<string, mixed>> */
    public function sync(string $storeId = 'default-store'): array
    {
        try {
            $records = $this->feishu->bitableRecords(
                $this->credentials->require('FEISHU_DESIGN_APP_TOKEN', $storeId),
                $this->credentials->require('FEISHU_DESIGN_TABLE_ID', $storeId),
                '',
                $storeId,
            );
            DB::table('ExternalDataSnapshot')->updateOrInsert(
                ['key' => $this->key($storeId)],
                ['payload' => json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'recordCount' => count($records), 'syncedAt' => now(), 'lastError' => null, 'created_at' => now(), 'updated_at' => now()],
            );

            return $records;
        } catch (Throwable $exception) {
            DB::table('ExternalDataSnapshot')->where('key', $this->key($storeId))->update(['lastError' => $exception->getMessage(), 'updated_at' => now()]);
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function rows(string $storeId = 'default-store'): array
    {
        $payload = DB::table('ExternalDataSnapshot')->where('key', $this->key($storeId))->value('payload');
        if (! $payload) return [];

        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }

    public function status(string $storeId = 'default-store'): ?object
    {
        return DB::table('ExternalDataSnapshot')->where('key', $this->key($storeId))->first(['recordCount', 'syncedAt', 'lastError']);
    }

    private function key(string $storeId): string
    {
        return $storeId === 'default-store' ? self::SNAPSHOT_KEY : "store:{$storeId}:".self::SNAPSHOT_KEY;
    }
}
