<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class AiSuggestion extends Model
{
    protected $table = 'AiSuggestion';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
        ];
    }
}
