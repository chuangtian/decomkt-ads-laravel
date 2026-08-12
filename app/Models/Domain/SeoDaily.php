<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class SeoDaily extends Model
{
    protected $table = 'SeoDaily';

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
