<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $table = 'Store';

    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
