<?php

namespace App\Services\Credentials;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CredentialService
{
    /** @var array<string, string> */
    private const MAIL_CONFIG_PATHS = [
        'MAIL_HOST' => 'mail.mailers.smtp.host',
        'MAIL_PORT' => 'mail.mailers.smtp.port',
        'MAIL_SCHEME' => 'mail.mailers.smtp.scheme',
        'MAIL_USERNAME' => 'mail.mailers.smtp.username',
        'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
        'MAIL_FROM_ADDRESS' => 'mail.from.address',
        'MAIL_FROM_NAME' => 'mail.from.name',
    ];

    public function __construct(private readonly CacheRepository $cache) {}

    public function get(string $key, string $storeId = 'default-store', ?string $default = null): ?string
    {
        return $this->cache->remember(
            "credentials:{$storeId}:{$key}",
            now()->addMinutes(5),
            function () use ($key, $storeId, $default): ?string {
                $row = DB::table('StoreConfig')->where(['storeId' => $storeId, 'key' => $key])->first();
                if ($row) {
                    return $this->decode($row->value, (bool) $row->encrypted, $key);
                }

                if (isset(self::MAIL_CONFIG_PATHS[$key])) {
                    $value = config(self::MAIL_CONFIG_PATHS[$key]);

                    return $value !== null && $value !== '' ? (string) $value : $default;
                }

                $row = DB::table('SystemConfig')->where('key', $key)->first();

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
