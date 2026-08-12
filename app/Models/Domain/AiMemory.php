<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AiMemory extends Model
{
    protected $table = 'AiMemory';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'ownerId' => 'integer',
            'expiresAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
