<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class SeoMonthly extends Model
{
    protected $table = 'SeoMonthly';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'syncedAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
