<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'Employee';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'emailVerified' => 'boolean',
            'isDefaultAdmin' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
