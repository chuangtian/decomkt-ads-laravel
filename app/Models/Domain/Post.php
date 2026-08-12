<?php

namespace App\Models\Domain;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'Post';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'published' => 'boolean',
            'authorId' => 'integer',
        ];
    }
}
