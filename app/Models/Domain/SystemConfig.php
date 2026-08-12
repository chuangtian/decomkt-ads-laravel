<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    protected $table = 'SystemConfig';

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
