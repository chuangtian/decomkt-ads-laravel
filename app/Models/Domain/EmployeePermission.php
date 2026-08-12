<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class EmployeePermission extends Model
{
    protected $table = 'EmployeePermission';

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
