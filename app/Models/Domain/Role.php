<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'Role';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'isSystem' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
