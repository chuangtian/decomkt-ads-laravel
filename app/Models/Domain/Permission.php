<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'Permission';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
