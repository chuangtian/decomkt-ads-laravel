<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class SeoGaCache extends Model
{
    protected $table = 'SeoGaCache';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'rowCount' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
