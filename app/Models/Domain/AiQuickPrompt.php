<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AiQuickPrompt extends Model
{
    protected $table = 'AiQuickPrompt';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'ownerId' => 'integer',
            'score' => 'decimal:4',
            'usedCount' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
