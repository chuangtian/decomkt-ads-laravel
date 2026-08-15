<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $storeId
 * @property string $campaignId
 * @property string $email
 * @property string $emailNormalized
 * @property string|null $fullName
 * @property string $verificationMethod
 * @property string|null $evidencePath
 * @property string|null $code
 * @property string|null $shopifyDiscountId
 * @property string $status
 * @property string|null $error
 * @property string|null $reviewedBy
 * @property string|null $aiProvider
 * @property string|null $aiModel
 * @property mixed $aiConfidence
 * @property string|null $aiReason
 * @property string|null $emailDeliveryStatus
 * @property Carbon|null $expiresAt
 * @property Carbon|null $reviewedAt
 * @property Carbon|null $emailSentAt
 * @property Carbon|null $createdAt
 */
class StudentDiscountClaim extends Model
{
    protected $table = 'StudentDiscountClaim';

    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'expiresAt' => 'datetime',
            'reviewedAt' => 'datetime',
            'emailSentAt' => 'datetime',
            'aiConfidence' => 'decimal:4',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
