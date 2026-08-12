<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class StudentDiscountClaim extends Model
{
    protected $table = 'StudentDiscountClaim';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'expiresAt' => 'datetime',
            'reviewedAt' => 'datetime',
            'emailSentAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
