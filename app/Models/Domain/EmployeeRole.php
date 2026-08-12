<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class EmployeeRole extends Model
{
    protected $table = 'EmployeeRole';

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
