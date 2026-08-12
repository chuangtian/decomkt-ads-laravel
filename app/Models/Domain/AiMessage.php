<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    protected $table = 'AiMessage';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
        ];
    }
}
