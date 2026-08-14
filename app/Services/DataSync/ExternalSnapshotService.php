<?php

namespace App\Services\DataSync;

use Illuminate\Support\Facades\DB;

class ExternalSnapshotService
{
    public function put(string $key, mixed $payload, string $storeId = 'default-store'): void
    {
        $key = $this->key($key, $storeId);
        $count = is_array($payload) ? count($payload['rows'] ?? $payload) : 0;
        DB::table('ExternalDataSnapshot')->updateOrInsert(['key' => $key], [
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'recordCount' => $count, 'syncedAt' => now(), 'lastError' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function get(string $key, mixed $default = null, string $storeId = 'default-store'): mixed
    {
        $key = $this->key($key, $storeId);
        $payload = DB::table('ExternalDataSnapshot')->where('key', $key)->value('payload');

        return $payload ? json_decode($payload, true, 512, JSON_THROW_ON_ERROR) : $default;
    }

    public function status(string $key, string $storeId = 'default-store'): ?object
    {
        $key = $this->key($key, $storeId);

        return DB::table('ExternalDataSnapshot')->where('key', $key)->first(['recordCount', 'syncedAt', 'lastError']);
    }

    public function fail(string $key, string $message, string $storeId = 'default-store'): void
    {
        $key = $this->key($key, $storeId);
        DB::table('ExternalDataSnapshot')->where('key', $key)->update(['lastError' => $message, 'updated_at' => now()]);
    }

    private function key(string $key, string $storeId): string
    {
        return $storeId === 'default-store' ? $key : "store:{$storeId}:{$key}";
    }
}
