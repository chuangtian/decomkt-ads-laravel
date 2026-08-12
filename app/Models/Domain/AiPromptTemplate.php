<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AiPromptTemplate extends Model
{
    protected $table = 'AiPromptTemplate';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
