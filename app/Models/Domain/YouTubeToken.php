<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class YouTubeToken extends Model
{
    protected $table = 'YouTubeToken';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'expiresAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
