<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class TaskAssignee extends Model
{
    protected $table = 'TaskAssignee';

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
