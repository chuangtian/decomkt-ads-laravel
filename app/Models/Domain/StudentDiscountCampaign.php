<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class StudentDiscountCampaign extends Model
{
    protected $table = 'StudentDiscountCampaign';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'schoolEmailEnabled' => 'boolean',
            'universityEnabled' => 'boolean',
            'studentIdEnabled' => 'boolean',
            'discountValue' => 'decimal:4',
            'minimumSubtotal' => 'decimal:4',
            'validityDays' => 'integer',
            'combinesWithProduct' => 'boolean',
            'combinesWithOrder' => 'boolean',
            'combinesWithShipping' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
