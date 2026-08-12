<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $table = 'RolePermission';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'assignedAt' => 'datetime',
        ];
    }
}
