<?php

namespace App\Services\Credentials;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CredentialService
{
    public function __construct(private readonly CacheRepository $cache) {}

    public function get(string $key, string $storeId = 'default-store', ?string $default = null): ?string
    {
        return $this->cache->remember(
            "credentials:{$storeId}:{$key}",
            now()->addMinutes(5),
            function () use ($key, $storeId, $default): ?string {
                $row = DB::table('StoreConfig')->where(['storeId' => $storeId, 'key' => $key])->first();
                $row ??= DB::table('SystemConfig')->where('key', $key)->first();

                return $row ? $this->decode($row->value, (bool) $row->encrypted, $key) : $default;
            },
        );
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function many(array $keys, string $storeId = 'default-store'): array
    {
        $values = [];
        foreach ($keys as $key) {
            $value = $this->get($key, $storeId);
            if ($value !== null && $value !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    public function require(string $key, string $storeId = 'default-store'): string
    {
        $value = $this->get($key, $storeId);
        if ($value === null || $value === '') {
            throw new RuntimeException("Credential {$key} is not configured.");
        }

        return $value;
    }

    public function forget(string $key, string $storeId = 'default-store'): void
    {
        $this->cache->forget("credentials:{$storeId}:{$key}");
    }

    private function decode(string $value, bool $encrypted, string $key): string
    {
        if (! $encrypted) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable $exception) {
            throw new RuntimeException("Credential {$key} cannot be decrypted.", previous: $exception);
        }
    }
}
