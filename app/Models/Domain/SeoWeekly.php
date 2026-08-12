<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class SeoWeekly extends Model
{
    protected $table = 'SeoWeekly';

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
