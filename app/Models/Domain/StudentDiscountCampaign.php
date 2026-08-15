<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $storeId
 * @property bool $enabled
 * @property bool $schoolEmailEnabled
 * @property bool $studentIdEnabled
 * @property mixed $educationDomains
 * @property mixed $allowedOrigins
 * @property string $discountType
 * @property string $discountTarget
 * @property mixed $discountProductIds
 * @property mixed $discountCollectionIds
 * @property mixed $discountValue
 * @property int $usageLimit
 * @property int $validityDays
 * @property string|null $codePrefix
 * @property mixed $minimumSubtotal
 * @property bool $combinesWithProduct
 * @property bool $combinesWithOrder
 * @property bool $combinesWithShipping
 */
class StudentDiscountCampaign extends Model
{
    protected $table = 'StudentDiscountCampaign';

    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'schoolEmailEnabled' => 'boolean',
            'universityEnabled' => 'boolean',
            'studentIdEnabled' => 'boolean',
            'discountValue' => 'decimal:4',
            'discountProductIds' => 'array',
            'discountCollectionIds' => 'array',
            'minimumSubtotal' => 'decimal:4',
            'validityDays' => 'integer',
            'usageLimit' => 'integer',
            'combinesWithProduct' => 'boolean',
            'combinesWithOrder' => 'boolean',
            'combinesWithShipping' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
