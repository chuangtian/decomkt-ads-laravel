<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $table = 'AiConversation';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'ownerId' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
