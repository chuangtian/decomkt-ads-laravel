<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class StoreMember extends Model
{
    protected $table = 'StoreMember';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'employeeId' => 'integer',
            'assignedAt' => 'datetime',
        ];
    }
}
