<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class StoreConfig extends Model
{
    protected $table = 'StoreConfig';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
            'updatedAt' => 'datetime',
        ];
    }
}
