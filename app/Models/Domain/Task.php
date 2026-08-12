<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'Task';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'dueDate' => 'datetime',
            'creatorId' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
