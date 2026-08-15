<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $storeId
 * @property string $shopDomain
 * @property string $clientId
 * @property string $status
 * @property list<string>|null $scopes
 * @property string|null $accessToken
 * @property string|null $refreshToken
 * @property Carbon|null $accessTokenExpiresAt
 * @property Carbon|null $refreshTokenExpiresAt
 * @property Carbon|null $installedAt
 * @property Carbon|null $uninstalledAt
 * @property Carbon|null $lastSeenAt
 */
class ShopifyInstallation extends Model
{
    protected $table = 'ShopifyInstallation';

    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'accessToken' => 'encrypted',
            'refreshToken' => 'encrypted',
            'accessTokenExpiresAt' => 'datetime',
            'refreshTokenExpiresAt' => 'datetime',
            'installedAt' => 'datetime',
            'uninstalledAt' => 'datetime',
            'lastSeenAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
